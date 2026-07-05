<?php
// ============================================================
//  includes/auth.php — Sessions, login, roles
// ============================================================

function auth_user(): ?array { return $_SESSION['mh_user'] ?? null; }
function auth_role(): ?string { return $_SESSION['mh_user']['role'] ?? null; }
function auth_id(): ?string { return $_SESSION['mh_user']['id'] ?? null; }
function is_admin(): bool { return has_role('admin'); }
function is_logged_in(): bool { return auth_user() !== null; }

/** users.role stores comma-separated roles (dual-role support). */
function has_role(string $role): bool {
    $r = auth_role();
    if (!$r) return false;
    return in_array($role, explode(',', $r), true);
}

/** Redirect to login (pages). */
function require_login(string $redirect = 'login.php'): void {
    if (!is_logged_in()) {
        header('Location: ' . $redirect);
        exit;
    }
}

/** 401 JSON instead of redirect (API endpoints). */
function require_login_api(): void {
    if (!is_logged_in()) {
        json_error('Not authenticated. Please log in.', 401);
    }
}

/** Page guard: admin only. */
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

    // Collect company IDs linked through both role person records
    $companyIds = [];
    foreach (['cm_person_id', 'sp_person_id'] as $col) {
        if (!empty($user[$col])) {
            $s = $pdo->prepare('SELECT company_id FROM person_companies WHERE person_id = ?');
            $s->execute([$user[$col]]);
            foreach ($s->fetchAll(PDO::FETCH_COLUMN) as $cid) {
                if (!in_array($cid, $companyIds, true)) $companyIds[] = $cid;
            }
        }
    }

    session_regenerate_id(true);
    $_SESSION['mh_user'] = [
        'id'           => $user['id'],
        'username'     => $user['username'],
        'full_name'    => $user['full_name'],
        'email'        => $user['email'],
        'role'         => $user['role'],
        'cm_person_id' => $user['cm_person_id'],
        'sp_person_id' => $user['sp_person_id'],
        'company_ids'  => $companyIds,
    ];

    $pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')->execute([$user['id']]);
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

/**
 * Company visibility filter.
 * Returns null for admin (no filter) or an array of company IDs
 * (possibly empty) the current user may see.
 */
function user_company_filter(): ?array {
    if (is_admin()) return null;
    return $_SESSION['mh_user']['company_ids'] ?? [];
}

/** True if current user may see the given company. */
function can_view_company(string $companyId): bool {
    $filter = user_company_filter();
    return $filter === null || in_array($companyId, $filter, true);
}
