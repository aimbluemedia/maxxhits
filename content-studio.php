<?php
// ============================================================
//  content-studio.php — AI Content Studio
//  Pick a brand + platform + topic, generate draft variants
//  with AI, edit them, and send them into the approval flow.
// ============================================================

require_once __DIR__ . '/includes/bootstrap.php';
require_login();

// SPs are view-only elsewhere; the studio is for creators.
if (!is_admin() && !has_role('content_manager')) {
    header('Location: dashboard.php');
    exit;
}

$PAGE = [
    'id'    => 'content-studio',
    'title' => 'Content Studio',
    'js'    => ['content-studio.js'],
];

include __DIR__ . '/includes/layout/header.php';
?>

<div id="studioPage" data-is-admin="<?= $mh_is_admin ? '1' : '0' ?>">

  <?php if ($mh_is_admin): ?>
  <div class="mh-card" style="background:#f5f3ff;border-color:#ddd6fe">
    <h3>
      <i class="bi bi-stars"></i>AI settings
      <button class="btn btn-sm btn-outline-secondary ms-auto" id="btnToggleAI" type="button">Configure</button>
    </h3>
    <div id="aiSettingsBox" style="display:none">
      <div class="row g-3" style="max-width:720px">
        <div class="col-md-4">
          <label class="form-label">Provider</label>
          <select class="form-select" id="aiProvider">
            <option value="anthropic">Claude (Anthropic)</option>
            <option value="xai">Grok (xAI)</option>
          </select>
        </div>
        <div class="col-md-8">
          <label class="form-label">Model</label>
          <input class="form-control" id="aiModel" placeholder="claude-opus-5-5">
          <div class="form-text">Claude default: <code>claude-opus-5-5</code> · Grok default: <code>grok-4</code></div>
        </div>
        <div class="col-md-6">
          <label class="form-label">Claude API key <span class="text-muted fw-normal" id="keyAnthHint"></span></label>
          <input type="password" class="form-control" id="aiKeyAnthropic" autocomplete="new-password" placeholder="sk-ant-…">
          <div class="form-text">console.anthropic.com → API keys. Stored encrypted.</div>
        </div>
        <div class="col-md-6">
          <label class="form-label">Grok API key <span class="text-muted fw-normal" id="keyXaiHint"></span></label>
          <input type="password" class="form-control" id="aiKeyXai" autocomplete="new-password" placeholder="xai-…">
          <div class="form-text">console.x.ai → API keys. Stored encrypted.</div>
        </div>
        <div class="col-12">
          <button class="btn btn-sm btn-mh" id="btnSaveAI" type="button">Save AI settings</button>
        </div>
      </div>
    </div>
    <div id="aiStatusLine" class="muted" style="font-size:12.5px;color:#64748b">Loading…</div>
  </div>
  <?php endif; ?>

  <div class="two-col" style="display:grid;grid-template-columns:minmax(290px,380px) 1fr;gap:16px;align-items:start">
    <div class="mh-card" style="margin-bottom:0">
      <h3><i class="bi bi-magic"></i>Brief</h3>
      <label class="form-label">Brand / company *</label>
      <select class="form-select mb-2" id="stCompany"></select>
      <label class="form-label">Platform *</label>
      <select class="form-select mb-2" id="stPlatform">
        <option value="blog">Blog (WordPress)</option>
        <option value="facebook">Facebook</option>
        <option value="instagram">Instagram</option>
        <option value="twitter">X / Twitter</option>
        <option value="linkedin">LinkedIn</option>
        <option value="tiktok">TikTok</option>
        <option value="youtube">YouTube</option>
        <option value="reddit">Reddit</option>
        <option value="pinterest">Pinterest</option>
      </select>
      <label class="form-label">Topic *</label>
      <input class="form-control mb-2" id="stTopic" placeholder="e.g. Spring sale on wellness products">
      <label class="form-label">Extra instructions</label>
      <textarea class="form-control mb-2" id="stInstructions" rows="3" placeholder="Angle, keywords, call to action, things to avoid…"></textarea>
      <label class="form-label">Variants</label>
      <select class="form-select mb-3" id="stCount">
        <option>1</option><option>2</option><option value="3" selected>3</option><option>4</option><option>5</option>
      </select>
      <button class="btn btn-mh w-100" id="btnGenerate" type="button">
        <i class="bi bi-stars me-1"></i>Generate drafts
      </button>
      <div class="form-text mt-2">Drafts are never published directly — everything goes through the Approval Center.</div>
    </div>

    <div>
      <div id="variantsArea">
        <div class="state-box">
          <i class="bi bi-stars"></i>
          <strong>Nothing generated yet</strong>
          <p>Fill in the brief and hit Generate — the AI drafts appear here for editing.</p>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/layout/footer.php'; ?>
