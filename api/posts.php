<?php
// ============================================================
//  api/posts.php — Content pipeline
//  GET  action=list [&status=…]   → posts in the workspace
//  GET  action=review_queue       → review/approved/rejected
//  POST action=save               → create/update (workflow rules)
//  POST action=approve|reject     → admin decisions
//  POST action=publish_now        → immediate publish (admin)
//  POST action=delete             → delete
// ============================================================

require_once __DIR__ . '/_init.php';

$action = $_GET['action'] ?? $body['action'] ?? 'list';

$SAVE_STATUSES = ['draft', 'review', 'scheduled', 'published'];

try {
    switch ($action) {

        case 'list':
            [$bw, $bp] = brand_where('p.brand_id');
            $status = $_GET['status'] ?? '';
            $sw = ''; $sp = [];
            if ($status !== '') {
                $statuses = array_values(array_intersect(explode(',', $status),
                    ['draft','review','approved','scheduled','publishing','published','failed','rejected']));
                if ($statuses) {
                    $sw = ' AND p.status IN (' . implode(',', array_fill(0, count($statuses), '?')) . ')';
                    $sp = $statuses;
                }
            }
            $stmt = $pdo->prepare("
                SELECT p.*, b.name AS brand_name, b.color AS brand_color,
                       d.name AS destination_name, d.platform AS destination_platform
                FROM posts p
                JOIN brands b ON b.id = p.brand_id
                LEFT JOIN destinations d ON d.id = p.destination_id
                WHERE 1=1 $bw $sw
                ORDER BY FIELD(p.status,'failed','publishing','scheduled','review','approved','draft','published','rejected'),
                         p.post_date IS NULL, p.post_date, p.publish_time
                LIMIT 300
            ");
            $stmt->execute(array_merge($bp, $sp));
            json_ok($stmt->fetchAll());
            break;

        case 'review_queue':
            [$bw, $bp] = brand_where('p.brand_id');
            $stmt = $pdo->prepare("
                SELECT p.*, b.name AS brand_name, b.color AS brand_color,
                       d.name AS destination_name
                FROM posts p
                JOIN brands b ON b.id = p.brand_id
                LEFT JOIN destinations d ON d.id = p.destination_id
                WHERE p.status IN ('review','approved','rejected') $bw
                ORDER BY FIELD(p.status,'review','approved','rejected'), p.updated_at DESC
                LIMIT 200
            ");
            $stmt->execute($bp);
            json_ok($stmt->fetchAll());
            break;

        case 'save':
            $id       = $body['id'] ?: uid();
            $brandId  = $body['brand_id'] ?? '';
            $title    = trim($body['title'] ?? '');
            $content  = $body['content'] ?? null;
            $imageUrl = trim($body['image_url'] ?? '') ?: null;
            $platform = $body['platform'] ?? '';
            $status   = $body['status'] ?? 'draft';
            $date     = $body['post_date'] ?? null;
            $time     = trim($body['publish_time'] ?? '') ?: null;
            $mode     = $body['publish_mode'] ?? 'manual';
            $destId   = $body['destination_id'] ?: null;
            $source   = in_array($body['source'] ?? '', ['manual', 'studio'], true) ? $body['source'] : 'manual';
            $aiGen    = !empty($body['ai_generated']) ? 1 : 0;

            if (!$brandId || !$title || !$platform) json_error('brand_id, title and platform are required.', 422);
            if (!isset(mh_platforms()[$platform])) json_error('Invalid platform.', 422);
            if (!in_array($status, $SAVE_STATUSES, true)) json_error('Invalid status.', 422);
            if ($date !== null && $date !== '' && !valid_date($date)) json_error('Invalid post date (expected YYYY-MM-DD).', 422);
            if ($time !== null && !valid_time($time)) json_error('Invalid publish time (expected HH:MM).', 422);
            if (!in_array($mode, ['manual', 'auto'], true)) $mode = 'manual';

            if ($mode === 'auto') {
                if (empty(mh_platforms()[$platform]['adapter'])) {
                    json_error(mh_platforms()[$platform]['label'] . ' has no publishing adapter yet — it is manual-handoff only.', 422);
                }
                if (!$destId) json_error('Auto-publish needs a destination. Pick one or switch to manual.', 422);
                $chk = $pdo->prepare('SELECT brand_id, platform FROM destinations WHERE id = ? AND is_active = 1');
                $chk->execute([$destId]);
                $d = $chk->fetch();
                if (!$d) json_error('Selected destination not found or inactive.', 422);
                if ($d['brand_id'] !== $brandId) json_error('Destination belongs to a different brand.', 422);
                if ($d['platform'] !== $platform) json_error('Destination platform does not match the post platform.', 422);
            }

            $stmt = $pdo->prepare('SELECT * FROM posts WHERE id = ?');
            $stmt->execute([$id]);
            $existing = $stmt->fetch() ?: null;

            $approvedBy = $existing['approved_by'] ?? null;
            $approvedAt = $existing['approved_at'] ?? null;

            // Auto posts need approval before queueing (admin queueing
            // counts as approval).
            if ($mode === 'auto' && $status === 'scheduled' && $approvedAt === null) {
                if (is_admin()) {
                    $approvedBy = auth_user()['full_name'] ?? 'admin';
                    $approvedAt = date('Y-m-d H:i:s');
                } else {
                    json_error('Auto-publish posts need approval first. Submit for review.', 422);
                }
            }

            // Material edits by non-admins invalidate the approval.
            if ($existing && !is_admin() && $approvedAt !== null
                && in_array($existing['status'], ['approved', 'scheduled'], true)) {
                $changed = $existing['title'] !== $title
                    || (string)$existing['content'] !== (string)$content
                    || (string)$existing['image_url'] !== (string)$imageUrl
                    || $existing['platform'] !== $platform
                    || (string)$existing['destination_id'] !== (string)$destId;
                if ($changed) { $status = 'review'; $approvedBy = null; $approvedAt = null; }
            }

            if ($existing && $existing['status'] === 'publishing') {
                json_error('This post is publishing right now — try again in a moment.', 409);
            }

            $stmt = $pdo->prepare("
                INSERT INTO posts
                  (id, brand_id, destination_id, source, platform, title, content, image_url,
                   status, publish_mode, post_date, publish_time, ai_generated,
                   approved_by, approved_at, created_by)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE
                  destination_id=VALUES(destination_id), platform=VALUES(platform),
                  title=VALUES(title), content=VALUES(content), image_url=VALUES(image_url),
                  status=VALUES(status), publish_mode=VALUES(publish_mode),
                  post_date=VALUES(post_date), publish_time=VALUES(publish_time),
                  ai_generated=VALUES(ai_generated),
                  approved_by=VALUES(approved_by), approved_at=VALUES(approved_at)
            ");
            $stmt->execute([
                $id, $brandId, $destId, $source, $platform, $title, $content, $imageUrl,
                $status, $mode, $date ?: null, $time,
                $existing ? (int)($existing['ai_generated'] || $aiGen) : $aiGen,
                $approvedBy, $approvedAt,
                $existing['created_by'] ?? (auth_user()['username'] ?? null),
            ]);
            json_ok(['id' => $id, 'status' => $status]);
            break;

        case 'approve':
            require_admin_api();
            $id    = $body['id'] ?? '';
            $queue = !empty($body['queue']);
            $note  = trim($body['note'] ?? '') ?: null;
            if (!$id) json_error('Missing id', 422);

            $stmt = $pdo->prepare('SELECT * FROM posts WHERE id = ?');
            $stmt->execute([$id]);
            $post = $stmt->fetch();
            if (!$post) json_error('Post not found', 404);
            if (!in_array($post['status'], ['draft', 'review', 'rejected', 'approved'], true)) {
                json_error('Only draft, review or rejected posts can be approved.', 422);
            }

            $newStatus = 'approved';
            if ($queue) {
                if (!$post['post_date']) json_error('Set a post date before queueing.', 422);
                if ($post['publish_mode'] === 'auto' && !$post['destination_id']) {
                    json_error('This auto post has no destination — assign one first.', 422);
                }
                $newStatus = 'scheduled';
            }
            $pdo->prepare('UPDATE posts SET status=?, approved_by=?, approved_at=NOW(), review_note=? WHERE id=?')
                ->execute([$newStatus, auth_user()['full_name'] ?? 'admin', $note, $id]);
            audit('post_approved', 'post', $id, '"' . $post['title'] . '" → ' . $newStatus);
            json_ok(['id' => $id, 'status' => $newStatus]);
            break;

        case 'reject':
            require_admin_api();
            $id   = $body['id'] ?? '';
            $note = trim($body['note'] ?? '');
            if (!$id) json_error('Missing id', 422);
            if ($note === '') json_error('A rejection note is required so the author knows what to fix.', 422);
            $stmt = $pdo->prepare('SELECT status, title FROM posts WHERE id = ?');
            $stmt->execute([$id]);
            $p = $stmt->fetch();
            if (!$p) json_error('Post not found', 404);
            if (!in_array($p['status'], ['draft', 'review', 'approved'], true)) {
                json_error('Only draft, review or approved posts can be rejected.', 422);
            }
            $pdo->prepare("UPDATE posts SET status='rejected', review_note=?, approved_by=NULL, approved_at=NULL WHERE id=?")
                ->execute([$note, $id]);
            audit('post_rejected', 'post', $id, '"' . $p['title'] . '": ' . $note);
            json_ok(['id' => $id, 'status' => 'rejected']);
            break;

        case 'publish_now':
            require_admin_api();
            $id = $body['id'] ?? '';
            if (!$id) json_error('Missing id', 422);
            $stmt = $pdo->prepare('SELECT * FROM posts WHERE id = ?');
            $stmt->execute([$id]);
            $post = $stmt->fetch();
            if (!$post) json_error('Post not found', 404);
            if ($post['approved_at'] === null) json_error('Approve the post before publishing.', 422);

            require_once APP_ROOT . '/includes/runner.php';
            $r = run_publish_post($pdo, $post);
            if ($r['ok']) json_ok(['published' => true, 'url' => $r['url']]);
            json_error($r['error'] ?? 'Publishing failed.', 422);
            break;

        case 'delete':
            $id = $body['id'] ?? '';
            if (!$id) json_error('Missing id', 422);
            $pdo->prepare('DELETE FROM posts WHERE id = ?')->execute([$id]);
            audit('post_deleted', 'post', $id);
            json_ok(['deleted' => $id]);
            break;

        default:
            json_error('Unknown action: ' . $action, 404);
    }
} catch (PDOException $e) {
    json_error('Database error: ' . $e->getMessage(), 500);
}
