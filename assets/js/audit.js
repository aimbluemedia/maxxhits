/* ============================================================
   audit.js — Audit log page
   ============================================================ */
(function () {
  'use strict';

  async function load() {
    let rows;
    try { rows = await MH.api('audit'); }
    catch (e) { MH.toast(e.message, 'err'); return; }
    const el = document.getElementById('auditList');
    el.innerHTML = rows.length
      ? '<table><thead><tr><th>Time</th><th>Actor</th><th>Action</th><th>Target</th><th>Details</th></tr></thead><tbody>' +
        rows.map(function (r) {
          return '<tr><td style="white-space:nowrap;font-size:12px">' + MH.esc(r.created_at) + '</td>' +
            '<td><code>' + MH.esc(r.actor) + '</code></td>' +
            '<td><span class="tag">' + MH.esc(r.action) + '</span></td>' +
            '<td style="font-size:12px" class="muted">' + MH.esc(r.target_type || '') + '</td>' +
            '<td style="font-size:12.5px">' + MH.esc(r.message || '') + '</td></tr>';
        }).join('') + '</tbody></table>'
      : '<div class="empty">No events yet.</div>';
  }

  document.addEventListener('DOMContentLoaded', load);
})();
