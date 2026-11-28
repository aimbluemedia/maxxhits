/* ============================================================
   queue.js — Publishing queue page
   ============================================================ */
(function () {
  'use strict';

  const page = document.getElementById('queuePage');
  if (!page) return;
  const IS_ADMIN = page.dataset.isAdmin === '1';
  let rows = [];

  async function load() {
    try {
      rows = await MH.api('posts', { action: 'list', status: 'approved,scheduled,publishing,published,failed' });
    } catch (e) { MH.toast(e.message, 'err'); return; }
    render();
  }

  function render() {
    const el = document.getElementById('queueList');
    if (!rows.length) {
      el.innerHTML = '<div class="empty">Queue is empty. Approve posts in the Approval center to queue them.</div>';
      return;
    }
    el.innerHTML =
      '<table><thead><tr><th>When (AZ)</th><th>Post</th><th>Brand</th><th>Destination</th><th>Status</th><th>Result</th>' +
      (IS_ADMIN ? '<th></th>' : '') + '</tr></thead><tbody>' +
      rows.map(function (p) {
        const when = (p.post_date ? MH.fmtDate(p.post_date) : '<span class="muted">no date</span>') +
          (p.publish_time ? ' <small class="muted">' + MH.esc(p.publish_time.slice(0, 5)) + '</small>' : '');
        let result = '—';
        if (p.status === 'published') {
          result = p.post_url
            ? '<a href="' + MH.esc(p.post_url) + '" target="_blank" rel="noopener" style="font-size:12px">View post ↗</a>'
            : '<small class="muted">' + MH.esc(p.published_at || '') + '</small>';
        } else if (p.status === 'failed') {
          result = '<span class="err" style="font-size:11.5px">' + MH.esc(p.publish_error || 'failed') +
            (p.publish_attempts > 1 ? ' (attempt ' + p.publish_attempts + ')' : '') + '</span>';
        }
        const canPublish = IS_ADMIN && p.publish_mode === 'auto'
          && (p.status === 'scheduled' || p.status === 'failed') && p.approved_at;
        return '<tr>' +
          '<td style="white-space:nowrap">' + when + '</td>' +
          '<td><strong>' + MH.esc(p.title) + '</strong>' +
            (p.publish_mode === 'manual' ? ' <span class="tag">manual handoff</span>' : '') + '</td>' +
          '<td>' + MH.brandDot(p.brand_color, p.brand_name) + '</td>' +
          '<td style="font-size:12px">' + (p.destination_name
            ? MH.esc(p.destination_name) + ' <span class="muted">(' + MH.esc(p.destination_platform) + ')</span>'
            : '<span class="muted">—</span>') + '</td>' +
          '<td>' + MH.statusBadge(p.status) + '</td>' +
          '<td>' + result + '</td>' +
          (IS_ADMIN
            ? '<td style="text-align:right">' + (canPublish
                ? '<button data-pub="' + MH.esc(p.id) + '">Publish now</button>'
                : '') + '</td>'
            : '') +
          '</tr>';
      }).join('') + '</tbody></table>';

    el.querySelectorAll('[data-pub]').forEach(function (b) {
      b.addEventListener('click', async function () {
        const p = rows.find(function (x) { return x.id === b.dataset.pub; });
        if (!await MH.confirm('Publish "' + (p ? p.title : '') + '" right now?', 'Publish')) return;
        b.disabled = true;
        b.textContent = 'Publishing…';
        try {
          const r = await MH.api('posts', { action: 'publish_now', id: b.dataset.pub }, 'POST');
          MH.toast('Published!' + (r.url ? ' ' + r.url : ''));
        } catch (e) { MH.toast(e.message, 'err'); }
        load();
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    const run = document.getElementById('btnRunDue');
    if (run) {
      run.addEventListener('click', async function () {
        run.disabled = true;
        run.textContent = 'Running…';
        try {
          const r = await MH.api('settings', { action: 'run_scheduler' }, 'POST');
          MH.toast(r.automations.length + ' automation run(s), ' + r.published + ' published, ' + r.failed + ' failed.',
            r.failed ? 'err' : undefined);
        } catch (e) { MH.toast(e.message, 'err'); }
        run.disabled = false;
        run.textContent = 'Run scheduler now';
        load();
      });
    }
    load();
  });
})();
