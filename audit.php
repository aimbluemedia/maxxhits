<?php
// ============================================================
//  audit.php — Audit log (Administration)
// ============================================================

require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$PAGE = ['id' => 'audit-log', 'title' => 'Audit log', 'js' => ['audit.js']];
include __DIR__ . '/includes/layout/header.php';
?>

<div class="page-head">
  <div>
    <small>Administration</small>
    <h1>Audit log</h1>
    <p class="muted">Append-only history of staff, scheduler and publishing actions (latest 200).</p>
  </div>
</div>

<div class="panel">
  <div class="scroll" id="auditList"><span class="muted">Loading…</span></div>
</div>

<?php include __DIR__ . '/includes/layout/footer.php'; ?>
