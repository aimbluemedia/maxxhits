<?php
// ============================================================
//  settings.php — System settings (admin only)
//  AI provider/keys, scheduler cron info, emergency stop state.
// ============================================================

require_once __DIR__ . '/includes/bootstrap.php';
require_login();
require_admin();

$PAGE = ['id' => 'settings', 'title' => 'Settings', 'js' => ['settings.js']];
include __DIR__ . '/includes/layout/header.php';
?>

<div class="page-head">
  <div>
    <small>Administration</small>
    <h1>Settings</h1>
    <p class="muted">AI provider, scheduler and system controls. Timezone: America/Phoenix (Arizona).</p>
  </div>
</div>

<div class="panel">
  <h2>AI provider</h2>
  <div class="form-grid" style="max-width:760px">
    <div>
      <label>Provider</label>
      <select id="aiProvider" style="width:100%">
        <option value="anthropic">Claude (Anthropic)</option>
        <option value="xai">Grok (xAI)</option>
      </select>
    </div>
    <div>
      <label>Model</label>
      <input id="aiModel" type="text" style="width:100%" placeholder="claude-opus-5-5">
      <div class="hint">Claude default: <code>claude-opus-5-5</code> · Grok default: <code>grok-4</code></div>
    </div>
    <div>
      <label>Claude API key <span class="muted" id="keyAnthHint"></span></label>
      <input id="aiKeyAnthropic" type="password" style="width:100%" autocomplete="new-password" placeholder="sk-ant-…">
      <div class="hint">console.anthropic.com → API keys. Stored encrypted; blank keeps the saved key.</div>
    </div>
    <div>
      <label>Grok API key <span class="muted" id="keyXaiHint"></span></label>
      <input id="aiKeyXai" type="password" style="width:100%" autocomplete="new-password" placeholder="xai-…">
      <div class="hint">console.x.ai → API keys. Stored encrypted; blank keeps the saved key.</div>
    </div>
  </div>
  <div class="row" style="margin-top:14px">
    <button class="primary" id="btnSaveAI" type="button">Save AI settings</button>
    <span class="muted" id="aiStatusLine" style="font-size:12.5px"></span>
  </div>
</div>

<div class="panel">
  <h2>Scheduler (cron)</h2>
  <p class="muted" style="font-size:13px">
    The scheduler runs due automations (AI drafts → Approval center) and publishes due approved posts.
    Add this URL to Hostinger → Advanced → Cron Jobs, every 5–15 minutes
    (or run <code>php cron/scheduler.php</code> from a CLI cron).
  </p>
  <div class="row">
    <input id="cronUrl" type="text" readonly value="Loading…" style="flex:1;min-width:280px;font-size:12px">
    <button id="btnCopyCron" type="button">Copy</button>
  </div>
</div>

<div class="panel">
  <h2>Emergency stop</h2>
  <p class="muted" style="font-size:13px">
    The red button in the header pauses ALL publishing and automation runs system-wide, enforced
    server-side in the scheduler and every publish path. Current state:
    <strong id="stopState">—</strong>
  </p>
</div>

<div class="panel">
  <h2>Database</h2>
  <p class="muted" style="font-size:13px">
    Connection settings live in the config file written by <a href="config.php">config.php</a>.
    Schema: <code>sql/maxxhits-schema.sql</code> (import via phpMyAdmin).
  </p>
</div>

<?php include __DIR__ . '/includes/layout/footer.php'; ?>
