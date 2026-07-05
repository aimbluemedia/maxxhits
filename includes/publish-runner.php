<?php
// ============================================================
//  includes/publish-runner.php — Shared publish execution
//  Used by both api/automation.php ("Publish now") and
//  cron/publish-scheduler.php (scheduled runs).
// ============================================================

require_once APP_ROOT . '/lib/publishers/publisher.php';

/** Write an automation log row. */
function automation_log(PDO $pdo, ?string $postId, ?string $channelId, string $level, string $message): void {
    $stmt = $pdo->prepare('INSERT INTO automation_logs (post_id, channel_id, level, message) VALUES (?,?,?,?)');
    $stmt->execute([$postId, $channelId, $level, $message]);
}

/** Decrypted channel config as array. */
function channel_config(array $channel): array {
    $json = decrypt_secret($channel['config_enc'] ?? null);
    $cfg  = json_decode($json, true);
    return is_array($cfg) ? $cfg : [];
}

/**
 * Publish one post through its channel.
 * Claims the row (status → publishing), dispatches to the
 * platform module, then records the outcome on the post and in
 * automation_logs. Returns ['ok' => bool, 'error' => ?string, 'url' => ?string].
 */
function run_publish_post(PDO $pdo, array $post): array {
    $postId = $post['id'];
    $chId   = $post['channel_id'];

    $fail = function (string $msg) use ($pdo, $postId, $chId) {
        $pdo->prepare("
            UPDATE posts SET status='failed', publish_error=?, publish_attempts=publish_attempts+1
            WHERE id=?
        ")->execute([$msg, $postId]);
        automation_log($pdo, $postId, $chId, 'error', $msg);
        return ['ok' => false, 'error' => $msg, 'url' => null];
    };

    if (!$chId) return $fail('Post has no channel assigned.');

    $stmt = $pdo->prepare('SELECT * FROM channels WHERE id = ?');
    $stmt->execute([$chId]);
    $channel = $stmt->fetch();
    if (!$channel) return $fail('Channel no longer exists.');
    if (!$channel['is_active']) return $fail('Channel "' . $channel['name'] . '" is inactive.');

    // Claim the post — guards against two overlapping cron runs
    // publishing the same post twice.
    $claim = $pdo->prepare("
        UPDATE posts SET status='publishing'
        WHERE id = ? AND status IN ('scheduled','failed')
    ");
    $claim->execute([$postId]);
    if ($claim->rowCount() === 0) {
        return ['ok' => false, 'error' => 'Post is already being published or was already published.', 'url' => null];
    }

    automation_log($pdo, $postId, $chId, 'info',
        'Publishing "' . $post['title'] . '" via ' . $channel['platform'] . ' channel "' . $channel['name'] . '"');

    $result = publisher_publish($channel['platform'], channel_config($channel), $post);

    if (!empty($result['ok'])) {
        $pdo->prepare("
            UPDATE posts
            SET status='published', published_at=NOW(), external_id=?,
                post_url=COALESCE(?, post_url), publish_error=NULL,
                publish_attempts=publish_attempts+1
            WHERE id=?
        ")->execute([$result['external_id'] ?? null, $result['url'] ?? null, $postId]);
        automation_log($pdo, $postId, $chId, 'success',
            'Published "' . $post['title'] . '"' . (!empty($result['url']) ? ' → ' . $result['url'] : ''));
        return ['ok' => true, 'error' => null, 'url' => $result['url'] ?? null];
    }

    return $fail($result['error'] ?? 'Unknown publishing error.');
}

/**
 * Find due auto-publish posts and publish them.
 * Due = auto mode, scheduled, post_date <= today, and
 * publish_time <= now (or no time set).
 * Returns a per-post result summary.
 */
function run_due_posts(PDO $pdo, int $limit = 20): array {
    $stmt = $pdo->prepare("
        SELECT * FROM posts
        WHERE publish_mode = 'auto'
          AND status = 'scheduled'
          AND channel_id IS NOT NULL
          AND post_date IS NOT NULL
          AND (
                post_date < CURDATE()
             OR (post_date = CURDATE() AND (publish_time IS NULL OR publish_time <= CURTIME()))
          )
        ORDER BY post_date, publish_time
        LIMIT " . (int)$limit
    );
    $stmt->execute();
    $due = $stmt->fetchAll();

    $results = [];
    foreach ($due as $post) {
        $r = run_publish_post($pdo, $post);
        $results[] = [
            'post_id' => $post['id'],
            'title'   => $post['title'],
            'ok'      => $r['ok'],
            'error'   => $r['error'],
            'url'     => $r['url'],
        ];
    }
    return $results;
}
