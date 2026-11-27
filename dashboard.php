<?php
// ============================================================
//  dashboard.php — Overview (Command)
// ============================================================

require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$PAGE = ['id' => 'overview', 'title' => 'Overview', 'js' => ['dashboard.js']];
include __DIR__ . '/includes/layout/header.php';
?>

<div class="page-head">
  <div>
    <small>Command</small>
    <h1>Overview</h1>
    <p class="muted">Executive view of brand operations and exceptions.</p>
  </div>
</div>

<div class="grid" id="ovTiles">
  <div class="panel"><small>In review</small><div class="num warn" id="tReview">—</div></div>
  <div class="panel"><small>Scheduled</small><div class="num" id="tScheduled">—</div></div>
  <div class="panel"><small>Published today</small><div class="num ok" id="tToday">—</div></div>
  <div class="panel"><small>Failed</small><div class="num err" id="tFailed">—</div></div>
  <div class="panel"><small>Automations enabled</small><div class="num" id="tAutos">—</div></div>
</div>

<div class="panel">
  <h2>Content pipeline</h2>
  <div class="flow" id="ovPipeline"><span class="muted">Loading…</span></div>
</div>

<div class="two">
  <div class="panel">
    <h2>Brands</h2>
    <div class="scroll" id="ovBrands"><span class="muted">Loading…</span></div>
  </div>
  <div>
    <div class="panel">
      <h2>Exceptions · failed posts</h2>
      <div id="ovFailures"><span class="muted">Loading…</span></div>
    </div>
    <div class="panel">
      <h2>Recent automation runs</h2>
      <div id="ovRuns"><span class="muted">Loading…</span></div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/layout/footer.php'; ?>
