<?php
// ============================================================
//  includes/auth.php — Sessions, login, roles
//  Roles: admin (approve, settings, users, destinations)
//         staff (create content, run automations/tests, view)
// ============================================================

function auth_user(): ?array { return $_SESSION['mh_user'] ?? null; }
function auth_role(): ?string { return $_SESSION['mh_user']['role'] ?? null; }
function auth_id(): ?string { return $_SESSION['mh_user']['id'] ?? null; }
function is_admin(): bool { return auth_role() === 'admin'; }
function is_logged_in(): bool { return auth_user() !== null; }

function require_login(string $redirect = 'login.php'): void {
    if (!is_logged_in()) {
        header('Location: ' . $redirect);
        exit;
    }
}

function require_login_api(): void {
    if (!is_logged_in()) json_error('Not authenticated. Please log in.', 401);
}

function require_admin(string $redirect = 'dashboard.php'): void {
    if (!is_admin()) {
        header('Location: ' . $redirect);
        exit;
    }
}

function attempt_login(string $username, string $password): bool {
    $pdo = db_connect();
    if (!$pdo) return false;

    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if (!$user || !password_verify($password, $user['password_hash'])) return false;

    session_regenerate_id(true);
    $_SESSION['mh_user'] = [
        'id'        => $user['id'],
        'username'  => $user['username'],
        'full_name' => $user['full_name'],
        'role'      => $user['role'],
    ];
    $pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')->execute([$user['id']]);
    audit('login', 'user', $user['id'], $user['username'] . ' signed in');
    return true;
}

function logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    header('Location: login.php');
    exit;
}

// ── Brand workspace selector ───────────────────────────────
// '' means "all brands". Stored per session.

function current_brand_id(): string {
    return $_SESSION['mh_brand'] ?? '';
}

function set_current_brand(string $brandId): void {
    $_SESSION['mh_brand'] = $brandId;
}
