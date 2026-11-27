<?php
// ============================================================
//  api/brands.php — Brand directory
//  GET                → list brands (active + archived)
//  POST action=save   → create/update (admin)
//  POST action=delete → delete/archive (admin)
// ============================================================

require_once __DIR__ . '/_init.php';

$action = $_GET['action'] ?? $body['action'] ?? 'list';

try {
    switch ($action) {

        case 'list':
            $rows = $pdo->query('
                SELECT b.*,
                       (SELECT COUNT(*) FROM posts p WHERE p.brand_id = b.id)        AS post_count,
                       (SELECT COUNT(*) FROM destinations d WHERE d.brand_id = b.id) AS destination_count
                FROM brands b ORDER BY b.is_active DESC, b.name
            ')->fetchAll();
            foreach ($rows as &$r) {
                $r['is_active'] = (bool)$r['is_active'];
                $r['post_count'] = (int)$r['post_count'];
                $r['destination_count'] = (int)$r['destination_count'];
            }
            json_ok($rows);
            break;

        case 'save':
            require_admin_api();
            $id    = $body['id'] ?: uid();
            $name  = trim($body['name'] ?? '');
            $color = $body['color'] ?? '#7555df';
            $url   = trim($body['website_url'] ?? '') ?: null;
            $voice = trim($body['brand_voice'] ?? '') ?: null;
            $active = isset($body['is_active']) ? (int)(bool)$body['is_active'] : 1;

            if (!$name) json_error('Brand name is required.', 422);
            if (!valid_hex_color($color)) $color = '#7555df';
            if ($url !== null && !preg_match('~^https?://~i', $url)) json_error('Website URL must start with http:// or https://', 422);

            $dupe = $pdo->prepare('SELECT id FROM brands WHERE name = ? AND id != ?');
            $dupe->execute([$name, $id]);
            if ($dupe->fetch()) json_error('A brand with that name already exists.', 422);

            $pdo->prepare('
                INSERT INTO brands (id, name, color, website_url, brand_voice, is_active)
                VALUES (?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE
                  name=VALUES(name), color=VALUES(color), website_url=VALUES(website_url),
                  brand_voice=VALUES(brand_voice), is_active=VALUES(is_active)
            ')->execute([$id, $name, $color, $url, $voice, $active]);
            audit('brand_saved', 'brand', $id, $name);
            json_ok(['id' => $id]);
            break;

        case 'delete':
            require_admin_api();
            $id = $body['id'] ?? '';
            if (!$id) json_error('Missing id', 422);

            // Brands with history archive instead of hard-deleting.
            $chk = $pdo->prepare('SELECT (SELECT COUNT(*) FROM posts WHERE brand_id=?) + (SELECT COUNT(*) FROM automations WHERE brand_id=?)');
            $chk->execute([$id, $id]);
            if ((int)$chk->fetchColumn() > 0) {
                $pdo->prepare('UPDATE brands SET is_active = 0 WHERE id = ?')->execute([$id]);
                audit('brand_archived', 'brand', $id);
                json_ok(['archived' => $id]);
            }
            $pdo->prepare('DELETE FROM brands WHERE id = ?')->execute([$id]);
            audit('brand_deleted', 'brand', $id);
            json_ok(['deleted' => $id]);
            break;

        default:
            json_error('Unknown action: ' . $action, 404);
    }
} catch (PDOException $e) {
    json_error('Database error: ' . $e->getMessage(), 500);
}
