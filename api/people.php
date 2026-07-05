<?php
// ============================================================
//  api/people.php — Content managers & sales people
//  GET  [?role=content_manager|sales_person] → list
//  POST action=save    → create/update (admin)
//  POST action=delete  → delete (admin)
// ============================================================

require_once __DIR__ . '/_init.php';

$action = $_GET['action'] ?? $body['action'] ?? 'list';

try {
    switch ($action) {

        case 'list':
            $role = $_GET['role'] ?? null;
            if ($role && !in_array($role, ['content_manager', 'sales_person'], true)) {
                json_error('Invalid role.', 422);
            }
            if ($role) {
                $stmt = $pdo->prepare('SELECT * FROM people WHERE role = ? ORDER BY name');
                $stmt->execute([$role]);
            } else {
                $stmt = $pdo->query('SELECT * FROM people ORDER BY role, name');
            }
            $people = $stmt->fetchAll();

            $links = $pdo->query('SELECT person_id, company_id FROM person_companies')->fetchAll();
            $map = [];
            foreach ($links as $l) $map[$l['person_id']][] = $l['company_id'];
            foreach ($people as &$p) $p['company_ids'] = $map[$p['id']] ?? [];

            json_ok($people);
            break;

        case 'save':
            require_admin_api();
            $id         = $body['id'] ?: uid();
            $role       = $body['role'] ?? '';
            $name       = trim($body['name'] ?? '');
            $email      = trim($body['email'] ?? '') ?: null;
            $notes      = trim($body['notes'] ?? '') ?: null;
            $companyIds = is_array($body['company_ids'] ?? null) ? $body['company_ids'] : [];

            if (!in_array($role, ['content_manager', 'sales_person'], true)) json_error('Invalid role.', 422);
            if (!$name) json_error('Name is required.', 422);
            if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) json_error('Invalid email address.', 422);

            $stmt = $pdo->prepare("
                INSERT INTO people (id, role, name, email, notes)
                VALUES (?,?,?,?,?)
                ON DUPLICATE KEY UPDATE
                  role=VALUES(role), name=VALUES(name),
                  email=VALUES(email), notes=VALUES(notes)
            ");
            $stmt->execute([$id, $role, $name, $email, $notes]);

            $pdo->prepare('DELETE FROM person_companies WHERE person_id = ?')->execute([$id]);
            $ins = $pdo->prepare('INSERT IGNORE INTO person_companies (person_id, company_id) VALUES (?,?)');
            foreach ($companyIds as $cid) {
                if ($cid) $ins->execute([$id, $cid]);
            }

            json_ok(['id' => $id]);
            break;

        case 'delete':
            require_admin_api();
            $id = $body['id'] ?? '';
            if (!$id) json_error('Missing id', 422);
            $pdo->prepare('DELETE FROM people WHERE id = ?')->execute([$id]);
            json_ok(['deleted' => $id]);
            break;

        default:
            json_error('Unknown action: ' . $action, 404);
    }
} catch (PDOException $e) {
    json_error('Database error: ' . $e->getMessage(), 500);
}
