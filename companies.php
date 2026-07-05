<?php
// ============================================================
//  companies.php — Companies overview
//  Lists all companies the user may see; admins can add,
//  edit and delete. Click through to company.php for the
//  per-company content board.
// ============================================================

require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$PAGE = [
    'id'    => 'companies',
    'title' => 'Companies',
    'js'    => ['companies.js'],
];

include __DIR__ . '/includes/layout/header.php';
?>

<div id="companiesList">
  <div class="text-muted" style="font-size:13px">Loading…</div>
</div>

<?php if ($mh_is_admin): ?>
<!-- Modal: add / edit company -->
<div class="modal fade" id="mCompany" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="mCompanyTitle"><i class="bi bi-building me-2"></i>Add company</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="coId">
        <div class="row g-3">
          <div class="col-8">
            <label class="form-label">Company name *</label>
            <input class="form-control" id="coName" maxlength="255">
          </div>
          <div class="col-4">
            <label class="form-label">Color</label>
            <input type="color" class="form-control form-control-color w-100" id="coColor" value="#6c47ff">
          </div>
          <div class="col-6">
            <label class="form-label">Content manager</label>
            <select class="form-select" id="coCM"><option value="">—</option></select>
          </div>
          <div class="col-6">
            <label class="form-label">Sales person</label>
            <select class="form-select" id="coSP"><option value="">—</option></select>
          </div>
          <div class="col-4">
            <label class="form-label">Posts / month</label>
            <input type="number" class="form-control" id="coPosts" min="0">
          </div>
          <div class="col-4">
            <label class="form-label">Monthly fee ($)</label>
            <input type="number" class="form-control" id="coFee" min="0" step="0.01">
          </div>
          <div class="col-4">
            <label class="form-label">Payment day</label>
            <input type="number" class="form-control" id="coPayDay" min="1" max="31">
          </div>
          <div class="col-4">
            <label class="form-label">SP fee %</label>
            <input type="number" class="form-control" id="coSpPct" min="0" max="100" value="40">
          </div>
          <div class="col-4">
            <label class="form-label">CM fee %</label>
            <input type="number" class="form-control" id="coCmPct" min="0" max="100" value="40">
          </div>
          <div class="col-4">
            <label class="form-label">Agency fee %</label>
            <input type="number" class="form-control" id="coSmPct" min="0" max="100" value="20">
          </div>
          <div class="col-12">
            <label class="form-label">Posting days</label>
            <div id="coDays" class="d-flex gap-2 flex-wrap">
              <!-- checkboxes injected by companies.js -->
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-sm btn-mh" id="btnSaveCompany">Save company</button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/layout/footer.php'; ?>
