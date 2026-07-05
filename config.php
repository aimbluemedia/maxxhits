<?php
// ============================================================
//  config.php — Database configuration wizard
//  Writes the DB credentials + a generated APP_KEY (used for
//  encrypting secrets). The file is written ONE LEVEL ABOVE
//  the webroot when possible; falls back to the app root.
// ============================================================

require_once __DIR__ . '/includes/bootstrap.php';

// Once configured, only a logged-in admin may change it.
if (db_connected() && !is_admin()) {
    require_login();
    header('Location: dashboard.php');
    exit;
}

$saved_to    = null;
$test_error  = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_config') {
    if (!csrf_check($_POST['csrf'] ?? '')) {
        $test_error = 'Session expired — please try again.';
    } else {
        $host = trim($_POST['db_host'] ?? '');
        $name = trim($_POST['db_name'] ?? '');
        $user = trim($_POST['db_user'] ?? '');
        $pass = $_POST['db_pass'] ?? '';

        try {
            new PDO("mysql:host={$host};dbname={$name};charset=utf8mb4", $user, $pass);
        } catch (PDOException $e) {
            $test_error = $e->getMessage();
        }

        if (!$test_error) {
            // Keep the existing APP_KEY if one exists — changing it
            // would make previously encrypted secrets unreadable.
            $appKey = defined('APP_KEY') ? APP_KEY : bin2hex(random_bytes(32));
            $cfg = "<?php\n"
                 . "define('DB_HOST', " . var_export($host, true) . ");\n"
                 . "define('DB_NAME', " . var_export($name, true) . ");\n"
                 . "define('DB_USER', " . var_export($user, true) . ");\n"
                 . "define('DB_PASS', " . var_export($pass, true) . ");\n"
                 . "define('APP_KEY', " . var_export($appKey, true) . ");\n";

            $targets = [
                dirname(APP_ROOT) . '/maxxhits.db.config.php',
                APP_ROOT . '/db.config.php',
            ];
            foreach ($targets as $t) {
                if (is_writable(dirname($t)) && @file_put_contents($t, $cfg) !== false) {
                    $saved_to = $t;
                    break;
                }
            }
            if ($saved_to) {
                header('Location: index.php');
                exit;
            }
            $test_error = 'Connection OK, but the config file could not be written. Check directory permissions.';
        }
    }
}

$connected = db_connected();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e(APP_NAME) ?> — Database Setup</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body{background:#0f172a;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;font-family:system-ui,sans-serif;}
    .card{width:100%;max-width:480px;border-radius:14px;border:none;box-shadow:0 20px 60px rgba(0,0,0,.4);overflow:hidden;}
    .card-hdr{background:#6c47ff;padding:24px;color:#fff;}
    .card-hdr h1{font-size:18px;font-weight:700;margin:0;}
    .card-hdr p{font-size:13px;opacity:.8;margin:4px 0 0;}
    .card-body{padding:24px;}
    .form-label{font-size:12px;font-weight:600;color:#374151;}
    .btn-mh{background:#6c47ff;border:none;color:#fff;padding:11px;font-weight:600;border-radius:8px;width:100%;}
    .btn-mh:hover{background:#5a38e8;color:#fff;}
  </style>
</head>
<body>
<div class="card">
  <div class="card-hdr">
    <h1><?= e(APP_NAME) ?> — Database Configuration</h1>
    <p><?= $connected ? '✓ Connected' : '⚠ Not connected' ?></p>
  </div>
  <div class="card-body">
    <?php if ($test_error): ?>
    <div class="alert alert-danger" style="font-size:13px"><?= e($test_error) ?></div>
    <?php endif; ?>
    <form method="POST">
      <input type="hidden" name="action" value="save_config">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <div class="mb-3">
        <label class="form-label">Host</label>
        <input class="form-control" name="db_host" value="<?= defined('DB_HOST') ? e(DB_HOST) : 'localhost' ?>" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Database name</label>
        <input class="form-control" name="db_name" value="<?= defined('DB_NAME') ? e(DB_NAME) : '' ?>" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Username</label>
        <input class="form-control" name="db_user" value="<?= defined('DB_USER') ? e(DB_USER) : '' ?>" required>
      </div>
      <div class="mb-4">
        <label class="form-label">Password</label>
        <input class="form-control" type="password" name="db_pass" value="">
      </div>
      <button type="submit" class="btn btn-mh">Save &amp; test connection</button>
    </form>
    <div class="form-text mt-3">
      After connecting: import <code>sql/maxxhits-schema.sql</code> via phpMyAdmin,
      then open <code>setadmin.php</code> to set the admin password.
    </div>
    <?php if ($connected): ?>
    <div class="mt-3 text-center">
      <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">Back to dashboard</a>
    </div>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
