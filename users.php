<?php
// ============================================================
//  users.php — Staff accounts (admin only)
// ============================================================

require_once __DIR__ . '/includes/bootstrap.php';
require_login();
require_admin();

$PAGE = ['id' => 'users', 'title' => 'Users', 'js' => ['users.js']];
include __DIR__ . '/includes/layout/header.php';
?>

<div class="page-head">
  <div>
    <small>Administration</small>
    <h1>Users</h1>
    <p class="muted">Staff accounts. Admins approve content and manage settings; staff create and run tests.</p>
  </div>
  <button class="primary" id="btnAddUser" type="button">Add user</button>
</div>

<div class="panel">
  <div class="scroll" id="usersList"><span class="muted">Loading…</span></div>
</div>

<div class="mh-overlay" id="mUser">
  <div class="mh-modal">
    <div class="mh-modal-head"><span id="mUserTitle">Add user</span><button type="button" data-close>✕</button></div>
    <div class="mh-modal-body">
      <input type="hidden" id="uId">
      <div class="form-grid">
        <div>
          <label>Full name *</label>
          <input id="uFullName" type="text" style="width:100%" maxlength="255">
        </div>
        <div>
          <label>Username *</label>
          <input id="uUsername" type="text" style="width:100%" maxlength="100" autocomplete="off">
        </div>
        <div>
          <label>Email</label>
          <input id="uEmail" type="email" style="width:100%" maxlength="255">
        </div>
        <div>
          <label>Password <span class="muted" id="uPassHint"></span></label>
          <input id="uPassword" type="password" style="width:100%" autocomplete="new-password">
        </div>
        <div>
          <label>Role</label>
          <select id="uRole" style="width:100%">
            <option value="staff">Staff</option>
            <option value="admin">Admin</option>
          </select>
        </div>
        <div>
          <label style="margin-top:34px"><input type="checkbox" id="uActive" checked style="width:auto;margin-right:7px">Account active</label>
        </div>
      </div>
    </div>
    <div class="mh-modal-footer">
      <button type="button" data-close>Cancel</button>
      <button type="button" class="primary" id="btnSaveUser">Save user</button>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/layout/footer.php'; ?>
