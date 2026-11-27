<?php
// ============================================================
//  api/destinations.php — Publishing destinations
//  GET                     → list (workspace-filtered)
//  POST action=save        → create/update (admin)
//  POST action=delete      → delete (admin)
//  POST action=test        → verify credentials (admin)
// ============================================================

require_once __DIR__ . '/_init.php';

$action = $_GET['action'] ?? $body['action'] ?? 'list';

try {
    switch ($action) {

        case 'list':
            [$bw, $bp] = brand_where('d.brand_id');
            $stmt = $pdo->prepare("
                SELECT d.id, d.brand_id, d.platform, d.name, d.is_active,
                       d.last_test_at, d.last_test_ok, d.created_at,
                       b.name AS brand_name, b.color AS brand_color,
                       (d.config_enc IS NOT NULL) AS has_config
                FROM destinations d
                JOIN brands b ON b.id = d.brand_id
                WHERE 1=1 $bw
                ORDER BY b.name, d.platform, d.name
            ");
            $stmt->execute($bp);
            $rows = $stmt->fetchAll();
            foreach ($rows as &$r) {
                $r['is_active']  = (bool)$r['is_active'];
                $r['has_config'] = (bool)$r['has_config'];
                $r['last_test_ok'] = $r['last_test_ok'] === null ? null : (bool)$r['last_test_ok'];
                $r['has_adapter'] = !empty(mh_platforms()[$r['platform']]['adapter']);
            }
            json_ok($rows);
            break;

        case 'save':
            require_admin_api();
            $id       = $body['id'] ?: uid();
            $brandId  = $body['brand_id'] ?? '';
            $platform = $body['platform'] ?? '';
            $name     = trim($body['name'] ?? '');
            $active   = isset($body['is_active']) ? (int)(bool)$body['is_active'] : 1;
            $config   = is_array($body['config'] ?? null) ? $body['config'] : [];

            $platforms = mh_platforms();
            if (!$brandId || !$name) json_error('Brand and destination name are required.', 422);
            if (!isset($platforms[$platform])) json_error('Invalid platform.', 422);

            // Keep only this platform's fields; blank values on an
            // existing destination keep the stored secret.
            $clean = [];
            foreach ($platforms[$platform]['fields'] as $f) {
                $clean[$f[0]] = trim((string)($config[$f[0]] ?? ''));
            }
            $stmt = $pdo->prepare('SELECT config_enc, platform FROM destinations WHERE id = ?');
            $stmt->execute([$id]);
            $existing = $stmt->fetch();
            if ($existing && $existing['platform'] === $platform) {
                $old = json_decode(decrypt_secret($existing['config_enc']), true) ?: [];
                foreach ($clean as $k => $v) {
                    if ($v === '' && isset($old[$k])) $clean[$k] = $old[$k];
                }
            }
            if ($platforms[$platform]['adapter']) {
                $missing = array_keys(array_filter($clean, fn($v) => $v === ''));
                if ($missing) json_error('Missing required fields: ' . implode(', ', $missing), 422);
            }

            $pdo->prepare('
                INSERT INTO destinations (id, brand_id, platform, name, config_enc, is_active)
                VALUES (?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE
                  brand_id=VALUES(brand_id), platform=VALUES(platform), name=VALUES(name),
                  config_enc=VALUES(config_enc), is_active=VALUES(is_active)
            ')->execute([$id, $brandId, $platform, $name, $clean ? encrypt_secret(json_encode($clean)) : null, $active]);
            audit('destination_saved', 'destination', $id, $name . ' (' . $platform . ')');
            json_ok(['id' => $id]);
            break;

        case 'delete':
            require_admin_api();
            $id = $body['id'] ?? '';
            if (!$id) json_error('Missing id', 422);
            $pdo->prepare('DELETE FROM destinations WHERE id = ?')->execute([$id]);
            audit('destination_deleted', 'destination', $id);
            json_ok(['deleted' => $id]);
            break;

        case 'test':
            require_admin_api();
            $id = $body['id'] ?? '';
            if (!$id) json_error('Missing id', 422);
            $stmt = $pdo->prepare('SELECT * FROM destinations WHERE id = ?');
            $stmt->execute([$id]);
            $dest = $stmt->fetch();
            if (!$dest) json_error('Destination not found', 404);

            require_once APP_ROOT . '/includes/runner.php';
            $r = adapter_test($dest['platform'], destination_config($dest));
            $ok = !empty($r['ok']);
            $pdo->prepare('UPDATE destinations SET last_test_at=NOW(), last_test_ok=? WHERE id=?')->execute([$ok ? 1 : 0, $id]);
            audit('destination_test', 'destination', $id, $dest['name'] . ': ' . ($ok ? 'OK' : ($r['error'] ?? 'failed')));
            if ($ok) json_ok(['tested' => true]);
            json_error($r['error'] ?? 'Connection test failed.', 422);
            break;

        default:
            json_error('Unknown action: ' . $action, 404);
    }
} catch (PDOException $e) {
    json_error('Database error: ' . $e->getMessage(), 500);
}
