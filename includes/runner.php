<?php
// ============================================================
//  includes/runner.php — Publishing + automation engine
//  Used by api/posts.php ("Publish now"), api/automations.php
//  ("Test run") and cron/scheduler.php (scheduled runs).
// ============================================================

require_once APP_ROOT . '/lib/adapters/adapter.php';
require_once APP_ROOT . '/lib/ai.php';

// ── Publishing ─────────────────────────────────────────────

/**
 * Publish one approved+scheduled post through its destination.
 * Claims the row (status → publishing) so overlapping cron runs
 * can never double-publish. Records the outcome on the post and
 * in the audit log.
 */
function run_publish_post(PDO $pdo, array $post): array {
    $postId = $post['id'];

    $fail = function (string $msg) use ($pdo, $postId) {
        $pdo->prepare("
            UPDATE posts SET status='failed', publish_error=?, publish_attempts=publish_attempts+1
            WHERE id=?
        ")->execute([$msg, $postId]);
        audit('publish_failed', 'post', $postId, $msg);
        return ['ok' => false, 'error' => $msg, 'url' => null];
    };

    if (emergency_stopped()) {
        return ['ok' => false, 'error' => 'Emergency stop is active — publishing is paused.', 'url' => null];
    }
    if (!$post['destination_id']) return $fail('Post has no destination assigned.');

    $stmt = $pdo->prepare('SELECT * FROM destinations WHERE id = ?');
    $stmt->execute([$post['destination_id']]);
    $dest = $stmt->fetch();
    if (!$dest) return $fail('Destination no longer exists.');
    if (!$dest['is_active']) return $fail('Destination "' . $dest['name'] . '" is inactive.');

    // Claim — only scheduled/failed posts may enter publishing.
    $claim = $pdo->prepare("UPDATE posts SET status='publishing' WHERE id = ? AND status IN ('scheduled','failed')");
    $claim->execute([$postId]);
    if ($claim->rowCount() === 0) {
        return ['ok' => false, 'error' => 'Post is already publishing or was already published.', 'url' => null];
    }

    $result = adapter_publish($dest['platform'], destination_config($dest), $post);

    if (!empty($result['ok'])) {
        $pdo->prepare("
            UPDATE posts
            SET status='published', published_at=NOW(), external_id=?,
                post_url=COALESCE(?, post_url), publish_error=NULL,
                publish_attempts=publish_attempts+1
            WHERE id=?
        ")->execute([$result['external_id'] ?? null, $result['url'] ?? null, $postId]);
        audit('published', 'post', $postId,
            '"' . $post['title'] . '" via ' . $dest['platform'] . (!empty($result['url']) ? ' → ' . $result['url'] : ''));
        return ['ok' => true, 'error' => null, 'url' => $result['url'] ?? null];
    }
    return $fail($result['error'] ?? 'Unknown publishing error.');
}

/** Publish every due post (auto mode, approved+scheduled, time reached). */
function run_due_posts(PDO $pdo, int $limit = 20): array {
    if (emergency_stopped()) return [];
    $stmt = $pdo->prepare("
        SELECT * FROM posts
        WHERE publish_mode = 'auto'
          AND status = 'scheduled'
          AND approved_at IS NOT NULL
          AND destination_id IS NOT NULL
          AND post_date IS NOT NULL
          AND (
                post_date < CURDATE()
             OR (post_date = CURDATE() AND (publish_time IS NULL OR publish_time <= CURTIME()))
          )
        ORDER BY post_date, publish_time
        LIMIT " . (int)$limit
    );
    $stmt->execute();
    $results = [];
    foreach ($stmt->fetchAll() as $post) {
        $r = run_publish_post($pdo, $post);
        $results[] = ['post_id' => $post['id'], 'title' => $post['title'], 'ok' => $r['ok'], 'error' => $r['error'], 'url' => $r['url']];
    }
    return $results;
}

// ── Automations ────────────────────────────────────────────

/**
 * Execute one automation: AI-research the topic, create up to
 * max_drafts posts in REVIEW status. A successful run creates
 * drafts — it never publishes anything.
 * $occurrenceKey makes scheduled runs idempotent; test runs pass
 * a unique key.
 */
function run_automation(PDO $pdo, array $auto, string $occurrenceKey, string $kind = 'scheduled'): array {
    // Claim the occurrence — the UNIQUE key blocks double runs.
    try {
        $pdo->prepare('INSERT INTO automation_runs (automation_id, occurrence_key, kind) VALUES (?,?,?)')
            ->execute([$auto['id'], $occurrenceKey, $kind]);
    } catch (PDOException $e) {
        return ['ok' => false, 'error' => 'This occurrence already ran.', 'drafts' => 0];
    }
    $runId = (int)$pdo->lastInsertId();

    $finish = function (string $status, string $msg, int $drafts) use ($pdo, $runId, $auto): array {
        $pdo->prepare('UPDATE automation_runs SET status=?, message=?, drafts_created=? WHERE id=?')
            ->execute([$status, $msg, $drafts, $runId]);
        $pdo->prepare('UPDATE automations SET last_run_at=NOW() WHERE id=?')->execute([$auto['id']]);
        audit('automation_run', 'automation', $auto['id'], $auto['name'] . ': ' . $msg);
        return ['ok' => $status === 'success', 'error' => $status === 'success' ? null : $msg, 'drafts' => $drafts, 'message' => $msg];
    };

    if (emergency_stopped()) return $finish('error', 'Emergency stop active — run skipped.', 0);

    $stmt = $pdo->prepare('SELECT * FROM brands WHERE id = ?');
    $stmt->execute([$auto['brand_id']]);
    $brand = $stmt->fetch();
    if (!$brand) return $finish('error', 'Brand no longer exists.', 0);

    $gen = ai_generate([
        'brand'    => $brand,
        'platform' => $auto['platform'],
        'topic'    => $auto['topic_prompt'],
        'count'    => max(1, min(5, (int)$auto['max_drafts'])),
    ]);
    if (!$gen['ok']) return $finish('error', 'AI generation failed: ' . $gen['error'], 0);

    // Platforms with adapters queue for auto-publish after approval;
    // others are manual-handoff drafts.
    $hasAdapter = !empty(mh_platforms()[$auto['platform']]['adapter']);
    $mode = ($hasAdapter && $auto['destination_id']) ? 'auto' : 'manual';

    $ins = $pdo->prepare("
        INSERT INTO posts
          (id, brand_id, destination_id, automation_id, source, platform, title, content,
           status, publish_mode, post_date, publish_time, ai_generated, created_by)
        VALUES (?,?,?,?,?,?,?,?, 'review', ?, CURDATE(), ?, 1, 'automation')
    ");
    $n = 0;
    foreach ($gen['variants'] as $v) {
        $ins->execute([
            uid(), $auto['brand_id'], $auto['destination_id'] ?: null, $auto['id'], 'automation',
            $auto['platform'], mb_substr($v['title'], 0, 500), $v['content'],
            $mode, $auto['run_time'],
        ]);
        $n++;
    }
    return $finish('success', $n . ' draft' . ($n === 1 ? '' : 's') . ' created and sent to the Approval center.', $n);
}

/**
 * Run every enabled automation whose schedule occurrence is due.
 * Occurrence = today's date once run_time (Arizona) has passed,
 * respecting frequency (daily / weekdays / weekly=Mon / monthly=1st).
 */
function run_due_automations(PDO $pdo): array {
    if (emergency_stopped()) return [];
    $now   = new DateTime('now');
    $today = $now->format('Y-m-d');
    $dow   = (int)$now->format('N');  // 1=Mon … 7=Sun
    $dom   = (int)$now->format('j');
    $time  = $now->format('H:i:s');

    $autos = $pdo->query('SELECT * FROM automations WHERE enabled = 1')->fetchAll();
    $results = [];
    foreach ($autos as $auto) {
        $due = match ($auto['frequency']) {
            'daily'    => true,
            'weekdays' => $dow <= 5,
            'weekly'   => $dow === 1,
            'monthly'  => $dom === 1,
            default    => false,
        };
        if (!$due || $auto['run_time'] > $time) continue;

        // Skip silently if this occurrence already ran.
        $chk = $pdo->prepare('SELECT 1 FROM automation_runs WHERE automation_id=? AND occurrence_key=?');
        $chk->execute([$auto['id'], $today]);
        if ($chk->fetch()) continue;

        $r = run_automation($pdo, $auto, $today, 'scheduled');
        $results[] = ['automation' => $auto['name'], 'ok' => $r['ok'], 'message' => $r['message'] ?? $r['error']];
    }
    return $results;
}
