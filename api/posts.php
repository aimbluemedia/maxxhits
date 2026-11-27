<?php
// ============================================================
//  api/posts.php — Posts CRUD + platform stats
//  GET  ?company_id=X [&year=&month=]  → posts for a company
//  GET  action=platform_stats          → per-platform counts
//  POST action=save                    → create/update (admin/CM)
//  POST action=delete                  → delete (admin/CM)
// ============================================================

require_once __DIR__ . '/_init.php';

$action = $_GET['action'] ?? $body['action'] ?? 'list';

$PLATFORMS = ['blog','facebook','instagram','twitter','linkedin','tiktok','youtube','reddit','pinterest'];
$STATUSES  = ['draft','review','approved','scheduled','publishing','published','failed','rejected'];
// Statuses a client may request directly via save; approved/rejected
// only ever come from the approve/reject actions below.
$SAVE_STATUSES = ['draft','review','scheduled','published'];

function require_post_editor(): void {
    if (!is_admin() && !has_role('content_manager')) {
        json_error('Forbidden — content manager or admin access required.', 403);
    }
}

try {
    switch ($action) {

        case 'list':
            $coId  = $_GET['company_id'] ?? '';
            $year  = isset($_GET['year'])  && $_GET['year']  !== '' ? (int)$_GET['year']  : null;
            $month = isset($_GET['month']) && $_GET['month'] !== '' ? (int)$_GET['month'] : null;
            if (!$coId) json_error('company_id required', 422);
            if (!can_view_company($coId)) json_error('Forbidden', 403);

            $sql = "
                SELECT p.*, ch.name AS channel_name, ch.platform AS channel_platform
                FROM posts p
                LEFT JOIN channels ch ON ch.id = p.channel_id
                WHERE p.company_id = ?
            ";
            $params = [$coId];
            if ($year && $month) {
                $sql .= ' AND YEAR(p.post_date) = ? AND MONTH(p.post_date) = ?';
                $params[] = $year;
                $params[] = $month;
            }
            $sql .= ' ORDER BY p.post_date, p.platform';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            json_ok($stmt->fetchAll());
            break;

        case 'platform_stats':
            $coId  = $_GET['company_id'] ?? '';
            $year  = $_GET['year']  ?? null;
            $month = $_GET['month'] ?? null;
            if (!$coId) json_error('company_id required', 422);
            if (!can_view_company($coId)) json_error('Forbidden', 403);

            $where  = 'company_id = ?';
            $params = [$coId];
            if ($year && $month) {
                $where .= ' AND YEAR(post_date) = ? AND MONTH(post_date) = ?';
                $params[] = (int)$year;
                $params[] = (int)$month;
            }
            $stmt = $pdo->prepare("
                SELECT platform,
                       COUNT(*) AS total,
                       SUM(status='published') AS published,
                       SUM(status='scheduled') AS scheduled,
                       SUM(status='failed')    AS failed
                FROM posts
                WHERE $where
                GROUP BY platform
            ");
            $stmt->execute($params);
            json_ok($stmt->fetchAll());
            break;

        case 'save':
            require_post_editor();
            $id       = $body['id'] ?: uid();
            $coId     = $body['company_id'] ?? '';
            $title    = trim($body['title'] ?? '');
            $content  = $body['content'] ?? null;
            $imageUrl = trim($body['image_url'] ?? '') ?: null;
            $platform = $body['platform'] ?? '';
            $status   = $body['status'] ?? 'draft';
            $date     = $body['post_date'] ?? null;
            $time     = trim($body['publish_time'] ?? '') ?: null;
            $mode     = $body['publish_mode'] ?? 'manual';
            $chId     = $body['channel_id'] ?: null;
            $assignee = trim($body['assignee'] ?? '') ?: null;
            $url      = trim($body['post_url'] ?? '') ?: null;
            $aiGen    = !empty($body['ai_generated']) ? 1 : 0;

            if (!$coId || !$title || !$platform) json_error('company_id, title and platform are required.', 422);
            if (!can_view_company($coId)) json_error('Forbidden', 403);
            if (!in_array($platform, $PLATFORMS, true)) json_error('Invalid platform.', 422);
            if (!in_array($status, $SAVE_STATUSES, true)) json_error('Invalid status.', 422);
            if ($date !== null && $date !== '' && !valid_date($date)) json_error('Invalid post date (expected YYYY-MM-DD).', 422);
            if ($time !== null && !valid_time($time)) json_error('Invalid publish time (expected HH:MM).', 422);
            if (!in_array($mode, ['manual', 'auto'], true)) $mode = 'manual';

            if ($mode === 'auto') {
                if (!$chId) json_error('Auto-publish requires a channel. Pick one or switch to manual.', 422);
                $chk = $pdo->prepare('SELECT company_id FROM channels WHERE id = ? AND is_active = 1');
                $chk->execute([$chId]);
                $chCo = $chk->fetchColumn();
                if (!$chCo) json_error('Selected channel not found or inactive.', 422);
                if ($chCo !== $coId) json_error('Channel belongs to a different company.', 422);
            }

            // Load the existing row to apply workflow rules
            $stmt = $pdo->prepare('SELECT * FROM posts WHERE id = ?');
            $stmt->execute([$id]);
            $existing = $stmt->fetch() ?: null;

            $approvedBy = $existing['approved_by'] ?? null;
            $approvedAt = $existing['approved_at'] ?? null;
            $wasApproved = $approvedAt !== null;

            // Auto posts must be approved before they can be queued
            // (admins may queue directly — that counts as approval).
            if ($mode === 'auto' && $status === 'scheduled' && !$wasApproved) {
                if (is_admin()) {
                    $approvedBy = auth_user()['full_name'] ?? 'admin';
                    $approvedAt = date('Y-m-d H:i:s');
                } else {
                    json_error('Auto-publish posts need approval first. Submit it for review.', 422);
                }
            }

            // A material edit to an approved/scheduled post invalidates
            // the approval and sends it back to review (admins exempt).
            if ($existing && !is_admin() && $wasApproved
                && in_array($existing['status'], ['approved', 'scheduled'], true)) {
                $changed = $existing['title'] !== $title
                    || (string)$existing['content'] !== (string)$content
                    || (string)$existing['image_url'] !== (string)$imageUrl
                    || $existing['platform'] !== $platform
                    || (string)$existing['channel_id'] !== (string)$chId;
                if ($changed) {
                    $status = 'review';
                    $approvedBy = null;
                    $approvedAt = null;
                }
            }

            // Keep publishing/failed states unless explicitly resubmitted
            if ($existing && in_array($existing['status'], ['publishing'], true)) {
                json_error('This post is being published right now — try again in a moment.', 409);
            }

            $aiGenFinal = $existing ? ($existing['ai_generated'] || $aiGen ? 1 : 0) : $aiGen;

            $stmt = $pdo->prepare("
                INSERT INTO posts
                  (id, company_id, title, content, image_url, platform, status,
                   post_date, publish_time, publish_mode, channel_id, assignee, post_url,
                   approved_by, approved_at, ai_generated)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE
                  title=VALUES(title), content=VALUES(content), image_url=VALUES(image_url),
                  platform=VALUES(platform), status=VALUES(status),
                  post_date=VALUES(post_date), publish_time=VALUES(publish_time),
                  publish_mode=VALUES(publish_mode), channel_id=VALUES(channel_id),
                  assignee=VALUES(assignee), post_url=VALUES(post_url),
                  approved_by=VALUES(approved_by), approved_at=VALUES(approved_at),
                  ai_generated=VALUES(ai_generated)
            ");
            $stmt->execute([
                $id, $coId, $title, $content, $imageUrl, $platform, $status,
                $date ?: null, $time, $mode, $chId, $assignee, $url,
                $approvedBy, $approvedAt, $aiGenFinal,
            ]);
            json_ok(['id' => $id, 'status' => $status]);
            break;

        case 'approve':
            if (!is_admin()) json_error('Forbidden — only admins can approve posts.', 403);
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
                if ($post['publish_mode'] === 'auto' && !$post['channel_id']) {
                    json_error('This auto post has no channel — assign one first.', 422);
                }
                $newStatus = 'scheduled';
            }
            $pdo->prepare("
                UPDATE posts SET status=?, approved_by=?, approved_at=NOW(), review_note=?
                WHERE id=?
            ")->execute([$newStatus, auth_user()['full_name'] ?? 'admin', $note, $id]);
            json_ok(['id' => $id, 'status' => $newStatus]);
            break;

        case 'reject':
            if (!is_admin()) json_error('Forbidden — only admins can reject posts.', 403);
            $id   = $body['id'] ?? '';
            $note = trim($body['note'] ?? '');
            if (!$id) json_error('Missing id', 422);
            if ($note === '') json_error('A rejection note is required so the author knows what to fix.', 422);
            $stmt = $pdo->prepare('SELECT status FROM posts WHERE id = ?');
            $stmt->execute([$id]);
            $st = $stmt->fetchColumn();
            if ($st === false) json_error('Post not found', 404);
            if (!in_array($st, ['draft', 'review', 'approved'], true)) {
                json_error('Only draft, review or approved posts can be rejected.', 422);
            }
            $pdo->prepare("
                UPDATE posts SET status='rejected', review_note=?, approved_by=NULL, approved_at=NULL
                WHERE id=?
            ")->execute([$note, $id]);
            json_ok(['id' => $id, 'status' => 'rejected']);
            break;

        case 'review_queue':
            // Posts awaiting review (+ recent decisions) across all
            // companies visible to the current user.
            $filter = user_company_filter();
            if ($filter !== null && empty($filter)) json_ok([]);
            $sql = "
                SELECT p.*, c.name AS company_name, c.color AS company_color,
                       ch.name AS channel_name, ch.platform AS channel_platform
                FROM posts p
                JOIN companies c ON c.id = p.company_id
                LEFT JOIN channels ch ON ch.id = p.channel_id
                WHERE p.status IN ('review','approved','rejected')
            ";
            $params = [];
            if ($filter !== null) {
                $sql .= ' AND p.company_id IN (' . implode(',', array_fill(0, count($filter), '?')) . ')';
                $params = $filter;
            }
            $sql .= "
                ORDER BY FIELD(p.status,'review','approved','rejected'), p.updated_at DESC
                LIMIT 200
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            json_ok($stmt->fetchAll());
            break;

        case 'delete':
            require_post_editor();
            $id = $body['id'] ?? '';
            if (!$id) json_error('Missing id', 422);
            $chk = $pdo->prepare('SELECT company_id FROM posts WHERE id = ?');
            $chk->execute([$id]);
            $coId = $chk->fetchColumn();
            if ($coId && !can_view_company($coId)) json_error('Forbidden', 403);
            $pdo->prepare('DELETE FROM posts WHERE id = ?')->execute([$id]);
            json_ok(['deleted' => $id]);
            break;

        default:
            json_error('Unknown action: ' . $action, 404);
    }
} catch (PDOException $e) {
    json_error('Database error: ' . $e->getMessage(), 500);
}
