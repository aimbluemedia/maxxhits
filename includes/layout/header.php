<?php
// ============================================================
//  includes/layout/header.php — Shared page shell (top half)
//
//  Each page sets $PAGE before including this file:
//    $PAGE = [
//      'id'    => 'dashboard',          // nav highlight key
//      'title' => 'Dashboard',          // topbar + <title>
//      'js'    => ['dashboard.js'],     // page scripts (assets/js/)
//    ];
// ============================================================

if (!isset($PAGE)) { $PAGE = ['id' => '', 'title' => APP_NAME, 'js' => []]; }

$mh_user      = auth_user();
$mh_is_admin  = is_admin();
$mh_connected = db_connected();

$mh_role_badge = $mh_is_admin ? 'Admin' : (has_role('content_manager') ? 'CM' : 'SP');
$mh_role_color = $mh_is_admin ? '#6c47ff' : (has_role('content_manager') ? '#0891b2' : '#16a34a');

// Sidebar navigation. Each entry: id, href, icon, label, iconColor, adminOnly
$mh_nav = [
    ['id' => 'dashboard',        'href' => 'dashboard.php',        'icon' => 'bi-speedometer2',  'label' => 'Dashboard',        'color' => '#6c47ff', 'admin' => false],
    ['id' => 'companies',        'href' => 'companies.php',        'icon' => 'bi-building',      'label' => 'Companies',        'color' => '#f97316', 'admin' => false],
    ['id' => 'automation',       'href' => 'automation.php',       'icon' => 'bi-robot',         'label' => 'Automation',       'color' => '#e11d48', 'admin' => false],
    ['id' => 'social-logins',    'href' => 'social-logins.php',    'icon' => 'bi-key',           'label' => 'Social Logins',    'color' => '#ec4899', 'admin' => false],
    ['id' => 'content-managers', 'href' => 'content-managers.php', 'icon' => 'bi-person-badge',  'label' => 'Content Managers', 'color' => '#0891b2', 'admin' => false],
    ['id' => 'sales-people',     'href' => 'sales-people.php',     'icon' => 'bi-person-check',  'label' => 'Sales People',     'color' => '#16a34a', 'admin' => false],
    ['id' => 'users',            'href' => 'users.php',            'icon' => 'bi-people',        'label' => 'Users',            'color' => '#f59e0b', 'admin' => true],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <title><?= e($PAGE['title']) ?> — <?= e(APP_NAME) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/app.css?v=<?= e(MAXXHITS_VERSION) ?>" rel="stylesheet">
</head>
<body>
<div class="app" id="app">

  <!-- ══════════ SIDEBAR ══════════ -->
  <div class="sidebar" id="sidebar">
    <div class="sb-brand">
      <div class="logo">MX</div>
      <div class="hide-collapsed">
        <div class="brand-name"><?= e(APP_NAME) ?></div>
        <div class="brand-sub">by AimBlue Media</div>
      </div>
    </div>

    <nav class="sb-nav">
      <?php foreach ($mh_nav as $item): ?>
        <?php if ($item['admin'] && !$mh_is_admin) continue; ?>
        <a class="sb-link<?= $item['id'] === $PAGE['id'] ? ' active' : '' ?>" href="<?= e($item['href']) ?>">
          <i class="bi <?= e($item['icon']) ?>" style="color:<?= e($item['color']) ?>"></i>
          <span class="hide-collapsed"><?= e($item['label']) ?></span>
        </a>
      <?php endforeach; ?>
    </nav>

    <div class="sb-footer">
      <?php if ($mh_is_admin): ?>
      <a class="sb-link" href="config.php">
        <i class="bi bi-gear" style="color:#64748b"></i>
        <span class="hide-collapsed">Database setup</span>
      </a>
      <?php endif; ?>
      <a class="sb-link" href="logout.php">
        <i class="bi bi-box-arrow-right" style="color:#ef4444"></i>
        <span class="hide-collapsed">Logout</span>
      </a>
    </div>
  </div>

  <!-- ══════════ MAIN ══════════ -->
  <div class="main">
    <?php if (!$mh_connected): ?>
    <div class="db-warning">
      <span><i class="bi bi-exclamation-triangle me-2"></i><strong>Database not connected.</strong></span>
      <?php if ($mh_is_admin): ?><a href="config.php">Configure database</a><?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="topbar">
      <button class="tb-toggle" id="btnToggle" type="button" title="Toggle sidebar"><i class="bi bi-list"></i></button>
      <span class="tb-title" id="tbTitle"><?= e($PAGE['title']) ?></span>
      <div class="tb-user ms-auto">
        <span class="tb-user-name">
          <i class="bi bi-person-circle"></i>
          <strong><?= e($mh_user['full_name'] ?? '') ?></strong>
          <span class="badge" style="background:<?= e($mh_role_color) ?>"><?= e($mh_role_badge) ?></span>
        </span>
      </div>
      <div class="tb-actions" id="tbActions"></div>
    </div>

    <div class="content" id="content">
