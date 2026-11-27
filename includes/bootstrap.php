<?php
// ============================================================
//  includes/bootstrap.php — MaxxHits Marketing OS bootstrap
//  Every page and API endpoint starts here.
// ============================================================

define('MH_VERSION', '2.0.0');
define('APP_NAME', 'MaxxHits');
define('APP_ROOT', dirname(__DIR__));

// All scheduling displays and cron math use Arizona time.
date_default_timezone_set('America/Phoenix');

// ── Session ────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('MAXXHITS_SESS');
    session_start();
}

// ── DB config ──────────────────────────────────────────────
// Preferred location is ONE LEVEL ABOVE the webroot; falls back
// to the app root (both gitignored, both blocked by .htaccess).
foreach ([dirname(APP_ROOT) . '/maxxhits.db.config.php', APP_ROOT . '/db.config.php'] as $__cfg) {
    if (is_file($__cfg)) { require_once $__cfg; break; }
}
unset($__cfg);

function db_connect(): ?PDO {
    static $pdo = null;
    static $tried = false;
    if ($pdo !== null) return $pdo;
    if ($tried) return null;
    $tried = true;
    if (!defined('DB_HOST')) return null;
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        return null;
    }
}

function db_connected(): bool {
    return db_connect() !== null;
}

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/modules.php';
