/* ============================================================
   brands.js — Brand directory page
   ============================================================ */
(function () {
  'use strict';

  const page = document.getElementById('brandsPage');
  if (!page) return;
  const IS_ADMIN = page.dataset.isAdmin === '1';
  let brands = [];

  async function load() {
    try { brands = await MH.api('brands'); }
    catch (e) { MH.toast(e.message, 'err'); return; }
    render();
  }

  function render() {
    const el = document.getElementById('brandsList');
    if (!brands.length) {
      el.innerHTML = '<div class="empty">No brands yet' + (IS_ADMIN ? ' — add the first one.' : '.') + '</div>';
      return;
    }
    el.innerHTML =
      '<table><thead><tr><th>Brand</th><th>Website</th><th>Brand voice</th><th>Posts</th><th>Destinations</th><th>Status</th>' +
      (IS_ADMIN ? '<th></th>' : '') + '</tr></thead><tbody>' +
      brands.map(function (b) {
        return '<tr>' +
          '<td><strong>' + MH.brandDot(b.color, b.name) + '</strong></td>' +
          '<td>' + (b.website_url ? '<a href="' + MH.esc(b.website_url) + '" target="_blank" rel="noopener">' + MH.esc(b.website_url.replace(/^https?:\/\//, '')) + '</a>' : '—') + '</td>' +
          '<td style="font-size:12.5px;max-width:340px">' + MH.esc(b.brand_voice || '—') + '</td>' +
          '<td>' + b.post_count + '</td>' +
          '<td>' + b.destination_count + '</td>' +
          '<td>' + (b.is_active ? '<span class="st st-published">Active</span>' : '<span class="st st-draft">Archived</span>') + '</td>' +
          (IS_ADMIN
            ? '<td style="text-align:right;white-space:nowrap">' +
              '<button data-edit="' + MH.esc(b.id) + '">Edit</button> ' +
              '<button class="danger" data-del="' + MH.esc(b.id) + '">Delete</button></td>'
            : '') +
          '</tr>';
      }).join('') + '</tbody></table>';

    if (!IS_ADMIN) return;
    el.querySelectorAll('[data-edit]').forEach(function (b) {
      b.addEventListener('click', function () { openModal(b.dataset.edit); });
    });
    el.querySelectorAll('[data-del]').forEach(function (b) {
      b.addEventListener('click', function () { del(b.dataset.del); });
    });
  }

  function openModal(id) {
    const b = id ? brands.find(function (x) { return x.id === id; }) : null;
    document.getElementById('mBrandTitle').textContent = b ? 'Edit brand' : 'Add brand';
    document.getElementById('bId').value = b ? b.id : '';
    document.getElementById('bName').value = b ? b.name : '';
    document.getElementById('bColor').value = b ? b.color : '#7555df';
    document.getElementById('bUrl').value = b ? (b.website_url || '') : '';
    document.getElementById('bVoice').value = b ? (b.brand_voice || '') : '';
    document.getElementById('bActive').checked = b ? b.is_active : true;
    MH.openModal('mBrand');
  }

  async function save() {
    const payload = {
      action: 'save',
      id: document.getElementById('bId').value || null,
      name: document.getElementById('bName').value.trim(),
      color: document.getElementById('bColor').value,
      website_url: document.getElementById('bUrl').value.trim(),
      brand_voice: document.getElementById('bVoice').value.trim(),
      is_active: document.getElementById('bActive').checked
    };
    if (!payload.name) { MH.toast('Brand name is required.', 'err'); return; }
    try {
      await MH.api('brands', payload, 'POST');
      MH.closeModal('mBrand');
      MH.toast('Brand saved.');
      load();
    } catch (e) { MH.toast(e.message, 'err'); }
  }

  async function del(id) {
    const b = brands.find(function (x) { return x.id === id; });
    const yes = await MH.confirm('Delete brand "' + (b ? b.name : id) + '"? Brands with history are archived instead of deleted.', 'Delete');
    if (!yes) return;
    try {
      const r = await MH.api('brands', { action: 'delete', id: id }, 'POST');
      MH.toast(r.archived ? 'Brand had history — archived instead.' : 'Brand deleted.');
      load();
    } catch (e) { MH.toast(e.message, 'err'); }
  }

  document.addEventListener('DOMContentLoaded', function () {
    if (IS_ADMIN) {
      document.getElementById('btnAddBrand').addEventListener('click', function () { openModal(null); });
      document.getElementById('btnSaveBrand').addEventListener('click', save);
    }
    load();
  });
})();
