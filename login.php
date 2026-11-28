<?php
// ============================================================
//  login.php — Sign in
// ============================================================

require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) { header('Location: dashboard.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? '')) {
        $error = 'Session expired — please try again.';
    } elseif (!db_connected()) {
        $error = 'Database is not connected. Run config.php first.';
    } elseif (attempt_login(trim($_POST['username'] ?? ''), $_POST['password'] ?? '')) {
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign in — <?= e(APP_NAME) ?></title>
  <style>
    :root{color-scheme:dark}
    body{margin:0;background:#0b1019;color:#eef3fb;font:14px/1.55 system-ui,sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
    .card{width:100%;max-width:380px;background:#121b29;border:1px solid #28364a;border-radius:14px;overflow:hidden;box-shadow:0 30px 80px rgba(0,0,0,.5)}
    .hd{background:linear-gradient(135deg,#7555df,#aa97ff);padding:24px;color:#fff}
    .logo{font-size:26px;font-weight:850;letter-spacing:-1.4px}
    .hd p{margin:4px 0 0;font-size:12px;opacity:.9}
    .bd{padding:24px}
    label{display:block;font-size:12px;color:#9baac0;margin:12px 0 5px}
    input{width:100%;box-sizing:border-box;border:1px solid #28364a;border-radius:8px;background:#182437;padding:10px 12px;font:inherit;color:inherit}
    button{width:100%;margin-top:18px;border:none;border-radius:8px;background:#7555df;color:#fff;padding:11px;font:inherit;font-weight:650;cursor:pointer}
    button:hover{background:#6446c8}
    .err{background:#3c2029;border:1px solid #7a3c4d;color:#ffb0b0;border-radius:8px;padding:9px 12px;font-size:13px;margin-bottom:4px}
  </style>
</head>
<body>
<div class="card">
  <div class="hd">
    <div class="logo">MaxxHits <small style="font-size:12px;font-weight:600">2.0</small></div>
    <p>AimBlue Media · Marketing Operating System</p>
  </div>
  <div class="bd">
    <?php if ($error): ?><div class="err"><?= e($error) ?></div><?php endif; ?>
    <form method="POST" autocomplete="on">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <label>Username</label>
      <input name="username" required autofocus autocomplete="username" value="<?= e($_POST['username'] ?? '') ?>">
      <label>Password</label>
      <input type="password" name="password" required autocomplete="current-password">
      <button type="submit">Sign in</button>
    </form>
  </div>
</div>
</body>
</html>
