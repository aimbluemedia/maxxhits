<?php
// ============================================================
//  api/_init.php — Shared API bootstrap
//  Every endpoint starts with: require_once __DIR__.'/_init.php';
//  Provides $pdo, $method, $body; enforces auth + CSRF.
// ============================================================

require_once dirname(__DIR__) . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_login_api();
csrf_require_for_api();

$pdo = db_connect();
if (!$pdo) json_error('Database not connected. Please run config.php first.', 503);

$method = $_SERVER['REQUEST_METHOD'];
$body   = [];
if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
}

function require_admin_api(): void {
    if (!is_admin()) json_error('Forbidden — admin access required.', 403);
}

/**
 * Brand workspace filter for a query.
 * Returns [' AND <col> = ?', [brandId]] when a brand is selected,
 * or ['', []] for "All brands".
 */
function brand_where(string $col = 'brand_id'): array {
    $b = current_brand_id();
    return $b === '' ? ['', []] : [" AND $col = ?", [$b]];
}
