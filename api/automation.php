<?php
// ============================================================
//  api/automation.php — Channels, queue, logs, publish-now
//
//  GET  action=channels       → list channels (filtered by role)
//  GET  action=queue          → auto-publish queue + recent results
//  GET  action=logs           → recent automation logs
//  GET  action=cron_info      → cron URL + key (admin)
//  POST action=save_channel   → create/update channel (admin)
//  POST action=delete_channel → delete channel (admin)
//  POST action=test_channel   → verify credentials (admin)
//  POST action=publish_now    → publish one post immediately (admin/CM)
//  POST action=run_due        → publish everything due (admin)
// ============================================================

require_once __DIR__ . '/_init.php';
require_once dirname(__DIR__) . '/includes/publish-runner.php';

$action = $_GET['action'] ?? $body['action'] ?? '';

/** Fields that make up each platform's channel config. */
const CHANNEL_CONFIG_FIELDS = [
    'wordpress' => ['site_url', 'username', 'app_password'],
    'facebook'  => ['page_id', 'access_token'],
    'instagram' => ['ig_user_id', 'access_token'],
    'twitter'   => ['access_token'],
    'linkedin'  => ['author_urn', 'access_token'],
];

try {
    switch ($action) {

        case 'channels':
            $filter = user_company_filter();
            if ($filter !== null && empty($filter)) json_ok([]);

            $sql = "
                SELECT ch.id, ch.company_id, ch.platform, ch.name, ch.is_active,
                       ch.last_test_at, ch.last_test_ok, ch.created_at,
                       c.name AS company_name, c.color AS company_color,
                       (ch.config_enc IS NOT NULL) AS has_config
                FROM channels ch
                JOIN companies c ON c.id = ch.company_id
            ";
            $params = [];
            if ($filter !== null) {
                $sql .= ' WHERE ch.company_id IN (' . implode(',', array_fill(0, count($filter), '?')) . ')';
                $params = $filter;
            }
            $sql .= ' ORDER BY c.name, ch.platform, ch.name';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
            foreach ($rows as &$r) {
                $r['is_active']  = (bool)$r['is_active'];
                $r['has_config'] = (bool)$r['has_config'];
                $r['last_test_ok'] = $r['last_test_ok'] === null ? null : (bool)$r['last_test_ok'];
            }
            json_ok($rows);
            break;

        case 'queue':
            $filter = user_company_filter();
            if ($filter !== null && empty($filter)) json_ok([]);

            $sql = "
                SELECT p.id, p.title, p.platform, p.status, p.post_date, p.publish_time,
                       p.publish_mode, p.published_at, p.post_url, p.publish_error,
                       p.publish_attempts, p.image_url,
                       c.name AS company_name, c.color AS company_color, c.id AS company_id,
                       ch.name AS channel_name, ch.platform AS channel_platform
                FROM posts p
                JOIN companies c ON c.id = p.company_id
                LEFT JOIN channels ch ON ch.id = p.channel_id
                WHERE p.publish_mode = 'auto'
            ";
            $params = [];
            if ($filter !== null) {
                $sql .= ' AND p.company_id IN (' . implode(',', array_fill(0, count($filter), '?')) . ')';
                $params = $filter;
            }
            $sql .= "
                ORDER BY FIELD(p.status,'failed','publishing','scheduled','published','draft'),
                         p.post_date IS NULL, p.post_date, p.publish_time
                LIMIT 200
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            json_ok($stmt->fetchAll());
            break;

        case 'logs':
            $filter = user_company_filter();
            if ($filter !== null && empty($filter)) json_ok([]);

            $sql = "
                SELECT l.id, l.post_id, l.channel_id, l.level, l.message, l.created_at,
                       p.title AS post_title, c.name AS company_name
                FROM automation_logs l
                LEFT JOIN posts p ON p.id = l.post_id
                LEFT JOIN companies c ON c.id = p.company_id
            ";
            $params = [];
            if ($filter !== null) {
                $sql .= ' WHERE p.company_id IN (' . implode(',', array_fill(0, count($filter), '?')) . ')';
                $params = $filter;
            }
            $sql .= ' ORDER BY l.id DESC LIMIT 100';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            json_ok($stmt->fetchAll());
            break;

        case 'cron_info':
            require_admin_api();
            $key = get_setting('cron_key');
            if (!$key) {
                $key = bin2hex(random_bytes(24));
                set_setting('cron_key', $key);
            }
            $scheme = !empty($_SERVER['HTTPS']) ? 'https' : 'http';
            $host   = $_SERVER['HTTP_HOST'] ?? 'your-domain.com';
            $base   = dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
            $base   = rtrim($base === '/' || $base === '\\' ? '' : $base, '/');
            json_ok([
                'cron_key' => $key,
                'cron_url' => "$scheme://$host$base/cron/publish-scheduler.php?key=$key",
            ]);
            break;

        case 'save_channel':
            require_admin_api();
            $id        = $body['id'] ?: uid();
            $companyId = $body['company_id'] ?? '';
            $platform  = $body['platform'] ?? '';
            $name      = trim($body['name'] ?? '');
            $isActive  = isset($body['is_active']) ? (int)(bool)$body['is_active'] : 1;
            $config    = is_array($body['config'] ?? null) ? $body['config'] : [];

            if (!$companyId || !$name) json_error('Company and channel name are required.', 422);
            if (!isset(CHANNEL_CONFIG_FIELDS[$platform])) json_error('Invalid platform.', 422);

            // Keep only this platform's fields; blank values on an
            // existing channel keep the stored secret.
            $clean = [];
            foreach (CHANNEL_CONFIG_FIELDS[$platform] as $f) {
                $clean[$f] = trim((string)($config[$f] ?? ''));
            }

            $stmt = $pdo->prepare('SELECT config_enc, platform FROM channels WHERE id = ?');
            $stmt->execute([$id]);
            $existing = $stmt->fetch();
            if ($existing && $existing['platform'] === $platform) {
                $old = json_decode(decrypt_secret($existing['config_enc']), true) ?: [];
                foreach ($clean as $f => $v) {
                    if ($v === '' && isset($old[$f])) $clean[$f] = $old[$f];
                }
            }

            $missing = array_keys(array_filter($clean, fn($v) => $v === ''));
            if ($missing) json_error('Missing required fields: ' . implode(', ', $missing), 422);

            $enc = encrypt_secret(json_encode($clean));
            $stmt = $pdo->prepare("
                INSERT INTO channels (id, company_id, platform, name, config_enc, is_active)
                VALUES (?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE
                  company_id=VALUES(company_id), platform=VALUES(platform),
                  name=VALUES(name), config_enc=VALUES(config_enc), is_active=VALUES(is_active)
            ");
            $stmt->execute([$id, $companyId, $platform, $name, $enc, $isActive]);
            json_ok(['id' => $id]);
            break;

        case 'delete_channel':
            require_admin_api();
            $id = $body['id'] ?? '';
            if (!$id) json_error('Missing id', 422);
            $pdo->prepare('DELETE FROM channels WHERE id = ?')->execute([$id]);
            json_ok(['deleted' => $id]);
            break;

        case 'test_channel':
            require_admin_api();
            $id = $body['id'] ?? '';
            if (!$id) json_error('Missing id', 422);
            $stmt = $pdo->prepare('SELECT * FROM channels WHERE id = ?');
            $stmt->execute([$id]);
            $channel = $stmt->fetch();
            if (!$channel) json_error('Channel not found', 404);

            $result = publisher_test($channel['platform'], channel_config($channel));
            $ok = !empty($result['ok']);
            $pdo->prepare('UPDATE channels SET last_test_at = NOW(), last_test_ok = ? WHERE id = ?')
                ->execute([$ok ? 1 : 0, $id]);
            automation_log($pdo, null, $id,
                $ok ? 'success' : 'error',
                'Channel test for "' . $channel['name'] . '": ' . ($ok ? 'OK' : ($result['error'] ?? 'failed')));

            if ($ok) json_ok(['tested' => true]);
            json_error($result['error'] ?? 'Channel test failed.', 422);
            break;

        case 'publish_now':
            if (!is_admin() && !has_role('content_manager')) json_error('Forbidden', 403);
            $postId = $body['post_id'] ?? '';
            if (!$postId) json_error('Missing post_id', 422);

            $stmt = $pdo->prepare('SELECT * FROM posts WHERE id = ?');
            $stmt->execute([$postId]);
            $post = $stmt->fetch();
            if (!$post) json_error('Post not found', 404);
            if (!can_view_company($post['company_id'])) json_error('Forbidden', 403);

            $result = run_publish_post($pdo, $post);
            if ($result['ok']) json_ok(['published' => true, 'url' => $result['url']]);
            json_error($result['error'] ?? 'Publishing failed.', 422);
            break;

        case 'run_due':
            require_admin_api();
            $results = run_due_posts($pdo);
            json_ok(['processed' => count($results), 'results' => $results]);
            break;

        default:
            json_error('Unknown action: ' . $action, 404);
    }
} catch (PDOException $e) {
    json_error('Database error: ' . $e->getMessage(), 500);
}
