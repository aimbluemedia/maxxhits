<?php
// ============================================================
//  users.php — User accounts (admin only)
// ============================================================

require_once __DIR__ . '/includes/bootstrap.php';
require_login();
require_admin();

$PAGE = [
    'id'    => 'users',
    'title' => 'Users',
    'js'    => ['users.js'],
];

include __DIR__ . '/includes/layout/header.php';
?>

<div id="usersList">
  <div class="text-muted" style="font-size:13px">Loading…</div>
</div>

<!-- Modal: add / edit user -->
<div class="modal fade" id="mUser" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="mUserTitle"><i class="bi bi-person-plus me-2"></i>Add user</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="uId">
        <div class="row g-3">
          <div class="col-6">
            <label class="form-label">Full name *</label>
            <input class="form-control" id="uFullName" maxlength="255">
          </div>
          <div class="col-6">
            <label class="form-label">Username *</label>
            <input class="form-control" id="uUsername" maxlength="100" autocomplete="off">
          </div>
          <div class="col-6">
            <label class="form-label">Email</label>
            <input type="email" class="form-control" id="uEmail" maxlength="255">
          </div>
          <div class="col-6">
            <label class="form-label">Password <span class="text-muted fw-normal" id="uPassHint"></span></label>
            <input type="password" class="form-control" id="uPassword" autocomplete="new-password">
          </div>
          <div class="col-12">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="uIsAdmin">
              <label class="form-check-label" for="uIsAdmin" style="font-size:13px">Administrator (full access)</label>
            </div>
          </div>
          <div class="col-6" id="uCmWrap">
            <label class="form-label">Content manager record</label>
            <select class="form-select" id="uCmPerson"><option value="">— none —</option></select>
            <div class="form-text">Grants CM access to that person's companies.</div>
          </div>
          <div class="col-6" id="uSpWrap">
            <label class="form-label">Sales person record</label>
            <select class="form-select" id="uSpPerson"><option value="">— none —</option></select>
            <div class="form-text">Grants SP access to that person's companies.</div>
          </div>
          <div class="col-12">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="uActive" checked>
              <label class="form-check-label" for="uActive" style="font-size:13px">Account active</label>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-sm btn-mh" id="btnSaveUser">Save user</button>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/layout/footer.php'; ?>
