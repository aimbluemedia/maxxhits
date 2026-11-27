<?php
// ============================================================
//  approvals.php — Approval Center
//  Review queue for drafts submitted by content managers and
//  AI-generated posts. Admins approve/reject; approving can
//  also queue the post for auto-publishing.
// ============================================================

require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$PAGE = [
    'id'    => 'approvals',
    'title' => 'Approval Center',
    'js'    => ['approvals.js'],
];

include __DIR__ . '/includes/layout/header.php';
?>

<div id="approvalsPage" data-is-admin="<?= $mh_is_admin ? '1' : '0' ?>">
  <div class="stats">
    <div class="sc sch"><div class="sc-lbl"><i class="bi bi-hourglass-split me-1"></i>Awaiting review</div><div class="sc-val" id="apReview">—</div></div>
    <div class="sc pub"><div class="sc-lbl"><i class="bi bi-check-circle me-1"></i>Approved</div><div class="sc-val" id="apApproved">—</div></div>
    <div class="sc err"><div class="sc-lbl"><i class="bi bi-x-circle me-1"></i>Rejected</div><div class="sc-val" id="apRejected">—</div></div>
  </div>

  <div id="approvalsList">
    <div class="text-muted" style="font-size:13px">Loading…</div>
  </div>
</div>

<!-- Modal: review a post -->
<div class="modal fade" id="mReview" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-clipboard-check me-2"></i>Review post</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="reviewBody"><!-- filled by approvals.js --></div>
      <div class="modal-footer flex-wrap">
        <input type="hidden" id="revPostId">
        <textarea class="form-control mb-2" id="revNote" rows="2" placeholder="Note to the author (required when rejecting)"></textarea>
        <?php if ($mh_is_admin): ?>
        <button type="button" class="btn btn-sm btn-outline-danger" id="btnReject"><i class="bi bi-x-lg me-1"></i>Reject</button>
        <div class="ms-auto d-flex gap-2">
          <button type="button" class="btn btn-sm btn-outline-success" id="btnApprove"><i class="bi bi-check-lg me-1"></i>Approve</button>
          <button type="button" class="btn btn-sm btn-mh" id="btnApproveQueue"><i class="bi bi-send me-1"></i>Approve &amp; queue</button>
        </div>
        <?php else: ?>
        <span class="text-muted" style="font-size:12px">Only admins can approve or reject.</span>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/layout/footer.php'; ?>
