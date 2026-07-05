/* ============================================================
   company.js — Single-company content board
   ============================================================ */
(function () {
  'use strict';

  const page = document.getElementById('companyPage');
  if (!page) return;

  const COMPANY_ID = page.dataset.companyId;
  const CAN_EDIT = page.dataset.canEdit === '1';

  let posts = [];
  let channels = [];
  let company = null;
  let curYear = null;   // null = all dates
  let curMonth = null;
  let platformFilter = '';

  const now = new Date();
  curYear = now.getFullYear();
  curMonth = now.getMonth() + 1;

  // ── Loading ───────────────────────────────────────────────

  async function loadStatic() {
    try {
      const res = await Promise.all([
        MH.api('companies', { action: 'get', id: COMPANY_ID }),
        MH.api('automation', { action: 'channels' })
      ]);
      company = res[0];
      channels = res[1].filter(function (ch) { return ch.company_id === COMPANY_ID && ch.is_active; });
    } catch (e) {
      MH.toast(e.message, 'err');
    }
    renderDetail();
  }

  async function loadPosts() {
    const params = { company_id: COMPANY_ID };
    if (curYear) { params.year = curYear; params.month = curMonth; }
    try {
      posts = await MH.api('posts', params);
    } catch (e) {
      MH.toast(e.message, 'err');
      return;
    }
    renderStats();
    renderPlatformFilter();
    renderPosts();
  }

  // ── Rendering ─────────────────────────────────────────────

  function renderDetail() {
    if (!company) return;
    const el = document.getElementById('coDetailCard');
    const bits = [];
    if (company.content_manager_name) bits.push('<strong>CM:</strong> ' + MH.esc(company.content_manager_name));
    if (company.sales_person_name) bits.push('<strong>SP:</strong> ' + MH.esc(company.sales_person_name));
    if (company.monthly_posts) bits.push('<strong>Target:</strong> ' + company.monthly_posts + ' posts/mo');
    if (company.posting_days && company.posting_days.length) bits.push('<strong>Days:</strong> ' + MH.esc(company.posting_days.join(', ')));
    if (bits.length) {
      el.style.display = '';
      el.innerHTML = '<div style="font-size:13px;color:#475569;display:flex;gap:18px;flex-wrap:wrap">' + bits.join(' <span style="color:#cbd5e1">|</span> ') + '</div>';
    }
  }

  function renderStats() {
    const c = { total: posts.length, published: 0, scheduled: 0, failed: 0 };
    posts.forEach(function (p) {
      if (p.status === 'published') c.published++;
      else if (p.status === 'scheduled' || p.status === 'publishing') c.scheduled++;
      else if (p.status === 'failed') c.failed++;
    });
    document.getElementById('csTotal').textContent = c.total;
    document.getElementById('csPublished').textContent = c.published;
    document.getElementById('csScheduled').textContent = c.scheduled;
    document.getElementById('csFailed').textContent = c.failed;
  }

  function monthLabel() {
    if (!curYear) return 'All dates';
    return new Date(curYear, curMonth - 1, 1)
      .toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
  }

  function renderPlatformFilter() {
    const el = document.getElementById('platformFilter');
    const platforms = [];
    posts.forEach(function (p) { if (platforms.indexOf(p.platform) === -1) platforms.push(p.platform); });
    el.innerHTML = platforms.map(function (pf) {
      const active = platformFilter === pf ? ' style="outline:2px solid #6c47ff;border-radius:6px"' : '';
      return '<button class="btn btn-sm p-0 border-0 bg-transparent" data-pf="' + MH.esc(pf) + '"' + active + '>' + MH.platformBadge(pf) + '</button>';
    }).join('');
    el.querySelectorAll('[data-pf]').forEach(function (b) {
      b.addEventListener('click', function () {
        platformFilter = platformFilter === b.dataset.pf ? '' : b.dataset.pf;
        renderPlatformFilter();
        renderPosts();
      });
    });
  }

  function renderPosts() {
    document.getElementById('monthLabel').textContent = monthLabel();
    const el = document.getElementById('postsList');
    const rows = platformFilter ? posts.filter(function (p) { return p.platform === platformFilter; }) : posts;

    if (!rows.length) {
      el.innerHTML =
        '<div class="state-box"><i class="bi bi-file-earmark-text"></i>' +
        '<strong>No posts for ' + MH.esc(monthLabel()) + '</strong>' +
        '<p>' + (CAN_EDIT ? 'Use "Add post" to schedule content.' : 'Nothing scheduled here yet.') + '</p></div>';
      return;
    }

    el.innerHTML =
      '<div class="tbl-wrap"><table><thead><tr>' +
      '<th>Date</th><th>Title</th><th>Platform</th><th>Status</th><th>Mode</th><th>Assignee</th><th>Link</th>' +
      (CAN_EDIT ? '<th></th>' : '') +
      '</tr></thead><tbody>' +
      rows.map(function (p) {
        const mode = p.publish_mode === 'auto'
          ? '<span title="' + MH.esc(p.channel_name || 'no channel') + '"><i class="bi bi-robot" style="color:#e11d48"></i> Auto</span>'
          : '<span class="text-muted">Manual</span>';
        const link = p.post_url
          ? '<a href="' + MH.esc(p.post_url) + '" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i></a>'
          : '—';
        const err = p.status === 'failed' && p.publish_error
          ? '<div style="font-size:11px;color:#b91c1c;max-width:340px">' + MH.esc(p.publish_error) + '</div>'
          : '';
        return '<tr>' +
          '<td style="white-space:nowrap">' + MH.fmtDate(p.post_date) + (p.publish_time ? ' <span class="text-muted" style="font-size:11px">' + MH.esc(p.publish_time.slice(0, 5)) + '</span>' : '') + '</td>' +
          '<td><strong>' + MH.esc(p.title) + '</strong>' + err + '</td>' +
          '<td>' + MH.platformBadge(p.platform) + '</td>' +
          '<td>' + MH.statusBadge(p.status) + '</td>' +
          '<td style="font-size:12px">' + mode + '</td>' +
          '<td>' + MH.esc(p.assignee || '—') + '</td>' +
          '<td>' + link + '</td>' +
          (CAN_EDIT
            ? '<td class="text-end" style="white-space:nowrap">' +
              (p.publish_mode === 'auto' && (p.status === 'scheduled' || p.status === 'failed')
                ? '<button class="btn btn-sm btn-outline-success me-1" data-pub="' + MH.esc(p.id) + '" title="Publish now"><i class="bi bi-send"></i></button>'
                : '') +
              '<button class="btn btn-sm btn-outline-secondary me-1" data-edit="' + MH.esc(p.id) + '" title="Edit"><i class="bi bi-pencil"></i></button>' +
              '<button class="btn btn-sm btn-outline-danger" data-del="' + MH.esc(p.id) + '" title="Delete"><i class="bi bi-trash"></i></button></td>'
            : '') +
          '</tr>';
      }).join('') +
      '</tbody></table></div>';

    if (CAN_EDIT) {
      el.querySelectorAll('[data-edit]').forEach(function (b) {
        b.addEventListener('click', function () { openModal(b.dataset.edit); });
      });
      el.querySelectorAll('[data-del]').forEach(function (b) {
        b.addEventListener('click', function () { del(b.dataset.del); });
      });
      el.querySelectorAll('[data-pub]').forEach(function (b) {
        b.addEventListener('click', function () { publishNow(b.dataset.pub, b); });
      });
    }
  }

  // ── Post editor ───────────────────────────────────────────

  function fillChannels(selected) {
    const sel = document.getElementById('pChannel');
    sel.innerHTML = '<option value="">— pick a channel —</option>' +
      channels.map(function (ch) {
        return '<option value="' + MH.esc(ch.id) + '">' + MH.esc(ch.name) + ' (' + MH.esc(ch.platform) + ')</option>';
      }).join('');
    sel.value = selected || '';
  }

  function openModal(id) {
    const p = id ? posts.find(function (x) { return x.id === id; }) : null;
    document.getElementById('mPostTitle').innerHTML =
      '<i class="bi bi-file-plus me-2"></i>' + (p ? 'Edit post' : 'Add post');
    document.getElementById('pId').value = p ? p.id : '';
    document.getElementById('pTitle').value = p ? p.title : '';
    document.getElementById('pContent').value = p ? (p.content || '') : '';
    document.getElementById('pPlatform').value = p ? p.platform : 'blog';
    document.getElementById('pStatus').value = p && ['draft', 'scheduled', 'published'].indexOf(p.status) !== -1 ? p.status : 'scheduled';
    document.getElementById('pAssignee').value = p ? (p.assignee || '') : (company && company.content_manager_name) || '';
    document.getElementById('pDate').value = p ? (p.post_date || '') : '';
    document.getElementById('pTime').value = p && p.publish_time ? p.publish_time.slice(0, 5) : '';
    document.getElementById('pMode').value = p ? p.publish_mode : 'manual';
    document.getElementById('pImage').value = p ? (p.image_url || '') : '';
    document.getElementById('pUrl').value = p ? (p.post_url || '') : '';
    fillChannels(p ? p.channel_id : '');
    toggleChannel();
    bootstrap.Modal.getOrCreateInstance(document.getElementById('mPost')).show();
  }

  function toggleChannel() {
    document.getElementById('pChannelWrap').style.display =
      document.getElementById('pMode').value === 'auto' ? '' : 'none';
  }

  async function save() {
    const payload = {
      action: 'save',
      id: document.getElementById('pId').value || null,
      company_id: COMPANY_ID,
      title: document.getElementById('pTitle').value.trim(),
      content: document.getElementById('pContent').value,
      platform: document.getElementById('pPlatform').value,
      status: document.getElementById('pStatus').value,
      assignee: document.getElementById('pAssignee').value,
      post_date: document.getElementById('pDate').value || null,
      publish_time: document.getElementById('pTime').value || null,
      publish_mode: document.getElementById('pMode').value,
      channel_id: document.getElementById('pChannel').value || null,
      image_url: document.getElementById('pImage').value,
      post_url: document.getElementById('pUrl').value
    };
    if (!payload.title) { MH.toast('Title is required.', 'err'); return; }
    if (payload.publish_mode === 'auto' && !payload.channel_id) {
      MH.toast('Auto-publish needs a channel — create one on the Automation page.', 'err');
      return;
    }
    try {
      await MH.api('posts', payload, 'POST');
      bootstrap.Modal.getInstance(document.getElementById('mPost')).hide();
      MH.toast('Post saved.', 'ok');
      loadPosts();
    } catch (e) {
      MH.toast(e.message, 'err');
    }
  }

  async function del(id) {
    const p = posts.find(function (x) { return x.id === id; });
    const yes = await MH.confirm('Delete post "' + (p ? p.title : id) + '"?');
    if (!yes) return;
    try {
      await MH.api('posts', { action: 'delete', id: id }, 'POST');
      MH.toast('Post deleted.', 'ok');
      loadPosts();
    } catch (e) {
      MH.toast(e.message, 'err');
    }
  }

  async function publishNow(id, btn) {
    const p = posts.find(function (x) { return x.id === id; });
    const yes = await MH.confirm('Publish "' + (p ? p.title : id) + '" right now?', 'Publish');
    if (!yes) return;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    try {
      const r = await MH.api('automation', { action: 'publish_now', post_id: id }, 'POST');
      MH.toast('Published!' + (r.url ? ' ' + r.url : ''), 'ok');
    } catch (e) {
      MH.toast(e.message, 'err');
    }
    loadPosts();
  }

  // ── Month navigation ──────────────────────────────────────

  function shiftMonth(delta) {
    if (!curYear) {
      const n = new Date();
      curYear = n.getFullYear();
      curMonth = n.getMonth() + 1;
    }
    curMonth += delta;
    if (curMonth < 1) { curMonth = 12; curYear--; }
    if (curMonth > 12) { curMonth = 1; curYear++; }
    loadPosts();
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('btnPrevMonth').addEventListener('click', function () { shiftMonth(-1); });
    document.getElementById('btnNextMonth').addEventListener('click', function () { shiftMonth(1); });
    document.getElementById('btnAllMonths').addEventListener('click', function () {
      curYear = null; curMonth = null;
      loadPosts();
    });

    if (CAN_EDIT) {
      MH.topbarButton(
        '<button class="btn btn-sm btn-mh"><i class="bi bi-plus-lg me-1"></i>Add post</button>',
        function () { openModal(null); }
      );
      document.getElementById('btnSavePost').addEventListener('click', save);
      document.getElementById('pMode').addEventListener('change', toggleChannel);
    }

    loadStatic();
    loadPosts();
  });
})();
