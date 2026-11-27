<?php
// ============================================================
//  approvals.php — Approval center (Publishing)
// ============================================================

require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$PAGE = ['id' => 'approval-center', 'title' => 'Approval center', 'js' => ['approvals.js']];
include __DIR__ . '/includes/layout/header.php';
?>

<div class="page-head" id="approvalsPage" data-is-admin="<?= $mh_is_admin ? '1' : '0' ?>">
  <div>
    <small>Publishing</small>
    <h1>Approval center</h1>
    <p class="muted">Human review before anything reaches an external channel. Only admins decide.</p>
  </div>
</div>

<div class="grid">
  <div class="panel"><small>Awaiting review</small><div class="num warn" id="apReview">—</div></div>
  <div class="panel"><small>Approved</small><div class="num ok" id="apApproved">—</div></div>
  <div class="panel"><small>Rejected</small><div class="num err" id="apRejected">—</div></div>
</div>

<div class="panel">
  <div class="scroll" id="approvalsList"><span class="muted">Loading…</span></div>
</div>

<div class="mh-overlay" id="mReview">
  <div class="mh-modal">
    <div class="mh-modal-head"><span>Review post</span><button type="button" data-close>✕</button></div>
    <div class="mh-modal-body" id="reviewBody"></div>
    <div class="mh-modal-footer" style="display:block">
      <input type="hidden" id="revPostId">
      <textarea id="revNote" rows="2" placeholder="Note to the author (required when rejecting)"></textarea>
      <?php if ($mh_is_admin): ?>
      <div class="row spread" style="margin-top:12px">
        <button type="button" class="danger" id="btnReject">Reject</button>
        <div class="row">
          <button type="button" id="btnApprove">Approve</button>
          <button type="button" class="primary" id="btnApproveQueue">Approve &amp; queue</button>
        </div>
      </div>
      <?php else: ?>
      <p class="muted" style="margin:10px 0 0;font-size:12px">Only admins can approve or reject.</p>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/layout/footer.php'; ?>
