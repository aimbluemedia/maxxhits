/* ============================================================
   dashboard.js — Dashboard page
   ============================================================ */
(function () {
  'use strict';

  async function load() {
    let d;
    try {
      d = await MH.api('dashboard');
    } catch (e) {
      MH.toast(e.message, 'err');
      return;
    }

    // Stat tiles
    const rev = document.getElementById('dsRevenue');
    if (rev && d.revenue) rev.textContent = MH.money(d.revenue.total_monthly_revenue);
    document.getElementById('dsCompanies').textContent = d.summary.length;
    document.getElementById('dsReview').textContent = d.automation.in_review;
    document.getElementById('dsQueued').textContent = d.automation.queued;
    document.getElementById('dsPublishedToday').textContent = d.automation.published_today;
    document.getElementById('dsFailed').textContent = d.automation.failed;

    // Pipeline strip: Draft → Review → Approved → Queued → Published today
    const pipe = document.getElementById('dashPipeline');
    if (pipe) {
      const stages = [
        ['Drafts', d.automation.drafts, 'st-draft', 'content-studio.php'],
        ['In review', d.automation.in_review, 'st-review', 'approvals.php'],
        ['Approved', d.automation.approved, 'st-approved', 'approvals.php'],
        ['Auto queue', d.automation.queued, 'st-scheduled', 'automation.php'],
        ['Published today', d.automation.published_today, 'st-published', 'automation.php'],
        ['Failed', d.automation.failed, 'st-failed', 'automation.php']
      ];
      pipe.innerHTML = stages.map(function (s, i) {
        return (i ? '<span style="color:#cbd5e1;align-self:center">→</span>' : '') +
          '<a href="' + s[3] + '" style="text-decoration:none" class="badge-status ' + s[2] + '">' +
          s[0] + ': <strong>' + s[1] + '</strong></a>';
      }).join('');
    }

    renderToday(d.today);
    renderSummary(d.summary);
    if (document.getElementById('dashTeam')) renderTeam(d.team);
  }

  function renderToday(rows) {
    const el = document.getElementById('dashToday');
    if (!rows.length) {
      el.innerHTML = '<div class="text-muted" style="font-size:13px">No companies post today.</div>';
      return;
    }
    el.innerHTML =
      '<div class="tbl-wrap"><table><thead><tr>' +
      '<th>Company</th><th>Content manager</th><th>Sales person</th><th>Posts / month</th>' +
      '</tr></thead><tbody>' +
      rows.map(function (c) {
        return '<tr>' +
          '<td><a href="company.php?id=' + encodeURIComponent(c.company_id) + '" style="text-decoration:none;color:inherit">' +
            '<span class="co-dot me-2" style="background:' + MH.esc(c.color) + '">' + MH.initials(c.name) + '</span>' +
            '<strong>' + MH.esc(c.name) + '</strong></a></td>' +
          '<td>' + MH.esc(c.content_manager || '—') + '</td>' +
          '<td>' + MH.esc(c.sales_person || '—') + '</td>' +
          '<td>' + MH.esc(c.monthly_posts || '—') + '</td>' +
          '</tr>';
      }).join('') +
      '</tbody></table></div>';
  }

  function renderSummary(rows) {
    const el = document.getElementById('dashSummary');
    if (!rows.length) {
      el.innerHTML = '<div class="text-muted" style="font-size:13px">No companies yet — add one on the Companies page.</div>';
      return;
    }
    el.innerHTML =
      '<div class="tbl-wrap"><table><thead><tr>' +
      '<th>Company</th><th>Published</th><th>Scheduled</th><th>Target</th><th>Fee</th><th>CM</th><th>SP</th>' +
      '</tr></thead><tbody>' +
      rows.map(function (c) {
        const target = c.monthly_posts ? c.published + ' / ' + c.monthly_posts : String(c.published);
        return '<tr>' +
          '<td><a href="company.php?id=' + encodeURIComponent(c.id) + '" style="text-decoration:none;color:inherit">' +
            '<span class="co-dot me-2" style="background:' + MH.esc(c.color) + '">' + MH.initials(c.name) + '</span>' +
            '<strong>' + MH.esc(c.name) + '</strong></a></td>' +
          '<td>' + target + '</td>' +
          '<td>' + c.scheduled + '</td>' +
          '<td>' + MH.esc(c.monthly_posts || '—') + '</td>' +
          '<td>' + MH.money(c.fee) + '</td>' +
          '<td>' + MH.esc(c.content_manager || '—') + '</td>' +
          '<td>' + MH.esc(c.sales_person || '—') + '</td>' +
          '</tr>';
      }).join('') +
      '</tbody></table></div>';
  }

  function renderTeam(rows) {
    const el = document.getElementById('dashTeam');
    if (!rows.length) {
      el.innerHTML = '<div class="text-muted" style="font-size:13px">No team members linked to companies yet.</div>';
      return;
    }
    el.innerHTML =
      '<div class="tbl-wrap"><table><thead><tr>' +
      '<th>Name</th><th>Role</th><th>Companies</th><th>Monthly posts</th><th>Monthly earnings</th>' +
      '</tr></thead><tbody>' +
      rows.map(function (p) {
        const role = p.role === 'content_manager' ? 'Content Manager' : 'Sales Person';
        return '<tr>' +
          '<td><strong>' + MH.esc(p.name) + '</strong></td>' +
          '<td>' + role + '</td>' +
          '<td>' + p.num_companies + '</td>' +
          '<td>' + MH.esc(p.total_monthly_posts || 0) + '</td>' +
          '<td><strong>' + MH.money(p.total_monthly_earnings) + '</strong></td>' +
          '</tr>';
      }).join('') +
      '</tbody></table></div>';
  }

  document.addEventListener('DOMContentLoaded', load);
})();
