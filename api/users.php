<?php
// ============================================================
//  api/users.php — User accounts (admin only)
//  GET               → list users
//  POST action=save  → create/update
//  POST action=delete→ delete
// ============================================================

require_once __DIR__ . '/_init.php';
require_admin_api();

$action = $_GET['action'] ?? $body['action'] ?? 'list';

try {
    switch ($action) {

        case 'list':
            $rows = $pdo->query("
                SELECT u.id, u.username, u.full_name, u.email, u.role,
                       u.cm_person_id, u.sp_person_id, u.is_active, u.last_login, u.created_at,
                       cm.name AS cm_person_name,
                       sp.name AS sp_person_name
                FROM users u
                LEFT JOIN people cm ON cm.id = u.cm_person_id
                LEFT JOIN people sp ON sp.id = u.sp_person_id
                ORDER BY u.full_name
            ")->fetchAll();
            json_ok($rows);
            break;

        case 'save':
            $id           = $body['id'] ?: null;
            $username     = trim($body['username'] ?? '');
            $full_name    = trim($body['full_name'] ?? '');
            $email        = trim($body['email'] ?? '') ?: null;
            $is_active    = isset($body['is_active']) ? (int)(bool)$body['is_active'] : 1;
            $password     = $body['password'] ?? '';
            $cm_person_id = $body['cm_person_id'] ?: null;
            $sp_person_id = $body['sp_person_id'] ?: null;

            // Build role string from selections (dual-role support)
            $roles = [];
            if (!empty($body['is_admin'])) {
                $roles[] = 'admin';
            } else {
                if ($cm_person_id) $roles[] = 'content_manager';
                if ($sp_person_id) $roles[] = 'sales_person';
                if (empty($roles)) $roles[] = 'content_manager';
            }
            $role = implode(',', $roles);

            if (!$username || !$full_name) json_error('Username and full name are required.', 422);
            if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) json_error('Invalid email address.', 422);

            $exists = false;
            if ($id) {
                $stmt = $pdo->prepare('SELECT id FROM users WHERE id = ?');
                $stmt->execute([$id]);
                $exists = (bool)$stmt->fetch();
            }

            // Username must stay unique
            $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id != ?');
            $stmt->execute([$username, $id ?? '']);
            if ($stmt->fetch()) json_error('That username is already taken.', 422);

            if ($exists) {
                if ($password !== '') {
                    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
                    $stmt = $pdo->prepare('UPDATE users SET username=?, full_name=?, email=?, role=?, cm_person_id=?, sp_person_id=?, is_active=?, password_hash=? WHERE id=?');
                    $stmt->execute([$username, $full_name, $email, $role, $cm_person_id, $sp_person_id, $is_active, $hash, $id]);
                } else {
                    $stmt = $pdo->prepare('UPDATE users SET username=?, full_name=?, email=?, role=?, cm_person_id=?, sp_person_id=?, is_active=? WHERE id=?');
                    $stmt->execute([$username, $full_name, $email, $role, $cm_person_id, $sp_person_id, $is_active, $id]);
                }
            } else {
                if ($password === '') json_error('Password is required for a new user.', 422);
                if (strlen($password) < 8) json_error('Password must be at least 8 characters.', 422);
                if (!$id) $id = uniqid('usr_', true);
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
                $stmt = $pdo->prepare('INSERT INTO users (id, username, password_hash, role, cm_person_id, sp_person_id, full_name, email, is_active) VALUES (?,?,?,?,?,?,?,?,?)');
                $stmt->execute([$id, $username, $hash, $role, $cm_person_id, $sp_person_id, $full_name, $email, $is_active]);
            }
            json_ok(['id' => $id]);
            break;

        case 'delete':
            $id = $body['id'] ?? '';
            if (!$id) json_error('Missing id', 422);
            if ($id === 'usr_admin') json_error('Cannot delete the main admin.', 403);
            if ($id === auth_id()) json_error('You cannot delete your own account.', 403);
            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
            json_ok(['deleted' => $id]);
            break;

        default:
            json_error('Unknown action: ' . $action, 404);
    }
} catch (PDOException $e) {
    json_error('Database error: ' . $e->getMessage(), 500);
}
