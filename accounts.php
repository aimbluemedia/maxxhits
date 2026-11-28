<?php
// ============================================================
//  accounts.php — Connected accounts / destinations (Admin area)
// ============================================================

require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$PAGE = ['id' => 'connected-accounts', 'title' => 'Connected accounts', 'js' => ['accounts.js']];
include __DIR__ . '/includes/layout/header.php';

$mh_platforms_json = json_encode(mh_platforms());
?>

<div class="page-head" id="accountsPage" data-is-admin="<?= $mh_is_admin ? '1' : '0' ?>">
  <div>
    <small>Administration</small>
    <h1>Connected accounts</h1>
    <p class="muted">Publishing destinations per brand. Credentials are stored encrypted and never leave the server.</p>
  </div>
  <?php if ($mh_is_admin): ?><button class="primary" id="btnAddDest" type="button">Add destination</button><?php endif; ?>
</div>

<div class="panel">
  <div class="scroll" id="destList"><span class="muted">Loading…</span></div>
</div>

<?php if ($mh_is_admin): ?>
<div class="mh-overlay" id="mDest">
  <div class="mh-modal">
    <div class="mh-modal-head"><span id="mDestTitle">Add destination</span><button type="button" data-close>✕</button></div>
    <div class="mh-modal-body">
      <input type="hidden" id="dId">
      <div class="form-grid">
        <div>
          <label>Brand *</label>
          <select id="dBrand" style="width:100%"></select>
        </div>
        <div>
          <label>Platform *</label>
          <select id="dPlatform" style="width:100%"></select>
        </div>
        <div class="full">
          <label>Destination name *</label>
          <input id="dName" type="text" style="width:100%" placeholder="e.g. Main blog" maxlength="255">
        </div>
      </div>
      <div id="dFields"><!-- per-platform credential fields --></div>
      <label style="margin-top:14px"><input type="checkbox" id="dActive" checked style="width:auto;margin-right:7px">Destination active</label>
    </div>
    <div class="mh-modal-footer">
      <button type="button" data-close>Cancel</button>
      <button type="button" class="primary" id="btnSaveDest">Save destination</button>
    </div>
  </div>
</div>
<script>window.MH_PLATFORMS = <?= $mh_platforms_json ?>;</script>
<?php endif; ?>

<?php include __DIR__ . '/includes/layout/footer.php'; ?>
