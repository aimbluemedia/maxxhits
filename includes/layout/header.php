<?php
// ============================================================
//  includes/layout/header.php — Shared shell (top half)
//
//  Each page sets $PAGE before including this file:
//    $PAGE = ['id' => 'overview', 'title' => 'Overview', 'js' => ['dashboard.js']];
//  'id' is the module slug for nav highlighting.
// ============================================================

if (!isset($PAGE)) { $PAGE = ['id' => '', 'title' => APP_NAME, 'js' => []]; }

$mh_user      = auth_user();
$mh_is_admin  = is_admin();
$mh_connected = db_connected();
$mh_stopped   = $mh_connected && emergency_stopped();
$mh_groups    = mh_groups();
$mh_modules   = mh_modules();

// Brand directory for the workspace selector
$mh_brands = [];
if ($mh_connected) {
    try {
        $mh_brands = db_connect()->query('SELECT id, name FROM brands WHERE is_active = 1 ORDER BY name')->fetchAll();
    } catch (PDOException $e) { $mh_brands = []; }
}
$mh_current_brand = current_brand_id();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <title><?= e($PAGE['title']) ?> — <?= e(APP_NAME) ?></title>
  <link href="assets/css/app.css?v=<?= e(MH_VERSION) ?>" rel="stylesheet">
</head>
<body>
<aside id="sidebar">
  <div class="logo-wrap">
    <div class="logo">Maxx<span>Hits</span> <small>2.0</small></div>
    <small class="logo-sub">AIMBLUE MEDIA · MARKETING OS</small>
  </div>
  <nav id="nav">
    <?php foreach ($mh_groups as $groupName => $slugs): ?>
      <div class="eyebrow"><?= e($groupName) ?></div>
      <?php $n = 0; foreach ($slugs as $slug):
          $mod = $mh_modules[$slug] ?? null;
          if (!$mod) continue;
          if (!empty($mod['admin']) && !$mh_is_admin) continue;
          $n++;
      ?>
      <a class="nav-link<?= $slug === $PAGE['id'] ? ' active' : '' ?>"
         href="<?= e(mh_module_href($slug, $mod)) ?>"<?= $slug === $PAGE['id'] ? ' aria-current="page"' : '' ?>>
        <span class="nav-number"><?= str_pad((string)$n, 2, '0', STR_PAD_LEFT) ?> ·</span>
        <span><?= e($mod['label']) ?><?= empty($mod['page']) ? ' <i class="soon">○</i>' : '' ?></span>
      </a>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </nav>
</aside>

<main>
  <header>
    <span id="runstate" class="<?= $mh_stopped ? 'stopped' : 'ok' ?>">
      <?= $mh_stopped ? '● Emergency stop active' : '● Operational' ?>
    </span>
    <?php if (!$mh_connected): ?>
      <span class="tag warn-tag">DB not connected<?= $mh_is_admin ? ' — <a href="config.php">configure</a>' : '' ?></span>
    <?php endif; ?>
    <div class="spacer"></div>
    <select id="brandSelect" aria-label="Brand workspace">
      <option value="">All brands</option>
      <?php foreach ($mh_brands as $b): ?>
      <option value="<?= e($b['id']) ?>"<?= $b['id'] === $mh_current_brand ? ' selected' : '' ?>><?= e($b['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <?php if ($mh_is_admin): ?>
    <button id="btnStop" class="stop" type="button" data-stopped="<?= $mh_stopped ? '1' : '0' ?>">
      <?= $mh_stopped ? 'Resume operations' : 'Emergency stop' ?>
    </button>
    <?php endif; ?>
    <span class="userchip">
      <?= e($mh_user['full_name'] ?? '') ?>
      <span class="tag"><?= e($mh_user['role'] ?? '') ?></span>
    </span>
    <a class="logout" href="logout.php" title="Sign out">Logout</a>
  </header>

  <div id="content">
