/* ============================================================
   automation.js — Platform automation workspace
   ============================================================ */
(function () {
  'use strict';

  const page = document.getElementById('automationPage');
  if (!page) return;
  const PLATFORM = page.dataset.platform;
  const HAS_ADAPTER = page.dataset.hasAdapter === '1';
  const IS_ADMIN = page.dataset.isAdmin === '1';

  let autos = [];
  let runs = [];
  let brands = [];
  let destinations = [];

  async function load() {
    try {
      const req = [MH.api('automations', { platform: PLATFORM }), MH.api('brands')];
      if (HAS_ADAPTER) req.push(MH.api('destinations'));
      const res = await Promise.all(req);
      autos = res[0].automations;
      runs = res[0].runs;
      brands = res[1].filter(function (b) { return b.is_active; });
      destinations = HAS_ADAPTER
        ? res[2].filter(function (d) { return d.platform === PLATFORM && d.is_active; })
        : [];
    } catch (e) { MH.toast(e.message, 'err'); return; }

    document.getElementById('atConfigured').textContent = autos.length;
    document.getElementById('atEnabled').textContent = autos.filter(function (a) { return a.enabled; }).length;
    document.getElementById('atRuns').textContent = runs.length;
    renderAutos();
    renderRuns();
  }

  function renderAutos() {
    const el = document.getElementById('autosList');
    if (!autos.length) {
      el.innerHTML = '<div class="empty">No automations configured for this platform yet.</div>';
      return;
    }
    el.innerHTML =
      '<table><thead><tr><th>Automation</th><th>Brand</th><th>Schedule</th><th>Destination</th><th>Status</th><th>Last run</th><th></th></tr></thead><tbody>' +
      autos.map(function (a) {
        return '<tr>' +
          '<td><strong>' + MH.esc(a.name) + '</strong>' +
            '<div class="muted" style="font-size:11.5px;max-width:300px">' + MH.esc(a.topic_prompt.slice(0, 120)) + (a.topic_prompt.length > 120 ? '…' : '') + '</div></td>' +
          '<td>' + MH.brandDot(a.brand_color, a.brand_name) + '</td>' +
          '<td style="font-size:12px">' + MH.esc(a.frequency) + ' · ' + MH.esc(a.run_time.slice(0, 5)) + ' AZ' +
            '<div class="muted" style="font-size:11px">' + a.max_drafts + ' draft(s)/run</div></td>' +
          '<td style="font-size:12px">' + (a.destination_name ? MH.esc(a.destination_name) : '<span class="muted">manual handoff</span>') + '</td>' +
          '<td>' + (a.enabled ? '<span class="st st-published">Enabled</span>' : '<span class="st st-draft">Paused</span>') + '</td>' +
          '<td style="font-size:12px" class="muted">' + MH.esc(a.last_run_at || 'never') + '</td>' +
          '<td style="text-align:right;white-space:nowrap">' +
            '<button data-test="' + MH.esc(a.id) + '">Test run</button> ' +
            '<button data-toggle="' + MH.esc(a.id) + '">' + (a.enabled ? 'Pause' : 'Enable') + '</button> ' +
            '<button data-edit="' + MH.esc(a.id) + '">Edit</button>' +
            (IS_ADMIN ? ' <button class="danger" data-del="' + MH.esc(a.id) + '">Delete</button>' : '') +
          '</td></tr>';
      }).join('') + '</tbody></table>';

    el.querySelectorAll('[data-edit]').forEach(function (b) {
      b.addEventListener('click', function () { openModal(b.dataset.edit); });
    });
    el.querySelectorAll('[data-toggle]').forEach(function (b) {
      b.addEventListener('click', async function () {
        try {
          const r = await MH.api('automations', { action: 'toggle', id: b.dataset.toggle }, 'POST');
          MH.toast(r.enabled ? 'Automation enabled.' : 'Automation paused.');
          load();
        } catch (e) { MH.toast(e.message, 'err'); }
      });
    });
    el.querySelectorAll('[data-test]').forEach(function (b) {
      b.addEventListener('click', async function () {
        const a = autos.find(function (x) { return x.id === b.dataset.test; });
        if (!await MH.confirm('Run "' + (a ? a.name : '') + '" now? This calls the AI and creates drafts in the Approval center (it never publishes).', 'Run test')) return;
        b.disabled = true;
        b.textContent = 'Running…';
        try {
          const r = await MH.api('automations', { action: 'test', id: b.dataset.test }, 'POST');
          MH.toast(r.message);
        } catch (e) { MH.toast(e.message, 'err'); }
        load();
      });
    });
    el.querySelectorAll('[data-del]').forEach(function (b) {
      b.addEventListener('click', async function () {
        const a = autos.find(function (x) { return x.id === b.dataset.del; });
        if (!await MH.confirm('Delete automation "' + (a ? a.name : '') + '" and its run history?', 'Delete')) return;
        try {
          await MH.api('automations', { action: 'delete', id: b.dataset.del }, 'POST');
          MH.toast('Automation deleted.');
          load();
        } catch (e) { MH.toast(e.message, 'err'); }
      });
    });
  }

  function renderRuns() {
    const el = document.getElementById('runsList');
    if (!runs.length) {
      el.innerHTML = '<div class="empty">No runs yet — use "Test run" on a configuration.</div>';
      return;
    }
    el.innerHTML =
      '<table><thead><tr><th>Time</th><th>Automation</th><th>Kind</th><th>Result</th></tr></thead><tbody>' +
      runs.map(function (r) {
        const cls = r.status === 'success' ? 'ok' : (r.status === 'error' ? 'err' : 'warn');
        return '<tr><td style="white-space:nowrap;font-size:12px">' + MH.esc(r.created_at) + '</td>' +
          '<td>' + MH.esc(r.automation_name) + '</td>' +
          '<td><span class="tag">' + MH.esc(r.kind) + '</span></td>' +
          '<td style="font-size:12.5px"><span class="' + cls + '">●</span> ' + MH.esc(r.message || r.status) + '</td></tr>';
      }).join('') + '</tbody></table>';
  }

  function openModal(id) {
    const a = id ? autos.find(function (x) { return x.id === id; }) : null;
    document.getElementById('mAutoTitle').textContent = a ? 'Edit automation' : 'New automation';
    document.getElementById('aId').value = a ? a.id : '';
    document.getElementById('aBrand').innerHTML = brands.map(function (b) {
      return '<option value="' + MH.esc(b.id) + '">' + MH.esc(b.name) + '</option>';
    }).join('');
    if (a) document.getElementById('aBrand').value = a.brand_id;
    document.getElementById('aName').value = a ? a.name : '';
    const dest = document.getElementById('aDestination');
    if (dest) {
      dest.innerHTML = '<option value="">— manual handoff (no auto-publish) —</option>' +
        destinations.map(function (d) {
          return '<option value="' + MH.esc(d.id) + '">' + MH.esc(d.name) + ' (' + MH.esc(d.brand_name) + ')</option>';
        }).join('');
      dest.value = a ? (a.destination_id || '') : '';
    }
    document.getElementById('aTopic').value = a ? a.topic_prompt : '';
    document.getElementById('aFrequency').value = a ? a.frequency : 'daily';
    document.getElementById('aTime').value = a ? a.run_time.slice(0, 5) : '06:00';
    document.getElementById('aMax').value = a ? a.max_drafts : 1;
    MH.openModal('mAuto');
  }

  async function save() {
    const dest = document.getElementById('aDestination');
    const payload = {
      action: 'save',
      id: document.getElementById('aId').value || null,
      brand_id: document.getElementById('aBrand').value,
      platform: PLATFORM,
      name: document.getElementById('aName').value.trim(),
      destination_id: dest ? (dest.value || null) : null,
      topic_prompt: document.getElementById('aTopic').value.trim(),
      frequency: document.getElementById('aFrequency').value,
      run_time: document.getElementById('aTime').value,
      max_drafts: document.getElementById('aMax').value
    };
    if (!payload.brand_id) { MH.toast('Pick a brand.', 'err'); return; }
    if (!payload.name || !payload.topic_prompt) { MH.toast('Name and topic instructions are required.', 'err'); return; }
    try {
      await MH.api('automations', payload, 'POST');
      MH.closeModal('mAuto');
      MH.toast('Saved (paused). Test it, then enable.');
      load();
    } catch (e) { MH.toast(e.message, 'err'); }
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('btnAddAuto').addEventListener('click', function () { openModal(null); });
    document.getElementById('btnSaveAuto').addEventListener('click', save);
    load();
  });
})();
