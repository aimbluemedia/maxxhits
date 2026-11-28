<?php
// ============================================================
//  content-studio.php — Content studio (Creative)
//  Brief → AI variants → edit → submit for review.
// ============================================================

require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$PAGE = ['id' => 'content-studio', 'title' => 'Content studio', 'js' => ['content-studio.js']];
include __DIR__ . '/includes/layout/header.php';

$mh_platform_opts = '';
foreach (mh_platforms() as $key => $p) {
    $mh_platform_opts .= '<option value="' . e($key) . '">' . e($p['label']) . '</option>';
}
?>

<div class="page-head">
  <div>
    <small>Creative</small>
    <h1>Content studio</h1>
    <p class="muted">AI drafts in the brand's voice. Nothing publishes without approval.</p>
  </div>
</div>

<div class="two" style="grid-template-columns:minmax(290px,380px) 1fr;align-items:start">
  <div class="panel" style="margin-bottom:0">
    <h2>Brief</h2>
    <label>Brand *</label>
    <select id="stBrand" style="width:100%"></select>
    <label>Platform *</label>
    <select id="stPlatform" style="width:100%"><?= $mh_platform_opts ?></select>
    <label>Topic *</label>
    <input id="stTopic" type="text" style="width:100%" placeholder="e.g. Spring wellness tips">
    <label>Extra instructions</label>
    <textarea id="stInstructions" rows="3" placeholder="Angle, keywords, call to action, things to avoid…"></textarea>
    <label>Variants</label>
    <select id="stCount" style="width:100%">
      <option>1</option><option>2</option><option selected>3</option><option>4</option><option>5</option>
    </select>
    <button class="primary" id="btnGenerate" type="button" style="width:100%;margin-top:16px">Generate drafts</button>
    <p class="hint" style="margin-top:10px">Drafts go to the Approval center — the AI never publishes directly.
      AI provider and keys are configured in Settings.</p>
  </div>

  <div id="variantsArea">
    <div class="state-box">
      <strong>Nothing generated yet</strong>
      <p>Fill in the brief and hit Generate — drafts appear here for editing.</p>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/layout/footer.php'; ?>
