<?php
// ============================================================
//  queue.php — Publishing queue (Publishing)
// ============================================================

require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$PAGE = ['id' => 'publishing-queue', 'title' => 'Publishing queue', 'js' => ['queue.js']];
include __DIR__ . '/includes/layout/header.php';
?>

<div class="page-head" id="queuePage" data-is-admin="<?= $mh_is_admin ? '1' : '0' ?>">
  <div>
    <small>Publishing</small>
    <h1>Publishing queue</h1>
    <p class="muted">Approved, scheduled and delivered posts. The scheduler publishes due auto posts every run.</p>
  </div>
  <?php if ($mh_is_admin): ?><button id="btnRunDue" type="button">Run scheduler now</button><?php endif; ?>
</div>

<div class="panel">
  <div class="scroll" id="queueList"><span class="muted">Loading…</span></div>
</div>

<?php include __DIR__ . '/includes/layout/footer.php'; ?>
