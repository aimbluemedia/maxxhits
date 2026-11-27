<?php
// ============================================================
//  config.php — Database configuration wizard
//  Writes DB credentials + generated APP_KEY above the webroot
//  when writable (../maxxhits.db.config.php), else ./db.config.php.
// ============================================================

require_once __DIR__ . '/includes/bootstrap.php';

if (db_connected() && !is_admin()) {
    require_login();
    header('Location: dashboard.php');
    exit;
}

$test_error = null;
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
            $appKey = defined('APP_KEY') ? APP_KEY : bin2hex(random_bytes(32));
            $cfg = "<?php\n"
                 . "define('DB_HOST', " . var_export($host, true) . ");\n"
                 . "define('DB_NAME', " . var_export($name, true) . ");\n"
                 . "define('DB_USER', " . var_export($user, true) . ");\n"
                 . "define('DB_PASS', " . var_export($pass, true) . ");\n"
                 . "define('APP_KEY', " . var_export($appKey, true) . ");\n";
            $saved = false;
            foreach ([dirname(APP_ROOT) . '/maxxhits.db.config.php', APP_ROOT . '/db.config.php'] as $t) {
                if (is_writable(dirname($t)) && @file_put_contents($t, $cfg) !== false) { $saved = true; break; }
            }
            if ($saved) { header('Location: index.php'); exit; }
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
  <style>
    :root{color-scheme:dark}
    body{margin:0;background:#0b1019;color:#eef3fb;font:14px/1.55 system-ui,sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
    .card{width:100%;max-width:460px;background:#121b29;border:1px solid #28364a;border-radius:14px;overflow:hidden}
    .hd{background:#7555df;padding:22px;color:#fff}
    .hd h1{margin:0;font-size:17px}.hd p{margin:4px 0 0;font-size:13px;opacity:.85}
    .bd{padding:22px}
    label{display:block;font-size:12px;color:#9baac0;margin:12px 0 5px}
    input{width:100%;box-sizing:border-box;border:1px solid #28364a;border-radius:8px;background:#182437;padding:10px 12px;font:inherit;color:inherit}
    button{width:100%;margin-top:18px;border:none;border-radius:8px;background:#7555df;color:#fff;padding:11px;font:inherit;font-weight:650;cursor:pointer}
    .err{background:#3c2029;border:1px solid #7a3c4d;color:#ffb0b0;border-radius:8px;padding:9px 12px;font-size:13px}
    .hint{font-size:12px;color:#9baac0;margin-top:14px}
    a{color:#c1b1ff}
    code{color:#b7ccff}
  </style>
</head>
<body>
<div class="card">
  <div class="hd">
    <h1><?= e(APP_NAME) ?> — Database Configuration</h1>
    <p><?= $connected ? '✓ Connected' : '⚠ Not connected' ?></p>
  </div>
  <div class="bd">
    <?php if ($test_error): ?><div class="err"><?= e($test_error) ?></div><?php endif; ?>
    <form method="POST">
      <input type="hidden" name="action" value="save_config">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <label>Host</label>
      <input name="db_host" value="<?= defined('DB_HOST') ? e(DB_HOST) : 'localhost' ?>" required>
      <label>Database name</label>
      <input name="db_name" value="<?= defined('DB_NAME') ? e(DB_NAME) : '' ?>" required>
      <label>Username</label>
      <input name="db_user" value="<?= defined('DB_USER') ? e(DB_USER) : '' ?>" required>
      <label>Password</label>
      <input type="password" name="db_pass" value="">
      <button type="submit">Save &amp; test connection</button>
    </form>
    <div class="hint">
      After connecting: import <code>sql/maxxhits-schema.sql</code> via phpMyAdmin,
      then open <code>setadmin.php</code> to set the admin password.
      <?php if ($connected): ?><br><a href="dashboard.php">Back to Overview</a><?php endif; ?>
    </div>
  </div>
</div>
</body>
</html>
