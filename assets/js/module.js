/* ============================================================
   module.js — Placeholder module workspace
   ============================================================ */
(function () {
  'use strict';

  const page = document.getElementById('modulePage');
  if (!page) return;
  const MODULE = page.dataset.module;

  async function load() {
    let rows;
    try { rows = await MH.api('records', { module: MODULE }); }
    catch (e) { MH.toast(e.message, 'err'); return; }
    const el = document.getElementById('recordsList');
    el.innerHTML = rows.length
      ? '<table><thead><tr><th>Item</th><th>Notes</th><th>Brand</th><th>By</th><th></th></tr></thead><tbody>' +
        rows.map(function (r) {
          return '<tr><td><strong>' + MH.esc(r.title) + '</strong></td>' +
            '<td style="font-size:12.5px">' + MH.esc(r.notes || '—') + '</td>' +
            '<td>' + (r.brand_name ? MH.brandDot(r.brand_color, r.brand_name) : '<span class="muted">—</span>') + '</td>' +
            '<td class="muted" style="font-size:12px">' + MH.esc(r.created_by || '—') + '</td>' +
            '<td style="text-align:right"><button class="danger" data-del="' + MH.esc(r.id) + '">Delete</button></td></tr>';
        }).join('') + '</tbody></table>'
      : '<div class="empty">No records yet. Add the first work item above.</div>';
    el.querySelectorAll('[data-del]').forEach(function (b) {
      b.addEventListener('click', async function () {
        if (!await MH.confirm('Delete this record?', 'Delete')) return;
        try {
          await MH.api('records', { action: 'delete', id: b.dataset.del }, 'POST');
          load();
        } catch (e) { MH.toast(e.message, 'err'); }
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('btnAddRecord').addEventListener('click', async function () {
      const title = document.getElementById('recTitle').value.trim();
      if (!title) { MH.toast('Title is required.', 'err'); return; }
      try {
        await MH.api('records', {
          action: 'save', module: MODULE,
          title: title, notes: document.getElementById('recNotes').value.trim()
        }, 'POST');
        document.getElementById('recTitle').value = '';
        document.getElementById('recNotes').value = '';
        MH.toast('Record added.');
        load();
      } catch (e) { MH.toast(e.message, 'err'); }
    });
    load();
  });
})();
