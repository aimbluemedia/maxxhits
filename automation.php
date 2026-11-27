<?php
// ============================================================
//  automation.php?p=<platform> — Platform automation workspace
//  One dedicated page serving all ten Automation nav items.
//  Configure recurring AI research + draft creation; test runs
//  create drafts in the Approval center, never publish.
// ============================================================

require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$platform  = $_GET['p'] ?? 'wordpress';
$platforms = mh_platforms();
if (!isset($platforms[$platform])) { header('Location: dashboard.php'); exit; }

$pcfg  = $platforms[$platform];
$slug  = '';
foreach (mh_automation_pages() as $s => $cfg) {
    if ($cfg['platform'] === $platform) { $slug = $s; break; }
}

$PAGE = ['id' => $slug, 'title' => $pcfg['label'] . ' Automation', 'js' => ['automation.js']];
include __DIR__ . '/includes/layout/header.php';
?>

<div class="page-head" id="automationPage"
     data-platform="<?= e($platform) ?>"
     data-has-adapter="<?= $pcfg['adapter'] ? '1' : '0' ?>"
     data-is-admin="<?= $mh_is_admin ? '1' : '0' ?>">
  <div>
    <small>Automation</small>
    <h1><?= e($pcfg['label']) ?> automation</h1>
    <p class="muted">Recurring AI research and draft creation on a brand-specific schedule (Arizona time).
      Every run creates drafts for the Approval center — nothing publishes without approval.</p>
  </div>
  <button class="primary" id="btnAddAuto" type="button">New automation</button>
</div>

<?php if (!$pcfg['adapter']): ?>
<div class="banner">
  <?= e($pcfg['label']) ?> has no publishing adapter yet — approved drafts are
  <strong>manual handoff</strong>: staff copies the approved content into the platform natively.
</div>
<?php endif; ?>

<div class="grid" id="autoTiles">
  <div class="panel"><small>Configured</small><div class="num" id="atConfigured">—</div></div>
  <div class="panel"><small>Enabled</small><div class="num ok" id="atEnabled">—</div></div>
  <div class="panel"><small>Runs recorded</small><div class="num" id="atRuns">—</div></div>
</div>

<div class="panel">
  <h2>Configurations</h2>
  <div class="scroll" id="autosList"><span class="muted">Loading…</span></div>
</div>

<div class="panel">
  <h2>Run history</h2>
  <div class="scroll" id="runsList"><span class="muted">Loading…</span></div>
</div>

<div class="mh-overlay" id="mAuto">
  <div class="mh-modal">
    <div class="mh-modal-head"><span id="mAutoTitle">New automation</span><button type="button" data-close>✕</button></div>
    <div class="mh-modal-body">
      <input type="hidden" id="aId">
      <div class="form-grid">
        <div>
          <label>Brand *</label>
          <select id="aBrand" style="width:100%"></select>
        </div>
        <div>
          <label>Automation name *</label>
          <input id="aName" type="text" style="width:100%" placeholder="Daily <?= e(strtolower($pcfg['label'])) ?> workflow" maxlength="255">
        </div>
        <?php if ($pcfg['adapter']): ?>
        <div class="full">
          <label>Destination <span class="muted">(where approved drafts publish — manage under Connected accounts)</span></label>
          <select id="aDestination" style="width:100%"><option value="">— manual handoff (no auto-publish) —</option></select>
        </div>
        <?php endif; ?>
        <div class="full">
          <label>Content topics, research instructions and audience *</label>
          <textarea id="aTopic" rows="4" placeholder="What should the AI research and write about for this brand?"></textarea>
        </div>
        <div>
          <label>Frequency</label>
          <select id="aFrequency" style="width:100%">
            <option value="daily">Daily</option>
            <option value="weekdays">Weekdays</option>
            <option value="weekly">Weekly (Mondays)</option>
            <option value="monthly">Monthly (1st)</option>
          </select>
        </div>
        <div>
          <label>Run time (Arizona)</label>
          <input id="aTime" type="time" value="06:00" style="width:100%">
        </div>
        <div>
          <label>Max drafts per run (1–5)</label>
          <input id="aMax" type="number" min="1" max="5" value="1" style="width:100%">
        </div>
      </div>
      <p class="hint" style="margin-top:12px">Saving always stores the automation <strong>paused</strong> —
        review a test run before enabling it.</p>
    </div>
    <div class="mh-modal-footer">
      <button type="button" data-close>Cancel</button>
      <button type="button" class="primary" id="btnSaveAuto">Save configuration</button>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/layout/footer.php'; ?>
