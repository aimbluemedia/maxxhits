<?php
// ============================================================
//  automation.php — Automated posting hub
//  Publishing channels (WordPress + social), the auto-publish
//  queue, activity logs and the cron setup info.
// ============================================================

require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$PAGE = [
    'id'    => 'automation',
    'title' => 'Automation',
    'js'    => ['automation.js'],
];

include __DIR__ . '/includes/layout/header.php';
?>

<div id="automationPage" data-is-admin="<?= $mh_is_admin ? '1' : '0' ?>">

  <?php if ($mh_is_admin): ?>
  <div class="mh-card" style="background:#f5f3ff;border-color:#ddd6fe">
    <h3><i class="bi bi-clock-history"></i>Scheduler (cron)</h3>
    <div style="font-size:13px;color:#475569">
      Auto-publish runs when the cron URL below is called. Add it to your hosting panel
      (Hostinger: Advanced → Cron Jobs) or an external cron service, every 5–15 minutes.
      <div class="input-group input-group-sm mt-2" style="max-width:720px">
        <input class="form-control" id="cronUrl" readonly value="Loading…" style="font-size:12px">
        <button class="btn btn-outline-secondary" id="btnCopyCron" type="button"><i class="bi bi-clipboard"></i> Copy</button>
        <button class="btn btn-mh" id="btnRunDue" type="button"><i class="bi bi-play-fill"></i> Run now</button>
      </div>
      <div class="form-text">CLI alternative: <code>php cron/publish-scheduler.php</code></div>
    </div>
  </div>
  <?php endif; ?>

  <div class="mh-card">
    <h3>
      <i class="bi bi-broadcast"></i>Publishing channels
      <span class="ms-auto"></span>
    </h3>
    <div id="channelsList"><div class="text-muted" style="font-size:13px">Loading…</div></div>
  </div>

  <div class="mh-card">
    <h3><i class="bi bi-robot"></i>Auto-publish queue</h3>
    <div id="queueList"><div class="text-muted" style="font-size:13px">Loading…</div></div>
  </div>

  <div class="mh-card">
    <h3><i class="bi bi-journal-text"></i>Activity log</h3>
    <div id="logsList"><div class="text-muted" style="font-size:13px">Loading…</div></div>
  </div>
</div>

<?php if ($mh_is_admin): ?>
<!-- Modal: add / edit channel -->
<div class="modal fade" id="mChannel" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="mChannelTitle"><i class="bi bi-broadcast me-2"></i>Add channel</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="chId">
        <div class="row g-3">
          <div class="col-6">
            <label class="form-label">Company *</label>
            <select class="form-select" id="chCompany"></select>
          </div>
          <div class="col-6">
            <label class="form-label">Platform *</label>
            <select class="form-select" id="chPlatform">
              <option value="wordpress">WordPress</option>
              <option value="facebook">Facebook Page</option>
              <option value="instagram">Instagram Business</option>
              <option value="twitter">X / Twitter</option>
              <option value="linkedin">LinkedIn</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label">Channel name *</label>
            <input class="form-control" id="chName" maxlength="255" placeholder="e.g. Acme blog">
          </div>
          <div class="col-12" id="chConfigFields">
            <!-- per-platform credential fields injected by automation.js -->
          </div>
          <div class="col-12">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="chActive" checked>
              <label class="form-check-label" for="chActive" style="font-size:13px">Channel active</label>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-sm btn-mh" id="btnSaveChannel">Save channel</button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/layout/footer.php'; ?>
