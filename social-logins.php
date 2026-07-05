<?php
// ============================================================
//  social-logins.php — Encrypted social credentials vault
//  Everyone sees their companies' logins; only admins edit.
// ============================================================

require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$PAGE = [
    'id'    => 'social-logins',
    'title' => 'Social Logins',
    'js'    => ['social-logins.js'],
];

include __DIR__ . '/includes/layout/header.php';
?>

<div id="loginsList">
  <div class="text-muted" style="font-size:13px">Loading…</div>
</div>

<?php if ($mh_is_admin): ?>
<!-- Modal: add / edit social login -->
<div class="modal fade" id="mLogin" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="mLoginTitle"><i class="bi bi-key me-2"></i>Add social login</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="slId">
        <div class="row g-3">
          <div class="col-6">
            <label class="form-label">Platform *</label>
            <select class="form-select" id="slPlatform">
              <option value="facebook">Facebook</option>
              <option value="instagram">Instagram</option>
              <option value="twitter">X / Twitter</option>
              <option value="linkedin">LinkedIn</option>
              <option value="tiktok">TikTok</option>
              <option value="youtube">YouTube</option>
              <option value="reddit">Reddit</option>
              <option value="pinterest">Pinterest</option>
              <option value="wordpress">WordPress</option>
              <option value="other">Other</option>
            </select>
          </div>
          <div class="col-6">
            <label class="form-label">Title *</label>
            <input class="form-control" id="slTitle" maxlength="255" placeholder="e.g. Main FB page">
          </div>
          <div class="col-12">
            <label class="form-label">Channel URL</label>
            <input class="form-control" id="slUrl" placeholder="https://…">
          </div>
          <div class="col-6">
            <label class="form-label">Username</label>
            <input class="form-control" id="slUser" maxlength="255" autocomplete="off">
          </div>
          <div class="col-6">
            <label class="form-label">Password <span class="text-muted fw-normal" id="slPassHint"></span></label>
            <input type="password" class="form-control" id="slPass" autocomplete="new-password">
          </div>
          <div class="col-12">
            <label class="form-label">Companies</label>
            <div id="slCompanies" class="d-flex flex-column gap-1" style="max-height:150px;overflow-y:auto"></div>
          </div>
          <div class="col-12">
            <label class="form-label">Notes</label>
            <textarea class="form-control" id="slNotes" rows="2"></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-sm btn-mh" id="btnSaveLogin">Save</button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/layout/footer.php'; ?>
