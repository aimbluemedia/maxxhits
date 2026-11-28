<?php
// ============================================================
//  api/users.php — Staff accounts (admin only)
// ============================================================

require_once __DIR__ . '/_init.php';
require_admin_api();

$action = $_GET['action'] ?? $body['action'] ?? 'list';

try {
    switch ($action) {

        case 'list':
            json_ok($pdo->query('
                SELECT id, username, full_name, email, role, is_active, last_login, created_at
                FROM users ORDER BY full_name
            ')->fetchAll());
            break;

        case 'save':
            $id        = $body['id'] ?: null;
            $username  = trim($body['username'] ?? '');
            $full_name = trim($body['full_name'] ?? '');
            $email     = trim($body['email'] ?? '') ?: null;
            $role      = ($body['role'] ?? 'staff') === 'admin' ? 'admin' : 'staff';
            $active    = isset($body['is_active']) ? (int)(bool)$body['is_active'] : 1;
            $password  = $body['password'] ?? '';

            if (!$username || !$full_name) json_error('Username and full name are required.', 422);
            if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) json_error('Invalid email address.', 422);

            $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id != ?');
            $stmt->execute([$username, $id ?? '']);
            if ($stmt->fetch()) json_error('That username is already taken.', 422);

            $exists = false;
            if ($id) {
                $stmt = $pdo->prepare('SELECT id FROM users WHERE id = ?');
                $stmt->execute([$id]);
                $exists = (bool)$stmt->fetch();
            }

            if ($exists) {
                if ($id === 'usr_admin' && $role !== 'admin') json_error('The main admin must stay an admin.', 422);
                if ($password !== '') {
                    if (strlen($password) < 8) json_error('Password must be at least 8 characters.', 422);
                    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
                    $pdo->prepare('UPDATE users SET username=?, full_name=?, email=?, role=?, is_active=?, password_hash=? WHERE id=?')
                        ->execute([$username, $full_name, $email, $role, $active, $hash, $id]);
                } else {
                    $pdo->prepare('UPDATE users SET username=?, full_name=?, email=?, role=?, is_active=? WHERE id=?')
                        ->execute([$username, $full_name, $email, $role, $active, $id]);
                }
            } else {
                if ($password === '') json_error('Password is required for a new user.', 422);
                if (strlen($password) < 8) json_error('Password must be at least 8 characters.', 422);
                $id = $id ?: uid();
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
                $pdo->prepare('INSERT INTO users (id, username, password_hash, role, full_name, email, is_active) VALUES (?,?,?,?,?,?,?)')
                    ->execute([$id, $username, $hash, $role, $full_name, $email, $active]);
            }
            audit('user_saved', 'user', $id, $username . ' (' . $role . ')');
            json_ok(['id' => $id]);
            break;

        case 'delete':
            $id = $body['id'] ?? '';
            if (!$id) json_error('Missing id', 422);
            if ($id === 'usr_admin') json_error('Cannot delete the main admin.', 403);
            if ($id === auth_id()) json_error('You cannot delete your own account.', 403);
            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
            audit('user_deleted', 'user', $id);
            json_ok(['deleted' => $id]);
            break;

        default:
            json_error('Unknown action: ' . $action, 404);
    }
} catch (PDOException $e) {
    json_error('Database error: ' . $e->getMessage(), 500);
}
