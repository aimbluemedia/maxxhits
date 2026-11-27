/* ============================================================
   approvals.js — Approval Center page
   ============================================================ */
(function () {
  'use strict';

  const page = document.getElementById('approvalsPage');
  if (!page) return;
  const IS_ADMIN = page.dataset.isAdmin === '1';

  let rows = [];

  async function load() {
    try {
      rows = await MH.api('posts', { action: 'review_queue' });
    } catch (e) {
      MH.toast(e.message, 'err');
      return;
    }
    const counts = { review: 0, approved: 0, rejected: 0 };
    rows.forEach(function (p) { if (counts[p.status] !== undefined) counts[p.status]++; });
    document.getElementById('apReview').textContent = counts.review;
    document.getElementById('apApproved').textContent = counts.approved;
    document.getElementById('apRejected').textContent = counts.rejected;
    render();
  }

  function render() {
    const el = document.getElementById('approvalsList');
    if (!rows.length) {
      el.innerHTML =
        '<div class="state-box"><i class="bi bi-clipboard-check"></i>' +
        '<strong>Nothing to review</strong>' +
        '<p>Posts submitted for review from Content Studio or a company board will appear here.</p></div>';
      return;
    }
    el.innerHTML =
      '<div class="tbl-wrap"><table><thead><tr>' +
      '<th>Post</th><th>Company</th><th>Platform</th><th>Scheduled for</th><th>Mode</th><th>Status</th><th></th>' +
      '</tr></thead><tbody>' +
      rows.map(function (p) {
        const note = p.review_note
          ? '<div style="font-size:11px;color:#b45309"><i class="bi bi-chat-left-text me-1"></i>' + MH.esc(p.review_note) + '</div>'
          : '';
        const appr = p.approved_by && p.status === 'approved'
          ? '<div style="font-size:11px;color:#15803d">by ' + MH.esc(p.approved_by) + '</div>'
          : '';
        return '<tr>' +
          '<td><strong>' + MH.esc(p.title) + '</strong>' +
            (p.ai_generated == 1 ? ' <span class="tagline-ai" title="AI generated"><i class="bi bi-stars" style="color:#a855f7"></i></span>' : '') +
            note + '</td>' +
          '<td><span class="co-dot me-2" style="background:' + MH.esc(p.company_color) + ';width:20px;height:20px;font-size:9px">' +
            MH.initials(p.company_name) + '</span>' + MH.esc(p.company_name) + '</td>' +
          '<td>' + MH.platformBadge(p.platform) + '</td>' +
          '<td style="white-space:nowrap">' + MH.fmtDate(p.post_date) +
            (p.publish_time ? ' <span class="text-muted" style="font-size:11px">' + MH.esc(p.publish_time.slice(0, 5)) + '</span>' : '') + '</td>' +
          '<td style="font-size:12px">' + (p.publish_mode === 'auto'
            ? '<i class="bi bi-robot" style="color:#e11d48"></i> ' + MH.esc(p.channel_name || 'no channel')
            : '<span class="text-muted">Manual</span>') + '</td>' +
          '<td>' + MH.statusBadge(p.status) + appr + '</td>' +
          '<td class="text-end"><button class="btn btn-sm btn-outline-secondary" data-open="' + MH.esc(p.id) + '">' +
            (IS_ADMIN && p.status === 'review' ? 'Review' : 'View') + '</button></td>' +
          '</tr>';
      }).join('') +
      '</tbody></table></div>';

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
      '<div class="d-flex gap-2 flex-wrap mb-2">' +
        MH.platformBadge(p.platform) + MH.statusBadge(p.status) +
        '<span class="tag" style="font-size:11px;border:1px solid #e2e8f0;border-radius:20px;padding:3px 9px">' + MH.esc(p.company_name) + '</span>' +
        (p.publish_mode === 'auto'
          ? '<span style="font-size:12px"><i class="bi bi-robot" style="color:#e11d48"></i> via ' + MH.esc(p.channel_name || '—') + '</span>'
          : '<span class="text-muted" style="font-size:12px">manual</span>') +
      '</div>' +
      '<h5 style="font-size:16px">' + MH.esc(p.title) + '</h5>' +
      '<div style="white-space:pre-wrap;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:14px;font-size:13.5px;max-height:320px;overflow-y:auto">' +
        (p.content ? MH.esc(p.content) : '<span class="text-muted">No content body — tracking-only post.</span>') +
      '</div>' +
      (p.image_url
        ? '<div class="mt-2"><a href="' + MH.esc(p.image_url) + '" target="_blank" rel="noopener" style="font-size:12px"><i class="bi bi-image me-1"></i>Attached image</a></div>'
        : '') +
      '<div class="mt-2 text-muted" style="font-size:12px">Scheduled: ' + MH.fmtDate(p.post_date) +
        (p.publish_time ? ' ' + MH.esc(p.publish_time.slice(0, 5)) : '') +
        (p.assignee ? ' · Assignee: ' + MH.esc(p.assignee) : '') + '</div>';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('mReview')).show();
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
      bootstrap.Modal.getInstance(document.getElementById('mReview')).hide();
      MH.toast(action === 'reject' ? 'Post rejected.' : (r.status === 'scheduled' ? 'Approved and queued.' : 'Post approved.'), 'ok');
      load();
    } catch (e) {
      MH.toast(e.message, 'err');
    }
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
