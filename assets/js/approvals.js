/* ============================================================
   approvals.js — Approval center page
   ============================================================ */
(function () {
  'use strict';

  const page = document.getElementById('approvalsPage');
  if (!page) return;
  const IS_ADMIN = page.dataset.isAdmin === '1';
  let rows = [];

  async function load() {
    try { rows = await MH.api('posts', { action: 'review_queue' }); }
    catch (e) { MH.toast(e.message, 'err'); return; }
    const c = { review: 0, approved: 0, rejected: 0 };
    rows.forEach(function (p) { if (c[p.status] !== undefined) c[p.status]++; });
    document.getElementById('apReview').textContent = c.review;
    document.getElementById('apApproved').textContent = c.approved;
    document.getElementById('apRejected').textContent = c.rejected;
    render();
  }

  function render() {
    const el = document.getElementById('approvalsList');
    if (!rows.length) {
      el.innerHTML = '<div class="empty">Nothing to review. Posts submitted from the Content studio or automations appear here.</div>';
      return;
    }
    el.innerHTML =
      '<table><thead><tr><th>Post</th><th>Brand</th><th>Platform</th><th>Scheduled</th><th>Source</th><th>Status</th><th></th></tr></thead><tbody>' +
      rows.map(function (p) {
        const note = p.review_note
          ? '<div class="warn" style="font-size:11.5px">Note: ' + MH.esc(p.review_note) + '</div>' : '';
        const appr = p.approved_by && p.status === 'approved'
          ? '<div class="ok" style="font-size:11.5px">by ' + MH.esc(p.approved_by) + '</div>' : '';
        return '<tr>' +
          '<td><strong>' + MH.esc(p.title) + '</strong>' +
            (p.ai_generated == 1 ? ' <span class="tag">AI</span>' : '') + note + '</td>' +
          '<td>' + MH.brandDot(p.brand_color, p.brand_name) + '</td>' +
          '<td>' + MH.platformBadge(p.platform) + '</td>' +
          '<td style="white-space:nowrap">' + MH.fmtDate(p.post_date) +
            (p.publish_time ? ' <small class="muted">' + MH.esc(p.publish_time.slice(0, 5)) + '</small>' : '') + '</td>' +
          '<td style="font-size:12px">' + MH.esc(p.source) +
            (p.publish_mode === 'auto' ? ' → ' + MH.esc(p.destination_name || 'no destination') : ' · manual') + '</td>' +
          '<td>' + MH.statusBadge(p.status) + appr + '</td>' +
          '<td style="text-align:right"><button data-open="' + MH.esc(p.id) + '">' +
            (IS_ADMIN && p.status === 'review' ? 'Review' : 'View') + '</button></td></tr>';
      }).join('') + '</tbody></table>';

    el.querySelectorAll('[data-open]').forEach(function (b) {
      b.addEventListener('click', function () { openReview(b.dataset.open); });
    });
  }

  function openReview(id) {
    const p = rows.find(function (x) { return x.id === id; });
    if (!p) return;
    document.getElementById('revPostId').value = p.id;
    document.getElementById('revNote').value = '';
    document.getElementById('reviewBody').innerHTML =
      '<div class="row" style="margin-bottom:10px">' +
        MH.platformBadge(p.platform) + MH.statusBadge(p.status) +
        '<span class="tag">' + MH.esc(p.brand_name) + '</span>' +
        (p.publish_mode === 'auto'
          ? '<span class="tag">auto → ' + MH.esc(p.destination_name || '—') + '</span>'
          : '<span class="tag">manual</span>') +
      '</div>' +
      '<h2 style="font-size:16px">' + MH.esc(p.title) + '</h2>' +
      '<div style="white-space:pre-wrap;background:#0f1724;border:1px solid var(--line);border-radius:8px;padding:14px;font-size:13.5px;max-height:320px;overflow-y:auto">' +
        (p.content ? MH.esc(p.content) : '<span class="muted">No content body.</span>') +
      '</div>' +
      (p.image_url ? '<p style="margin-top:8px;font-size:12px"><a href="' + MH.esc(p.image_url) + '" target="_blank" rel="noopener">Attached image</a></p>' : '') +
      '<p class="muted" style="font-size:12px;margin-top:8px">Scheduled: ' + MH.fmtDate(p.post_date) +
        (p.publish_time ? ' ' + MH.esc(p.publish_time.slice(0, 5)) + ' AZ' : '') + '</p>';
    MH.openModal('mReview');
  }

  async function decide(action, queue) {
    const id = document.getElementById('revPostId').value;
    const note = document.getElementById('revNote').value.trim();
    if (action === 'reject' && !note) {
      MH.toast('Write a note so the author knows what to fix.', 'err');
      return;
    }
    try {
      const r = await MH.api('posts', { action: action, id: id, note: note, queue: !!queue }, 'POST');
      MH.closeModal('mReview');
      MH.toast(action === 'reject' ? 'Post rejected.' : (r.status === 'scheduled' ? 'Approved and queued.' : 'Post approved.'));
      load();
    } catch (e) { MH.toast(e.message, 'err'); }
  }

  document.addEventListener('DOMContentLoaded', function () {
    if (IS_ADMIN) {
      document.getElementById('btnApprove').addEventListener('click', function () { decide('approve', false); });
      document.getElementById('btnApproveQueue').addEventListener('click', function () { decide('approve', true); });
      document.getElementById('btnReject').addEventListener('click', function () { decide('reject', false); });
    }
    load();
  });
})();
