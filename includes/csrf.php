<?php
// ============================================================
//  includes/csrf.php — CSRF protection
//  Pages embed the token in a <meta> tag; core.js sends it
//  back on every mutating request as X-CSRF-Token.
// ============================================================

function csrf_token(): string {
    if (empty($_SESSION['mh_csrf'])) {
        $_SESSION['mh_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['mh_csrf'];
}

/** Validate a token value (forms). */
function csrf_check(?string $token): bool {
    return !empty($_SESSION['mh_csrf'])
        && is_string($token)
        && hash_equals($_SESSION['mh_csrf'], $token);
}

/** Enforce CSRF on mutating API requests; responds 403 JSON on failure. */
function csrf_require_for_api(): void {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) return;
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!csrf_check($token)) {
        json_error('Invalid or missing CSRF token. Reload the page and try again.', 403);
    }
}
