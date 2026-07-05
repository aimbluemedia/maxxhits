<?php
// ============================================================
//  api/social-logins.php — Encrypted social credentials vault
//  GET                  → list (admin sees all; CM/SP own companies)
//  GET action=reveal    → decrypt one password (access-checked)
//  POST action=save     → create/update (admin)
//  POST action=delete   → delete (admin)
// ============================================================

require_once __DIR__ . '/_init.php';

$action = $_GET['action'] ?? $body['action'] ?? 'list';

try {
    switch ($action) {

        case 'list':
            $filter = user_company_filter();
            if ($filter !== null && empty($filter)) json_ok([]);

            $sql = "
                SELECT sl.id, sl.platform, sl.title, sl.channel_url, sl.username,
                       sl.notes, sl.created_at,
                       (sl.password_enc IS NOT NULL AND sl.password_enc != '') AS has_password,
                       GROUP_CONCAT(slc.company_id) AS company_ids,
                       GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR ', ') AS company_names
                FROM social_logins sl
                LEFT JOIN social_login_companies slc ON slc.login_id = sl.id
                LEFT JOIN companies c ON c.id = slc.company_id
            ";
            $params = [];
            if ($filter !== null) {
                $sql .= ' WHERE slc.company_id IN (' . implode(',', array_fill(0, count($filter), '?')) . ')';
                $params = $filter;
            }
            $sql .= ' GROUP BY sl.id ORDER BY sl.platform, sl.title';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
            foreach ($rows as &$row) {
                $row['company_ids']  = $row['company_ids'] ? explode(',', $row['company_ids']) : [];
                $row['has_password'] = (bool)$row['has_password'];
            }
            json_ok($rows);
            break;

        case 'reveal':
            $id = $_GET['id'] ?? '';
            if (!$id) json_error('Missing id', 422);
            $stmt = $pdo->prepare('SELECT password_enc FROM social_logins WHERE id = ?');
            $stmt->execute([$id]);
            $enc = $stmt->fetchColumn();
            if ($enc === false) json_error('Not found', 404);

            if (!is_admin()) {
                $filter = user_company_filter();
                $stmt = $pdo->prepare('SELECT company_id FROM social_login_companies WHERE login_id = ?');
                $stmt->execute([$id]);
                $linked = $stmt->fetchAll(PDO::FETCH_COLUMN);
                if (empty(array_intersect($linked, $filter ?? []))) json_error('Forbidden', 403);
            }
            json_ok(['password' => decrypt_secret($enc)]);
            break;

        case 'save':
            require_admin_api();
            $id          = $body['id'] ?: null;
            $platform    = trim($body['platform'] ?? '');
            $title       = trim($body['title'] ?? '');
            $channel_url = trim($body['channel_url'] ?? '') ?: null;
            $username    = trim($body['username'] ?? '') ?: null;
            $password    = $body['password'] ?? null;
            $notes       = trim($body['notes'] ?? '') ?: null;
            $company_ids = is_array($body['company_ids'] ?? null) ? $body['company_ids'] : [];

            if (!$platform || !$title) json_error('Platform and title are required.', 422);

            $exists = false;
            if ($id) {
                $stmt = $pdo->prepare('SELECT id FROM social_logins WHERE id = ?');
                $stmt->execute([$id]);
                $exists = (bool)$stmt->fetch();
            }

            if ($exists) {
                if ($password !== null && $password !== '') {
                    $stmt = $pdo->prepare('UPDATE social_logins SET platform=?, title=?, channel_url=?, username=?, password_enc=?, notes=? WHERE id=?');
                    $stmt->execute([$platform, $title, $channel_url, $username, encrypt_secret($password), $notes, $id]);
                } else {
                    $stmt = $pdo->prepare('UPDATE social_logins SET platform=?, title=?, channel_url=?, username=?, notes=? WHERE id=?');
                    $stmt->execute([$platform, $title, $channel_url, $username, $notes, $id]);
                }
            } else {
                $id = uniqid('sl_', true);
                $stmt = $pdo->prepare('INSERT INTO social_logins (id, platform, title, channel_url, username, password_enc, notes) VALUES (?,?,?,?,?,?,?)');
                $stmt->execute([$id, $platform, $title, $channel_url, $username, $password ? encrypt_secret($password) : null, $notes]);
            }

            $pdo->prepare('DELETE FROM social_login_companies WHERE login_id = ?')->execute([$id]);
            $ins = $pdo->prepare('INSERT IGNORE INTO social_login_companies (login_id, company_id) VALUES (?,?)');
            foreach ($company_ids as $cid) {
                if ($cid) $ins->execute([$id, $cid]);
            }
            json_ok(['id' => $id]);
            break;

        case 'delete':
            require_admin_api();
            $id = $body['id'] ?? '';
            if (!$id) json_error('Missing id', 422);
            $pdo->prepare('DELETE FROM social_logins WHERE id = ?')->execute([$id]);
            json_ok(['deleted' => $id]);
            break;

        default:
            json_error('Unknown action: ' . $action, 404);
    }
} catch (PDOException $e) {
    json_error('Database error: ' . $e->getMessage(), 500);
}
