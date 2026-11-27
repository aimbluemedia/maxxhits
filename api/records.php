<?php
// ============================================================
//  api/records.php — Placeholder module workspaces
//  Simple per-module records so unbuilt modules can already
//  collect work items (mirrors the prototype's generic view).
//  GET  ?module=slug      → list
//  POST action=save       → add record
//  POST action=delete     → remove record
// ============================================================

require_once __DIR__ . '/_init.php';

$action = $_GET['action'] ?? $body['action'] ?? 'list';

function valid_module_slug(string $m): bool {
    $mods = mh_modules();
    return isset($mods[$m]) && empty($mods[$m]['page']);
}

try {
    switch ($action) {

        case 'list':
            $module = $_GET['module'] ?? '';
            if (!valid_module_slug($module)) json_error('Invalid module.', 422);
            $brand = current_brand_id();
            $sql = "
                SELECT r.*, b.name AS brand_name, b.color AS brand_color
                FROM module_records r
                LEFT JOIN brands b ON b.id = r.brand_id
                WHERE r.module = ?
            ";
            $params = [$module];
            if ($brand !== '') {
                $sql .= ' AND (r.brand_id = ? OR r.brand_id IS NULL)';
                $params[] = $brand;
            }
            $sql .= ' ORDER BY r.created_at DESC LIMIT 100';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            json_ok($stmt->fetchAll());
            break;

        case 'save':
            $module = $body['module'] ?? '';
            $title  = trim($body['title'] ?? '');
            $notes  = trim($body['notes'] ?? '') ?: null;
            if (!valid_module_slug($module)) json_error('Invalid module.', 422);
            if (!$title) json_error('Title is required.', 422);
            $brandId = current_brand_id() ?: null;
            $id = uid();
            $pdo->prepare('INSERT INTO module_records (id, module, brand_id, title, notes, created_by) VALUES (?,?,?,?,?,?)')
                ->execute([$id, $module, $brandId, $title, $notes, auth_user()['username'] ?? null]);
            json_ok(['id' => $id]);
            break;

        case 'delete':
            $id = $body['id'] ?? '';
            if (!$id) json_error('Missing id', 422);
            $pdo->prepare('DELETE FROM module_records WHERE id = ?')->execute([$id]);
            json_ok(['deleted' => $id]);
            break;

        default:
            json_error('Unknown action: ' . $action, 404);
    }
} catch (PDOException $e) {
    json_error('Database error: ' . $e->getMessage(), 500);
}
