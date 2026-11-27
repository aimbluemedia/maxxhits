<?php
// ============================================================
//  module.php?m=<slug> — Placeholder module workspace
//  Structured workspace for modules not yet built out: shows
//  the module's build spec (from the prototype) and a simple
//  record list so the team can already collect work items.
// ============================================================

require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$slug = $_GET['m'] ?? '';
$mods = mh_modules();
$mod  = $mods[$slug] ?? null;
if (!$mod || !empty($mod['page'])) {
    header('Location: dashboard.php');
    exit;
}

$group = '';
foreach (mh_groups() as $g => $slugs) {
    if (in_array($slug, $slugs, true)) { $group = $g; break; }
}

$PAGE = ['id' => $slug, 'title' => $mod['label'], 'js' => ['module.js']];
include __DIR__ . '/includes/layout/header.php';
?>

<div class="page-head" id="modulePage" data-module="<?= e($slug) ?>">
  <div>
    <small><?= e($group) ?></small>
    <h1><?= e($mod['label']) ?></h1>
    <p class="muted"><?= e($mod['goal'] ?? '') ?></p>
  </div>
  <span class="tag">Planned module</span>
</div>

<div class="banner">
  This module is scaffolded but not built out yet. The workspace below already stores
  work items; the specification underneath is the build plan for a future phase.
</div>

<div class="panel">
  <h2>Workspace records</h2>
  <label>Record title</label>
  <input id="recTitle" type="text" style="width:100%" placeholder="Describe the task or item" maxlength="500">
  <label>Notes / evidence</label>
  <textarea id="recNotes" rows="3"></textarea>
  <div class="row" style="margin-top:12px">
    <button class="primary" id="btnAddRecord" type="button">Add record</button>
  </div>
</div>

<div class="panel">
  <h2>Records</h2>
  <div class="scroll" id="recordsList"><span class="muted">Loading…</span></div>
</div>

<div class="panel">
  <h2>Build specification</h2>
  <?php foreach ([['Goal', 'goal'], ['Workflow', 'workflow'], ['Entities', 'entities'], ['Proposed API', 'api'], ['Safety & policy checks', 'checks']] as [$label, $key]): ?>
    <?php if (!empty($mod[$key])): ?>
    <div class="devsection">
      <h3><?= e($label) ?></h3>
      <?php if ($key === 'api'): ?>
        <div class="devcode"><?php foreach (explode(';', $mod[$key]) as $a): ?><code><?= e(trim($a)) ?></code><?php endforeach; ?></div>
      <?php else: ?>
        <p><?= e($mod[$key]) ?></p>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  <?php endforeach; ?>
</div>

<?php include __DIR__ . '/includes/layout/footer.php'; ?>
