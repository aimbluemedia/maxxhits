/* ============================================================
   users.js — User accounts page (admin only)
   ============================================================ */
(function () {
  'use strict';

  let users = [];
  let people = [];

  async function load() {
    try {
      const res = await Promise.all([
        MH.api('users'),
        MH.api('people')
      ]);
      users = res[0];
      people = res[1];
    } catch (e) {
      MH.toast(e.message, 'err');
      return;
    }
    render();
  }

  function roleBadges(u) {
    const roles = (u.role || '').split(',');
    return roles.map(function (r) {
      if (r === 'admin') return '<span class="badge" style="background:#6c47ff">Admin</span>';
      if (r === 'content_manager') return '<span class="badge" style="background:#0891b2">CM</span>';
      if (r === 'sales_person') return '<span class="badge" style="background:#16a34a">SP</span>';
      return '';
    }).join(' ');
  }

  function render() {
    const el = document.getElementById('usersList');
    el.innerHTML =
      '<div class="tbl-wrap"><table><thead><tr>' +
      '<th>Name</th><th>Username</th><th>Roles</th><th>Linked records</th><th>Last login</th><th>Status</th><th></th>' +
      '</tr></thead><tbody>' +
      users.map(function (u) {
        const linked = [u.cm_person_name ? 'CM: ' + u.cm_person_name : null,
                        u.sp_person_name ? 'SP: ' + u.sp_person_name : null]
                        .filter(Boolean).join(' · ') || '—';
        return '<tr>' +
          '<td><strong>' + MH.esc(u.full_name) + '</strong>' +
            (u.email ? '<div style="font-size:11px;color:#94a3b8">' + MH.esc(u.email) + '</div>' : '') + '</td>' +
          '<td><code>' + MH.esc(u.username) + '</code></td>' +
          '<td>' + roleBadges(u) + '</td>' +
          '<td style="font-size:12px">' + MH.esc(linked) + '</td>' +
          '<td style="font-size:12px;color:#64748b">' + (u.last_login ? MH.esc(u.last_login) : 'never') + '</td>' +
          '<td>' + (Number(u.is_active)
              ? '<span class="badge-status st-published">Active</span>'
              : '<span class="badge-status st-failed">Disabled</span>') + '</td>' +
          '<td class="text-end" style="white-space:nowrap">' +
            '<button class="btn btn-sm btn-outline-secondary me-1" data-edit="' + MH.esc(u.id) + '"><i class="bi bi-pencil"></i></button>' +
            (u.id !== 'usr_admin'
              ? '<button class="btn btn-sm btn-outline-danger" data-del="' + MH.esc(u.id) + '"><i class="bi bi-trash"></i></button>'
              : '') +
          '</td></tr>';
      }).join('') +
      '</tbody></table></div>';

    el.querySelectorAll('[data-edit]').forEach(function (b) {
      b.addEventListener('click', function () { openModal(b.dataset.edit); });
    });
    el.querySelectorAll('[data-del]').forEach(function (b) {
      b.addEventListener('click', function () { del(b.dataset.del); });
    });
  }

  function fillPersonSelects() {
    const cm = document.getElementById('uCmPerson');
    const sp = document.getElementById('uSpPerson');
    cm.innerHTML = '<option value="">— none —</option>';
    sp.innerHTML = '<option value="">— none —</option>';
    people.forEach(function (p) {
      const opt = '<option value="' + MH.esc(p.id) + '">' + MH.esc(p.name) + '</option>';
      if (p.role === 'content_manager') cm.insertAdjacentHTML('beforeend', opt);
      else sp.insertAdjacentHTML('beforeend', opt);
    });
  }

  function toggleRoleWraps() {
    const admin = document.getElementById('uIsAdmin').checked;
    document.getElementById('uCmWrap').style.opacity = admin ? '.4' : '1';
    document.getElementById('uSpWrap').style.opacity = admin ? '.4' : '1';
  }

  function openModal(id) {
    const u = id ? users.find(function (x) { return x.id === id; }) : null;
    fillPersonSelects();
    document.getElementById('mUserTitle').innerHTML =
      '<i class="bi bi-person-plus me-2"></i>' + (u ? 'Edit user' : 'Add user');
    document.getElementById('uId').value = u ? u.id : '';
    document.getElementById('uFullName').value = u ? u.full_name : '';
    document.getElementById('uUsername').value = u ? u.username : '';
    document.getElementById('uEmail').value = u ? (u.email || '') : '';
    document.getElementById('uPassword').value = '';
    document.getElementById('uPassHint').textContent = u ? '(leave blank to keep current)' : '(min 8 chars)';
    document.getElementById('uIsAdmin').checked = u ? u.role.indexOf('admin') !== -1 : false;
    document.getElementById('uCmPerson').value = u ? (u.cm_person_id || '') : '';
    document.getElementById('uSpPerson').value = u ? (u.sp_person_id || '') : '';
    document.getElementById('uActive').checked = u ? !!Number(u.is_active) : true;
    toggleRoleWraps();
    bootstrap.Modal.getOrCreateInstance(document.getElementById('mUser')).show();
  }

  async function save() {
    const payload = {
      action: 'save',
      id: document.getElementById('uId').value || null,
      full_name: document.getElementById('uFullName').value.trim(),
      username: document.getElementById('uUsername').value.trim(),
      email: document.getElementById('uEmail').value.trim(),
      password: document.getElementById('uPassword').value,
      is_admin: document.getElementById('uIsAdmin').checked,
      cm_person_id: document.getElementById('uCmPerson').value || null,
      sp_person_id: document.getElementById('uSpPerson').value || null,
      is_active: document.getElementById('uActive').checked
    };
    if (!payload.full_name || !payload.username) {
      MH.toast('Full name and username are required.', 'err');
      return;
    }
    try {
      await MH.api('users', payload, 'POST');
      bootstrap.Modal.getInstance(document.getElementById('mUser')).hide();
      MH.toast('User saved.', 'ok');
      load();
    } catch (e) {
      MH.toast(e.message, 'err');
    }
  }

  async function del(id) {
    const u = users.find(function (x) { return x.id === id; });
    const yes = await MH.confirm('Delete user "' + (u ? u.full_name : id) + '"?');
    if (!yes) return;
    try {
      await MH.api('users', { action: 'delete', id: id }, 'POST');
      MH.toast('User deleted.', 'ok');
      load();
    } catch (e) {
      MH.toast(e.message, 'err');
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    MH.topbarButton(
      '<button class="btn btn-sm btn-mh"><i class="bi bi-plus-lg me-1"></i>Add user</button>',
      function () { openModal(null); }
    );
    document.getElementById('btnSaveUser').addEventListener('click', save);
    document.getElementById('uIsAdmin').addEventListener('change', toggleRoleWraps);
    load();
  });
})();
