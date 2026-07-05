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
$STATUSES  = ['draft','scheduled','publishing','published','failed'];

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
            $status   = $body['status'] ?? 'scheduled';
            $date     = $body['post_date'] ?? null;
            $time     = trim($body['publish_time'] ?? '') ?: null;
            $mode     = $body['publish_mode'] ?? 'manual';
            $chId     = $body['channel_id'] ?: null;
            $assignee = trim($body['assignee'] ?? '') ?: null;
            $url      = trim($body['post_url'] ?? '') ?: null;

            if (!$coId || !$title || !$platform) json_error('company_id, title and platform are required.', 422);
            if (!can_view_company($coId)) json_error('Forbidden', 403);
            if (!in_array($platform, $PLATFORMS, true)) json_error('Invalid platform.', 422);
            if (!in_array($status, $STATUSES, true)) json_error('Invalid status.', 422);
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

            $stmt = $pdo->prepare("
                INSERT INTO posts
                  (id, company_id, title, content, image_url, platform, status,
                   post_date, publish_time, publish_mode, channel_id, assignee, post_url)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE
                  title=VALUES(title), content=VALUES(content), image_url=VALUES(image_url),
                  platform=VALUES(platform), status=VALUES(status),
                  post_date=VALUES(post_date), publish_time=VALUES(publish_time),
                  publish_mode=VALUES(publish_mode), channel_id=VALUES(channel_id),
                  assignee=VALUES(assignee), post_url=VALUES(post_url)
            ");
            $stmt->execute([
                $id, $coId, $title, $content, $imageUrl, $platform, $status,
                $date ?: null, $time, $mode, $chId, $assignee, $url,
            ]);
            json_ok(['id' => $id]);
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
