<?php
// ============================================================
//  login.php — Sign in
// ============================================================

require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? '')) {
        $error = 'Session expired — please try again.';
    } elseif (!db_connected()) {
        $error = 'Database is not connected. Run config.php first.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        if (attempt_login($username, $password)) {
            header('Location: dashboard.php');
            exit;
        }
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
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    body{background:#0f172a;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;font-family:system-ui,sans-serif;}
    .card{width:100%;max-width:400px;border-radius:14px;border:none;box-shadow:0 20px 60px rgba(0,0,0,.4);overflow:hidden;}
    .card-hdr{background:linear-gradient(135deg,#6c47ff,#a78bfa);padding:26px 24px;color:#fff;display:flex;align-items:center;gap:12px;}
    .logo{width:42px;height:42px;border-radius:10px;background:rgba(255,255,255,.18);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:15px;}
    .card-hdr h1{font-size:18px;font-weight:700;margin:0;}
    .card-hdr p{font-size:12px;opacity:.85;margin:2px 0 0;}
    .card-body{padding:26px 24px;}
    .form-label{font-size:12px;font-weight:600;color:#374151;}
    .btn-mh{background:#6c47ff;border:none;color:#fff;padding:11px;font-weight:600;border-radius:8px;width:100%;}
    .btn-mh:hover{background:#5a38e8;color:#fff;}
  </style>
</head>
<body>
<div class="card">
  <div class="card-hdr">
    <div class="logo">MX</div>
    <div>
      <h1><?= e(APP_NAME) ?></h1>
      <p>Content &amp; automated posting by AimBlue Media</p>
    </div>
  </div>
  <div class="card-body">
    <?php if ($error): ?>
    <div class="alert alert-danger py-2" style="font-size:13px"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="POST" autocomplete="on">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <div class="mb-3">
        <label class="form-label">Username</label>
        <input class="form-control" name="username" required autofocus autocomplete="username"
               value="<?= e($_POST['username'] ?? '') ?>">
      </div>
      <div class="mb-4">
        <label class="form-label">Password</label>
        <input class="form-control" type="password" name="password" required autocomplete="current-password">
      </div>
      <button type="submit" class="btn btn-mh"><i class="bi bi-box-arrow-in-right me-2"></i>Sign in</button>
    </form>
  </div>
</div>
</body>
</html>
