/* ============================================================
   accounts.js — Connected accounts (destinations) page
   ============================================================ */
(function () {
  'use strict';

  const page = document.getElementById('accountsPage');
  if (!page) return;
  const IS_ADMIN = page.dataset.isAdmin === '1';
  const PLATFORMS = window.MH_PLATFORMS || {};

  let dests = [];
  let brands = [];

  async function load() {
    try {
      const res = await Promise.all([MH.api('destinations'), MH.api('brands')]);
      dests = res[0];
      brands = res[1].filter(function (b) { return b.is_active; });
    } catch (e) { MH.toast(e.message, 'err'); return; }
    render();
  }

  function render() {
    const el = document.getElementById('destList');
    if (!dests.length) {
      el.innerHTML = '<div class="empty">No destinations yet' +
        (IS_ADMIN ? ' — connect a WordPress site or social account.' : '.') + '</div>';
      return;
    }
    el.innerHTML =
      '<table><thead><tr><th>Destination</th><th>Platform</th><th>Brand</th><th>Status</th><th>Last test</th>' +
      (IS_ADMIN ? '<th></th>' : '') + '</tr></thead><tbody>' +
      dests.map(function (d) {
        const test = d.last_test_at
          ? (d.last_test_ok ? '<span class="st st-published">OK</span>' : '<span class="st st-failed">Failed</span>') +
            ' <small class="muted">' + MH.esc(d.last_test_at) + '</small>'
          : '<span class="muted">never</span>';
        return '<tr>' +
          '<td><strong>' + MH.esc(d.name) + '</strong>' +
            (d.has_adapter ? '' : ' <span class="tag">manual handoff</span>') + '</td>' +
          '<td>' + MH.platformBadge(d.platform) + '</td>' +
          '<td>' + MH.brandDot(d.brand_color, d.brand_name) + '</td>' +
          '<td>' + (d.is_active ? '<span class="st st-published">Active</span>' : '<span class="st st-draft">Inactive</span>') + '</td>' +
          '<td style="font-size:12px">' + test + '</td>' +
          (IS_ADMIN
            ? '<td style="text-align:right;white-space:nowrap">' +
              (d.has_adapter ? '<button data-test="' + MH.esc(d.id) + '">Test</button> ' : '') +
              '<button data-edit="' + MH.esc(d.id) + '">Edit</button> ' +
              '<button class="danger" data-del="' + MH.esc(d.id) + '">Delete</button></td>'
            : '') +
          '</tr>';
      }).join('') + '</tbody></table>';

    if (!IS_ADMIN) return;
    el.querySelectorAll('[data-test]').forEach(function (b) {
      b.addEventListener('click', async function () {
        b.disabled = true;
        b.textContent = 'Testing…';
        try {
          await MH.api('destinations', { action: 'test', id: b.dataset.test }, 'POST');
          MH.toast('Connection OK.');
        } catch (e) { MH.toast(e.message, 'err'); }
        load();
      });
    });
    el.querySelectorAll('[data-edit]').forEach(function (b) {
      b.addEventListener('click', function () { openModal(b.dataset.edit); });
    });
    el.querySelectorAll('[data-del]').forEach(function (b) {
      b.addEventListener('click', async function () {
        const d = dests.find(function (x) { return x.id === b.dataset.del; });
        if (!await MH.confirm('Delete destination "' + (d ? d.name : '') + '"? Posts pointed at it stop auto-publishing.', 'Delete')) return;
        try {
          await MH.api('destinations', { action: 'delete', id: b.dataset.del }, 'POST');
          MH.toast('Destination deleted.');
          load();
        } catch (e) { MH.toast(e.message, 'err'); }
      });
    });
  }

  function renderFields(platform, isEdit) {
    const cfg = PLATFORMS[platform] || { fields: [] };
    const box = document.getElementById('dFields');
    if (!cfg.adapter) {
      box.innerHTML = '<p class="hint" style="margin-top:12px">' + MH.esc(cfg.label || platform) +
        ' has no publishing adapter yet — this destination is a label for manual handoff and needs no credentials.</p>';
      return;
    }
    box.innerHTML = cfg.fields.map(function (f) {
      const secret = f[0].indexOf('password') !== -1 || f[0].indexOf('token') !== -1;
      return '<div><label>' + MH.esc(f[1]) +
        (isEdit && secret ? ' <span class="muted">(blank keeps current)</span>' : '') + '</label>' +
        '<input style="width:100%" data-cfg="' + MH.esc(f[0]) + '" placeholder="' + MH.esc(f[2]) + '"' +
        (secret ? ' type="password" autocomplete="new-password"' : ' type="text"') + '>' +
        '<div class="hint">' + MH.esc(f[3]) + '</div></div>';
    }).join('');
  }

  function openModal(id) {
    const d = id ? dests.find(function (x) { return x.id === id; }) : null;
    document.getElementById('mDestTitle').textContent = d ? 'Edit destination' : 'Add destination';
    document.getElementById('dId').value = d ? d.id : '';
    document.getElementById('dBrand').innerHTML = brands.map(function (b) {
      return '<option value="' + MH.esc(b.id) + '">' + MH.esc(b.name) + '</option>';
    }).join('');
    if (d) document.getElementById('dBrand').value = d.brand_id;
    const pSel = document.getElementById('dPlatform');
    pSel.innerHTML = Object.keys(PLATFORMS).map(function (k) {
      return '<option value="' + MH.esc(k) + '">' + MH.esc(PLATFORMS[k].label) + '</option>';
    }).join('');
    pSel.value = d ? d.platform : 'wordpress';
    document.getElementById('dName').value = d ? d.name : '';
    document.getElementById('dActive').checked = d ? d.is_active : true;
    renderFields(pSel.value, !!d);
    MH.openModal('mDest');
  }

  async function save() {
    const config = {};
    document.querySelectorAll('#dFields [data-cfg]').forEach(function (i) { config[i.dataset.cfg] = i.value; });
    const payload = {
      action: 'save',
      id: document.getElementById('dId').value || null,
      brand_id: document.getElementById('dBrand').value,
      platform: document.getElementById('dPlatform').value,
      name: document.getElementById('dName').value.trim(),
      is_active: document.getElementById('dActive').checked,
      config: config
    };
    if (!payload.name) { MH.toast('Destination name is required.', 'err'); return; }
    try {
      await MH.api('destinations', payload, 'POST');
      MH.closeModal('mDest');
      MH.toast('Destination saved.');
      load();
    } catch (e) { MH.toast(e.message, 'err'); }
  }

  document.addEventListener('DOMContentLoaded', function () {
    if (IS_ADMIN) {
      document.getElementById('btnAddDest').addEventListener('click', function () { openModal(null); });
      document.getElementById('btnSaveDest').addEventListener('click', save);
      document.getElementById('dPlatform').addEventListener('change', function () {
        renderFields(this.value, !!document.getElementById('dId').value);
      });
    }
    load();
  });
})();
