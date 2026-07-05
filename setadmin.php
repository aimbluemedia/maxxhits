<?php
// ============================================================
//  setadmin.php — One-time admin password setter
//  DELETE THIS FILE after first use.
//  Only works while the admin account still has the locked
//  placeholder hash from the schema import (or when the person
//  using it is already logged in as admin).
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
        if (strlen($p1) < 8)   $error = 'Password must be at least 8 characters.';
        elseif ($p1 !== $p2)   $error = 'Passwords do not match.';
        else {
            $hash = password_hash($p1, PASSWORD_BCRYPT, ['cost' => 10]);
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $admin['id']]);
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
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body{background:#0f172a;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;font-family:system-ui,sans-serif;}
    .card{width:100%;max-width:420px;border-radius:14px;border:none;box-shadow:0 20px 60px rgba(0,0,0,.4);}
    .btn-mh{background:#6c47ff;border:none;color:#fff;padding:11px;font-weight:600;border-radius:8px;width:100%;}
  </style>
</head>
<body>
<div class="card">
  <div class="card-body p-4">
    <h1 style="font-size:17px;font-weight:700">Set admin password</h1>
    <?php if ($done): ?>
      <div class="alert alert-success mt-3" style="font-size:13px">
        Password set. <a href="login.php">Log in</a> as <strong>admin</strong> now,
        and <strong>delete setadmin.php from the server</strong>.
      </div>
    <?php else: ?>
      <?php if ($error): ?><div class="alert alert-danger mt-3 py-2" style="font-size:13px"><?= e($error) ?></div><?php endif; ?>
      <form method="POST" class="mt-3">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <div class="mb-3">
          <label class="form-label" style="font-size:12px;font-weight:600">New password (min 8 chars)</label>
          <input class="form-control" type="password" name="password" required minlength="8">
        </div>
        <div class="mb-4">
          <label class="form-label" style="font-size:12px;font-weight:600">Repeat password</label>
          <input class="form-control" type="password" name="password2" required minlength="8">
        </div>
        <button type="submit" class="btn btn-mh">Set password</button>
      </form>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
