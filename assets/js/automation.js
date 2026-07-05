/* ============================================================
   automation.js — Automation hub page
   Channels, auto-publish queue, activity log, cron info.
   ============================================================ */
(function () {
  'use strict';

  const page = document.getElementById('automationPage');
  if (!page) return;

  const IS_ADMIN = page.dataset.isAdmin === '1';

  // Credential fields per platform: [key, label, placeholder, help]
  const CONFIG_FIELDS = {
    wordpress: [
      ['site_url', 'Site URL', 'https://yourblog.com', 'The WordPress site address.'],
      ['username', 'WP username', 'admin', 'The WordPress user that will own the posts.'],
      ['app_password', 'Application password', 'xxxx xxxx xxxx xxxx', 'WP Admin → Users → Profile → Application Passwords.']
    ],
    facebook: [
      ['page_id', 'Page ID', '123456789', 'Meta Business Suite → Page settings.'],
      ['access_token', 'Page access token', 'EAAB…', 'Long-lived PAGE token with pages_manage_posts.']
    ],
    instagram: [
      ['ig_user_id', 'IG business account ID', '178414…', 'Instagram professional account linked to a FB page.'],
      ['access_token', 'Access token', 'EAAB…', 'Token with instagram_content_publish. Posts REQUIRE an image URL.']
    ],
    twitter: [
      ['access_token', 'OAuth 2.0 access token', '…', 'User token with tweet.write scope (expires — refresh when 401s appear).']
    ],
    linkedin: [
      ['author_urn', 'Author URN', 'urn:li:organization:12345', 'urn:li:organization:<id> for a page, urn:li:person:<id> for a profile.'],
      ['access_token', 'Access token', '…', 'Token with w_organization_social or w_member_social scope.']
    ]
  };

  let channels = [];
  let companies = [];

  async function loadAll() {
    loadChannels();
    loadQueue();
    loadLogs();
    if (IS_ADMIN) loadCron();
  }

  // ── Cron info ─────────────────────────────────────────────

  async function loadCron() {
    try {
      const info = await MH.api('automation', { action: 'cron_info' });
      document.getElementById('cronUrl').value = info.cron_url;
    } catch (e) {
      document.getElementById('cronUrl').value = 'Unavailable: ' + e.message;
    }
  }

  // ── Channels ──────────────────────────────────────────────

  async function loadChannels() {
    try {
      const res = await Promise.all([
        MH.api('automation', { action: 'channels' }),
        MH.api('companies')
      ]);
      channels = res[0];
      companies = res[1];
    } catch (e) {
      MH.toast(e.message, 'err');
      return;
    }
    renderChannels();
  }

  function renderChannels() {
    const el = document.getElementById('channelsList');
    if (!channels.length) {
      el.innerHTML =
        '<div class="state-box"><i class="bi bi-broadcast"></i>' +
        '<strong>No publishing channels yet</strong>' +
        '<p>' + (IS_ADMIN
          ? 'Add a WordPress site or social account to start auto-posting.'
          : 'An admin needs to connect WordPress/social accounts first.') + '</p></div>';
      return;
    }
    el.innerHTML =
      '<div class="tbl-wrap"><table><thead><tr>' +
      '<th>Channel</th><th>Platform</th><th>Company</th><th>Status</th><th>Last test</th>' +
      (IS_ADMIN ? '<th></th>' : '') +
      '</tr></thead><tbody>' +
      channels.map(function (ch) {
        const test = ch.last_test_at
          ? (ch.last_test_ok
              ? '<span class="badge-status st-published">OK</span>'
              : '<span class="badge-status st-failed">Failed</span>') +
            ' <span class="text-muted" style="font-size:11px">' + MH.esc(ch.last_test_at) + '</span>'
          : '<span class="text-muted">never</span>';
        return '<tr>' +
          '<td><strong>' + MH.esc(ch.name) + '</strong></td>' +
          '<td>' + MH.platformBadge(ch.platform) + '</td>' +
          '<td><span class="co-dot me-2" style="background:' + MH.esc(ch.company_color) + ';width:20px;height:20px;font-size:9px">' +
            MH.initials(ch.company_name) + '</span>' + MH.esc(ch.company_name) + '</td>' +
          '<td>' + (ch.is_active
              ? '<span class="badge-status st-published">Active</span>'
              : '<span class="badge-status st-draft">Inactive</span>') + '</td>' +
          '<td style="font-size:12px">' + test + '</td>' +
          (IS_ADMIN
            ? '<td class="text-end" style="white-space:nowrap">' +
              '<button class="btn btn-sm btn-outline-success me-1" data-test="' + MH.esc(ch.id) + '" title="Test connection"><i class="bi bi-plug"></i></button>' +
              '<button class="btn btn-sm btn-outline-secondary me-1" data-edit="' + MH.esc(ch.id) + '" title="Edit"><i class="bi bi-pencil"></i></button>' +
              '<button class="btn btn-sm btn-outline-danger" data-del="' + MH.esc(ch.id) + '" title="Delete"><i class="bi bi-trash"></i></button></td>'
            : '') +
          '</tr>';
      }).join('') +
      '</tbody></table></div>';

    if (!IS_ADMIN) return;
    el.querySelectorAll('[data-test]').forEach(function (b) {
      b.addEventListener('click', function () { testChannel(b.dataset.test, b); });
    });
    el.querySelectorAll('[data-edit]').forEach(function (b) {
      b.addEventListener('click', function () { openChannelModal(b.dataset.edit); });
    });
    el.querySelectorAll('[data-del]').forEach(function (b) {
      b.addEventListener('click', function () { delChannel(b.dataset.del); });
    });
  }

  function renderConfigFields(platform, isEdit) {
    const fields = CONFIG_FIELDS[platform] || [];
    document.getElementById('chConfigFields').innerHTML = fields.map(function (f) {
      const secret = f[0].indexOf('password') !== -1 || f[0].indexOf('token') !== -1;
      return '<div class="mb-2">' +
        '<label class="form-label">' + MH.esc(f[1]) +
        (isEdit && secret ? ' <span class="text-muted fw-normal">(leave blank to keep current)</span>' : '') +
        '</label>' +
        '<input class="form-control" data-cfg="' + MH.esc(f[0]) + '" placeholder="' + MH.esc(f[2]) + '"' +
        (secret ? ' type="password" autocomplete="new-password"' : '') + '>' +
        '<div class="form-text">' + MH.esc(f[3]) + '</div></div>';
    }).join('');
  }

  function openChannelModal(id) {
    const ch = id ? channels.find(function (x) { return x.id === id; }) : null;
    document.getElementById('mChannelTitle').innerHTML =
      '<i class="bi bi-broadcast me-2"></i>' + (ch ? 'Edit channel' : 'Add channel');
    document.getElementById('chId').value = ch ? ch.id : '';
    document.getElementById('chCompany').innerHTML = companies.map(function (c) {
      return '<option value="' + MH.esc(c.id) + '">' + MH.esc(c.name) + '</option>';
    }).join('');
    if (ch) document.getElementById('chCompany').value = ch.company_id;
    document.getElementById('chPlatform').value = ch ? ch.platform : 'wordpress';
    document.getElementById('chName').value = ch ? ch.name : '';
    document.getElementById('chActive').checked = ch ? !!ch.is_active : true;
    renderConfigFields(document.getElementById('chPlatform').value, !!ch);
    bootstrap.Modal.getOrCreateInstance(document.getElementById('mChannel')).show();
  }

  async function saveChannel() {
    const config = {};
    document.querySelectorAll('#chConfigFields [data-cfg]').forEach(function (i) {
      config[i.dataset.cfg] = i.value;
    });
    const payload = {
      action: 'save_channel',
      id: document.getElementById('chId').value || null,
      company_id: document.getElementById('chCompany').value,
      platform: document.getElementById('chPlatform').value,
      name: document.getElementById('chName').value.trim(),
      is_active: document.getElementById('chActive').checked,
      config: config
    };
    if (!payload.name) { MH.toast('Channel name is required.', 'err'); return; }
    try {
      await MH.api('automation', payload, 'POST');
      bootstrap.Modal.getInstance(document.getElementById('mChannel')).hide();
      MH.toast('Channel saved.', 'ok');
      loadChannels();
    } catch (e) {
      MH.toast(e.message, 'err');
    }
  }

  async function testChannel(id, btn) {
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    try {
      await MH.api('automation', { action: 'test_channel', id: id }, 'POST');
      MH.toast('Channel connection OK.', 'ok');
    } catch (e) {
      MH.toast(e.message, 'err');
    }
    loadChannels();
    loadLogs();
  }

  async function delChannel(id) {
    const ch = channels.find(function (x) { return x.id === id; });
    const yes = await MH.confirm('Delete channel "' + (ch ? ch.name : id) + '"? Posts pointed at it will stop auto-publishing.');
    if (!yes) return;
    try {
      await MH.api('automation', { action: 'delete_channel', id: id }, 'POST');
      MH.toast('Channel deleted.', 'ok');
      loadChannels();
    } catch (e) {
      MH.toast(e.message, 'err');
    }
  }

  // ── Queue ─────────────────────────────────────────────────

  async function loadQueue() {
    let queue;
    try {
      queue = await MH.api('automation', { action: 'queue' });
    } catch (e) {
      MH.toast(e.message, 'err');
      return;
    }
    const el = document.getElementById('queueList');
    if (!queue.length) {
      el.innerHTML =
        '<div class="state-box"><i class="bi bi-robot"></i>' +
        '<strong>Queue is empty</strong>' +
        '<p>Set a post to "Auto-publish via channel" on a company board and it will appear here.</p></div>';
      return;
    }
    el.innerHTML =
      '<div class="tbl-wrap"><table><thead><tr>' +
      '<th>When</th><th>Post</th><th>Company</th><th>Channel</th><th>Status</th><th>Result</th>' +
      '</tr></thead><tbody>' +
      queue.map(function (q) {
        const when = (q.post_date ? MH.fmtDate(q.post_date) : '<span class="text-muted">no date</span>') +
          (q.publish_time ? ' <span class="text-muted" style="font-size:11px">' + MH.esc(q.publish_time.slice(0, 5)) + '</span>' : '');
        let result = '—';
        if (q.status === 'published') {
          result = q.post_url
            ? '<a href="' + MH.esc(q.post_url) + '" target="_blank" rel="noopener" style="font-size:12px">View post <i class="bi bi-box-arrow-up-right"></i></a>'
            : '<span class="text-muted" style="font-size:12px">published ' + MH.esc(q.published_at || '') + '</span>';
        } else if (q.status === 'failed') {
          result = '<span style="font-size:11px;color:#b91c1c">' + MH.esc(q.publish_error || 'failed') +
            (q.publish_attempts > 1 ? ' (attempt ' + q.publish_attempts + ')' : '') + '</span>';
        }
        return '<tr>' +
          '<td style="white-space:nowrap">' + when + '</td>' +
          '<td><a href="company.php?id=' + encodeURIComponent(q.company_id) + '" style="text-decoration:none;color:inherit"><strong>' + MH.esc(q.title) + '</strong></a></td>' +
          '<td><span class="co-dot me-2" style="background:' + MH.esc(q.company_color) + ';width:20px;height:20px;font-size:9px">' +
            MH.initials(q.company_name) + '</span>' + MH.esc(q.company_name) + '</td>' +
          '<td style="font-size:12px">' + (q.channel_name
              ? MH.esc(q.channel_name) + ' <span class="text-muted">(' + MH.esc(q.channel_platform) + ')</span>'
              : '<span style="color:#b91c1c">no channel</span>') + '</td>' +
          '<td>' + MH.statusBadge(q.status) + '</td>' +
          '<td>' + result + '</td>' +
          '</tr>';
      }).join('') +
      '</tbody></table></div>';
  }

  // ── Logs ──────────────────────────────────────────────────

  async function loadLogs() {
    let logs;
    try {
      logs = await MH.api('automation', { action: 'logs' });
    } catch (e) {
      MH.toast(e.message, 'err');
      return;
    }
    const el = document.getElementById('logsList');
    if (!logs.length) {
      el.innerHTML = '<div class="text-muted" style="font-size:13px">No automation activity yet.</div>';
      return;
    }
    const icons = { success: 'bi-check-circle text-success', error: 'bi-x-circle text-danger', info: 'bi-info-circle text-secondary' };
    el.innerHTML =
      '<div style="max-height:320px;overflow-y:auto;font-size:12.5px">' +
      logs.map(function (l) {
        return '<div class="d-flex gap-2 py-1 border-bottom" style="border-color:#f1f5f9!important">' +
          '<i class="bi ' + (icons[l.level] || icons.info) + '"></i>' +
          '<div style="flex:1">' + MH.esc(l.message) +
            (l.company_name ? ' <span class="text-muted">— ' + MH.esc(l.company_name) + '</span>' : '') +
          '</div>' +
          '<span class="text-muted" style="white-space:nowrap">' + MH.esc(l.created_at) + '</span>' +
          '</div>';
      }).join('') +
      '</div>';
  }

  // ── Run due now (admin) ───────────────────────────────────

  async function runDue(btn) {
    btn.disabled = true;
    const orig = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Running…';
    try {
      const r = await MH.api('automation', { action: 'run_due' }, 'POST');
      const ok = r.results.filter(function (x) { return x.ok; }).length;
      const fail = r.results.length - ok;
      MH.toast(r.processed === 0
        ? 'Nothing due right now.'
        : ok + ' published, ' + fail + ' failed.', fail ? 'err' : 'ok');
    } catch (e) {
      MH.toast(e.message, 'err');
    }
    btn.disabled = false;
    btn.innerHTML = orig;
    loadQueue();
    loadLogs();
  }

  document.addEventListener('DOMContentLoaded', function () {
    if (IS_ADMIN) {
      MH.topbarButton(
        '<button class="btn btn-sm btn-mh"><i class="bi bi-plus-lg me-1"></i>Add channel</button>',
        function () { openChannelModal(null); }
      );
      document.getElementById('btnSaveChannel').addEventListener('click', saveChannel);
      document.getElementById('chPlatform').addEventListener('change', function () {
        renderConfigFields(this.value, !!document.getElementById('chId').value);
      });
      document.getElementById('btnRunDue').addEventListener('click', function () { runDue(this); });
      document.getElementById('btnCopyCron').addEventListener('click', function () {
        const input = document.getElementById('cronUrl');
        input.select();
        navigator.clipboard.writeText(input.value).then(function () {
          MH.toast('Cron URL copied.', 'ok');
        }, function () {
          document.execCommand('copy');
          MH.toast('Cron URL copied.', 'ok');
        });
      });
    }
    loadAll();
  });
})();
