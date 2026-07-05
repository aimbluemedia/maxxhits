/* ============================================================
   social-logins.js — Social credentials vault page
   ============================================================ */
(function () {
  'use strict';

  const isAdmin = !!document.getElementById('mLogin');
  let logins = [];
  let companies = [];

  async function load() {
    try {
      const res = await Promise.all([
        MH.api('social-logins'),
        MH.api('companies')
      ]);
      logins = res[0];
      companies = res[1];
    } catch (e) {
      MH.toast(e.message, 'err');
      return;
    }
    render();
  }

  function render() {
    const el = document.getElementById('loginsList');
    if (!logins.length) {
      el.innerHTML =
        '<div class="state-box"><i class="bi bi-key"></i>' +
        '<strong>No social logins saved</strong>' +
        '<p>' + (isAdmin ? 'Use "Add login" to store the first credential.' : 'No credentials are shared with your companies yet.') + '</p></div>';
      return;
    }
    el.innerHTML =
      '<div class="tbl-wrap"><table><thead><tr>' +
      '<th>Platform</th><th>Title</th><th>Username</th><th>Password</th><th>Companies</th><th>Link</th>' +
      (isAdmin ? '<th></th>' : '') +
      '</tr></thead><tbody>' +
      logins.map(function (l) {
        const pw = l.has_password
          ? '<span data-reveal="' + MH.esc(l.id) + '"><span class="text-muted">••••••••</span> ' +
            '<button class="btn btn-sm p-0 border-0" title="Reveal"><i class="bi bi-eye"></i></button></span>'
          : '<span class="text-muted">—</span>';
        return '<tr>' +
          '<td>' + MH.platformBadge(l.platform) + '</td>' +
          '<td><strong>' + MH.esc(l.title) + '</strong>' +
            (l.notes ? '<div style="font-size:11px;color:#94a3b8">' + MH.esc(l.notes) + '</div>' : '') + '</td>' +
          '<td>' + MH.esc(l.username || '—') + '</td>' +
          '<td>' + pw + '</td>' +
          '<td style="font-size:12px">' + MH.esc(l.company_names || '—') + '</td>' +
          '<td>' + (l.channel_url ? '<a href="' + MH.esc(l.channel_url) + '" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i></a>' : '—') + '</td>' +
          (isAdmin
            ? '<td class="text-end" style="white-space:nowrap">' +
              '<button class="btn btn-sm btn-outline-secondary me-1" data-edit="' + MH.esc(l.id) + '"><i class="bi bi-pencil"></i></button>' +
              '<button class="btn btn-sm btn-outline-danger" data-del="' + MH.esc(l.id) + '"><i class="bi bi-trash"></i></button></td>'
            : '') +
          '</tr>';
      }).join('') +
      '</tbody></table></div>';

    el.querySelectorAll('[data-reveal]').forEach(function (wrap) {
      wrap.querySelector('button').addEventListener('click', function () { reveal(wrap); });
    });
    el.querySelectorAll('[data-edit]').forEach(function (b) {
      b.addEventListener('click', function () { openModal(b.dataset.edit); });
    });
    el.querySelectorAll('[data-del]').forEach(function (b) {
      b.addEventListener('click', function () { del(b.dataset.del); });
    });
  }

  async function reveal(wrap) {
    try {
      const r = await MH.api('social-logins', { action: 'reveal', id: wrap.dataset.reveal });
      wrap.innerHTML = '<code>' + MH.esc(r.password || '(empty)') + '</code>';
    } catch (e) {
      MH.toast(e.message, 'err');
    }
  }

  function openModal(id) {
    const l = id ? logins.find(function (x) { return x.id === id; }) : null;
    document.getElementById('mLoginTitle').innerHTML =
      '<i class="bi bi-key me-2"></i>' + (l ? 'Edit social login' : 'Add social login');
    document.getElementById('slId').value = l ? l.id : '';
    document.getElementById('slPlatform').value = l ? l.platform : 'facebook';
    document.getElementById('slTitle').value = l ? l.title : '';
    document.getElementById('slUrl').value = l ? (l.channel_url || '') : '';
    document.getElementById('slUser').value = l ? (l.username || '') : '';
    document.getElementById('slPass').value = '';
    document.getElementById('slPassHint').textContent = l && l.has_password ? '(leave blank to keep current)' : '';
    document.getElementById('slNotes').value = l ? (l.notes || '') : '';
    const ids = l ? l.company_ids : [];
    document.getElementById('slCompanies').innerHTML = companies.map(function (c) {
      const checked = ids.indexOf(c.id) !== -1 ? ' checked' : '';
      return '<label class="form-check" style="font-size:13px">' +
        '<input class="form-check-input" type="checkbox" value="' + MH.esc(c.id) + '"' + checked + '> ' +
        MH.esc(c.name) + '</label>';
    }).join('') || '<span class="text-muted" style="font-size:12px">No companies yet.</span>';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('mLogin')).show();
  }

  async function save() {
    const ids = Array.prototype.slice.call(
      document.querySelectorAll('#slCompanies input:checked')
    ).map(function (i) { return i.value; });
    const payload = {
      action: 'save',
      id: document.getElementById('slId').value || null,
      platform: document.getElementById('slPlatform').value,
      title: document.getElementById('slTitle').value.trim(),
      channel_url: document.getElementById('slUrl').value.trim(),
      username: document.getElementById('slUser').value.trim(),
      password: document.getElementById('slPass').value || null,
      notes: document.getElementById('slNotes').value,
      company_ids: ids
    };
    if (!payload.title) { MH.toast('Title is required.', 'err'); return; }
    try {
      await MH.api('social-logins', payload, 'POST');
      bootstrap.Modal.getInstance(document.getElementById('mLogin')).hide();
      MH.toast('Saved.', 'ok');
      load();
    } catch (e) {
      MH.toast(e.message, 'err');
    }
  }

  async function del(id) {
    const l = logins.find(function (x) { return x.id === id; });
    const yes = await MH.confirm('Delete login "' + (l ? l.title : id) + '"?');
    if (!yes) return;
    try {
      await MH.api('social-logins', { action: 'delete', id: id }, 'POST');
      MH.toast('Deleted.', 'ok');
      load();
    } catch (e) {
      MH.toast(e.message, 'err');
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    if (isAdmin) {
      MH.topbarButton(
        '<button class="btn btn-sm btn-mh"><i class="bi bi-plus-lg me-1"></i>Add login</button>',
        function () { openModal(null); }
      );
      document.getElementById('btnSaveLogin').addEventListener('click', save);
    }
    load();
  });
})();
