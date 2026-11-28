<?php
// ============================================================
//  brands.php — Brand directory (Administration)
// ============================================================

require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$PAGE = ['id' => 'brands', 'title' => 'Brands', 'js' => ['brands.js']];
include __DIR__ . '/includes/layout/header.php';
?>

<div class="page-head" id="brandsPage" data-is-admin="<?= $mh_is_admin ? '1' : '0' ?>">
  <div>
    <small>Administration</small>
    <h1>Brands</h1>
    <p class="muted">The global brand directory. Changes update the workspace selector immediately.</p>
  </div>
  <?php if ($mh_is_admin): ?><button class="primary" id="btnAddBrand" type="button">Add brand</button><?php endif; ?>
</div>

<div class="panel">
  <div class="scroll" id="brandsList"><span class="muted">Loading…</span></div>
</div>

<?php if ($mh_is_admin): ?>
<div class="mh-overlay" id="mBrand">
  <div class="mh-modal">
    <div class="mh-modal-head"><span id="mBrandTitle">Add brand</span><button type="button" data-close>✕</button></div>
    <div class="mh-modal-body">
      <input type="hidden" id="bId">
      <div class="form-grid">
        <div>
          <label>Brand name *</label>
          <input id="bName" type="text" style="width:100%" maxlength="255">
        </div>
        <div>
          <label>Color</label>
          <input id="bColor" type="color" value="#7555df" style="width:100%;height:40px;padding:4px">
        </div>
        <div class="full">
          <label>Website URL</label>
          <input id="bUrl" type="text" style="width:100%" placeholder="https://example.com">
        </div>
        <div class="full">
          <label>Brand voice / AI instructions</label>
          <textarea id="bVoice" rows="3" placeholder="Tone, audience, do's and don'ts for this brand…"></textarea>
        </div>
        <div class="full">
          <label><input type="checkbox" id="bActive" checked style="width:auto;margin-right:7px">Active (appears in the workspace selector)</label>
        </div>
      </div>
    </div>
    <div class="mh-modal-footer">
      <button type="button" data-close>Cancel</button>
      <button type="button" class="primary" id="btnSaveBrand">Save brand</button>
    </div>
  </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/layout/footer.php'; ?>
