<?php
// ============================================================
//  dashboard.php — Dashboard page
//  Revenue totals, automation snapshot, posting today,
//  company summary and team earnings.
// ============================================================

require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$PAGE = [
    'id'    => 'dashboard',
    'title' => 'Dashboard',
    'js'    => ['dashboard.js'],
];

include __DIR__ . '/includes/layout/header.php';
?>

<div class="stats" id="dashStats">
  <?php if ($mh_is_admin): ?>
  <div class="sc"><div class="sc-lbl"><i class="bi bi-cash-stack me-1"></i>Monthly revenue</div><div class="sc-val" id="dsRevenue">—</div></div>
  <?php endif; ?>
  <div class="sc"><div class="sc-lbl"><i class="bi bi-building me-1"></i>Companies</div><div class="sc-val" id="dsCompanies">—</div></div>
  <div class="sc"><div class="sc-lbl"><i class="bi bi-hourglass-split me-1"></i>In review</div><div class="sc-val" id="dsReview">—</div></div>
  <div class="sc sch"><div class="sc-lbl"><i class="bi bi-robot me-1"></i>Auto queue</div><div class="sc-val" id="dsQueued">—</div></div>
  <div class="sc pub"><div class="sc-lbl"><i class="bi bi-check-circle me-1"></i>Published today</div><div class="sc-val" id="dsPublishedToday">—</div></div>
  <div class="sc err"><div class="sc-lbl"><i class="bi bi-exclamation-circle me-1"></i>Failed posts</div><div class="sc-val" id="dsFailed">—</div></div>
</div>

<div class="mh-card">
  <h3><i class="bi bi-diagram-3"></i>Content pipeline</h3>
  <div id="dashPipeline" class="d-flex gap-2 flex-wrap" style="font-size:13px"><span class="text-muted">Loading…</span></div>
</div>

<div class="mh-card">
  <h3><i class="bi bi-calendar-check"></i>Posting today</h3>
  <div id="dashToday"><div class="text-muted" style="font-size:13px">Loading…</div></div>
</div>

<div class="mh-card">
  <h3><i class="bi bi-building"></i>Companies</h3>
  <div id="dashSummary"><div class="text-muted" style="font-size:13px">Loading…</div></div>
</div>

<?php if ($mh_is_admin): ?>
<div class="mh-card">
  <h3><i class="bi bi-cash-coin"></i>Team earnings</h3>
  <div id="dashTeam"><div class="text-muted" style="font-size:13px">Loading…</div></div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/layout/footer.php'; ?>
