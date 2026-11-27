<?php
// ============================================================
//  includes/csrf.php — CSRF protection
// ============================================================

function csrf_token(): string {
    if (empty($_SESSION['mh_csrf'])) {
        $_SESSION['mh_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['mh_csrf'];
}

function csrf_check(?string $token): bool {
    return !empty($_SESSION['mh_csrf'])
        && is_string($token)
        && hash_equals($_SESSION['mh_csrf'], $token);
}

/** Enforce CSRF on mutating API requests. */
function csrf_require_for_api(): void {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) return;
    if (!csrf_check($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        json_error('Invalid or missing CSRF token. Reload the page and try again.', 403);
    }
}
