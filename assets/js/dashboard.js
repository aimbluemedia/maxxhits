/* ============================================================
   dashboard.js — Overview page
   ============================================================ */
(function () {
  'use strict';

  async function load() {
    let d;
    try { d = await MH.api('dashboard'); }
    catch (e) { MH.toast(e.message, 'err'); return; }

    document.getElementById('tReview').textContent = d.pipeline.in_review;
    document.getElementById('tScheduled').textContent = d.pipeline.scheduled;
    document.getElementById('tToday').textContent = d.pipeline.published_today;
    document.getElementById('tFailed').textContent = d.pipeline.failed;
    document.getElementById('tAutos').textContent = d.automations.enabled + ' / ' + d.automations.total;

    const stages = [
      ['Drafts', d.pipeline.drafts, 'content-studio.php'],
      ['In review', d.pipeline.in_review, 'approvals.php'],
      ['Approved', d.pipeline.approved, 'approvals.php'],
      ['Scheduled', d.pipeline.scheduled, 'queue.php'],
      ['Published', d.pipeline.published_total, 'queue.php'],
      ['Failed', d.pipeline.failed, 'queue.php']
    ];
    document.getElementById('ovPipeline').innerHTML = stages.map(function (s) {
      return '<a class="node" href="' + s[2] + '" style="text-decoration:none;color:inherit">' +
        '<small>' + s[0] + '</small><div class="num">' + s[1] + '</div></a>';
    }).join('');

    const bEl = document.getElementById('ovBrands');
    bEl.innerHTML = d.brands.length
      ? '<table><thead><tr><th>Brand</th><th>Review</th><th>Scheduled</th><th>Published</th><th>Failed</th></tr></thead><tbody>' +
        d.brands.map(function (b) {
          return '<tr><td>' + MH.brandDot(b.color, b.name) + '</td>' +
            '<td>' + b.in_review + '</td><td>' + b.scheduled + '</td>' +
            '<td>' + b.published + '</td><td>' + (b.failed ? '<span class="err">' + b.failed + '</span>' : '0') + '</td></tr>';
        }).join('') + '</tbody></table>'
      : '<div class="empty">No brands yet — add one under Administration → Brands.</div>';

    const fEl = document.getElementById('ovFailures');
    fEl.innerHTML = d.failures.length
      ? d.failures.map(function (f) {
          return '<div style="padding:7px 0;border-bottom:1px solid var(--line);font-size:13px">' +
            '<strong>' + MH.esc(f.title) + '</strong> <small class="muted">· ' + MH.esc(f.brand_name) + '</small>' +
            '<div class="err" style="font-size:12px">' + MH.esc(f.publish_error || '') + '</div></div>';
        }).join('')
      : '<div class="empty">No failed posts. All clear.</div>';

    const rEl = document.getElementById('ovRuns');
    rEl.innerHTML = d.recentRuns.length
      ? d.recentRuns.map(function (r) {
          const cls = r.status === 'success' ? 'ok' : (r.status === 'error' ? 'err' : 'warn');
          return '<div style="padding:6px 0;border-bottom:1px solid var(--line);font-size:12.5px">' +
            '<span class="' + cls + '">●</span> <strong>' + MH.esc(r.automation_name) + '</strong> — ' +
            MH.esc(r.message || r.status) + ' <small class="muted">' + MH.esc(r.created_at) + '</small></div>';
        }).join('')
      : '<div class="empty">No automation runs yet.</div>';
  }

  document.addEventListener('DOMContentLoaded', load);
})();
