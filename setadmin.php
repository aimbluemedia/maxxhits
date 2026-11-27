<?php
// ============================================================
//  setadmin.php — One-time admin password setter
//  DELETE THIS FILE after first use. Works only while the admin
//  account still has the locked placeholder hash (or for a
//  logged-in admin changing their password).
// ============================================================

require_once __DIR__ . '/includes/bootstrap.php';

$pdo = db_connect();
if (!$pdo) die('Database not connected — run config.php first.');

$stmt = $pdo->prepare("SELECT id, password_hash FROM users WHERE username = 'admin' LIMIT 1");
$stmt->execute();
$admin = $stmt->fetch();
if (!$admin) die('No admin user found — import sql/maxxhits-schema.sql first.');

$locked = str_starts_with($admin['password_hash'], '!locked');
if (!$locked && !is_admin()) {
    die('Admin password is already set. Log in as admin to change it, then reload this page.');
}

$done = false;
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? '')) {
        $error = 'Session expired — please try again.';
    } else {
        $p1 = $_POST['password'] ?? '';
        $p2 = $_POST['password2'] ?? '';
        if (strlen($p1) < 8)  $error = 'Password must be at least 8 characters.';
        elseif ($p1 !== $p2)  $error = 'Passwords do not match.';
        else {
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($p1, PASSWORD_BCRYPT, ['cost' => 10]), $admin['id']]);
            $done = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e(APP_NAME) ?> — Set Admin Password</title>
  <style>
    :root{color-scheme:dark}
    body{margin:0;background:#0b1019;color:#eef3fb;font:14px/1.55 system-ui,sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
    .card{width:100%;max-width:400px;background:#121b29;border:1px solid #28364a;border-radius:14px;padding:24px}
    h1{font-size:17px;margin:0 0 8px}
    label{display:block;font-size:12px;color:#9baac0;margin:12px 0 5px}
    input{width:100%;box-sizing:border-box;border:1px solid #28364a;border-radius:8px;background:#182437;padding:10px 12px;font:inherit;color:inherit}
    button{width:100%;margin-top:18px;border:none;border-radius:8px;background:#7555df;color:#fff;padding:11px;font:inherit;font-weight:650;cursor:pointer}
    .err{background:#3c2029;border:1px solid #7a3c4d;color:#ffb0b0;border-radius:8px;padding:9px 12px;font-size:13px;margin-top:10px}
    .ok{background:#1d3b2a;border:1px solid #2f6b4a;color:#7fe09b;border-radius:8px;padding:9px 12px;font-size:13px;margin-top:10px}
    a{color:#c1b1ff}
  </style>
</head>
<body>
<div class="card">
  <h1>Set admin password</h1>
  <?php if ($done): ?>
    <div class="ok">Password set. <a href="login.php">Log in</a> as <strong>admin</strong>,
    and <strong>delete setadmin.php from the server</strong>.</div>
  <?php else: ?>
    <?php if ($error): ?><div class="err"><?= e($error) ?></div><?php endif; ?>
    <form method="POST">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <label>New password (min 8 chars)</label>
      <input type="password" name="password" required minlength="8">
      <label>Repeat password</label>
      <input type="password" name="password2" required minlength="8">
      <button type="submit">Set password</button>
    </form>
  <?php endif; ?>
</div>
</body>
</html>
