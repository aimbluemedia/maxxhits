/* ============================================================
   users.js — Staff accounts page (admin)
   ============================================================ */
(function () {
  'use strict';

  let users = [];

  async function load() {
    try { users = await MH.api('users'); }
    catch (e) { MH.toast(e.message, 'err'); return; }
    render();
  }

  function render() {
    const el = document.getElementById('usersList');
    el.innerHTML =
      '<table><thead><tr><th>Name</th><th>Username</th><th>Role</th><th>Last login</th><th>Status</th><th></th></tr></thead><tbody>' +
      users.map(function (u) {
        return '<tr>' +
          '<td><strong>' + MH.esc(u.full_name) + '</strong>' +
            (u.email ? '<div class="muted" style="font-size:11.5px">' + MH.esc(u.email) + '</div>' : '') + '</td>' +
          '<td><code>' + MH.esc(u.username) + '</code></td>' +
          '<td><span class="tag">' + MH.esc(u.role) + '</span></td>' +
          '<td class="muted" style="font-size:12px">' + MH.esc(u.last_login || 'never') + '</td>' +
          '<td>' + (Number(u.is_active)
            ? '<span class="st st-published">Active</span>'
            : '<span class="st st-failed">Disabled</span>') + '</td>' +
          '<td style="text-align:right;white-space:nowrap">' +
            '<button data-edit="' + MH.esc(u.id) + '">Edit</button>' +
            (u.id !== 'usr_admin' ? ' <button class="danger" data-del="' + MH.esc(u.id) + '">Delete</button>' : '') +
          '</td></tr>';
      }).join('') + '</tbody></table>';

    el.querySelectorAll('[data-edit]').forEach(function (b) {
      b.addEventListener('click', function () { openModal(b.dataset.edit); });
    });
    el.querySelectorAll('[data-del]').forEach(function (b) {
      b.addEventListener('click', async function () {
        const u = users.find(function (x) { return x.id === b.dataset.del; });
        if (!await MH.confirm('Delete user "' + (u ? u.full_name : '') + '"?', 'Delete')) return;
        try {
          await MH.api('users', { action: 'delete', id: b.dataset.del }, 'POST');
          MH.toast('User deleted.');
          load();
        } catch (e) { MH.toast(e.message, 'err'); }
      });
    });
  }

  function openModal(id) {
    const u = id ? users.find(function (x) { return x.id === id; }) : null;
    document.getElementById('mUserTitle').textContent = u ? 'Edit user' : 'Add user';
    document.getElementById('uId').value = u ? u.id : '';
    document.getElementById('uFullName').value = u ? u.full_name : '';
    document.getElementById('uUsername').value = u ? u.username : '';
    document.getElementById('uEmail').value = u ? (u.email || '') : '';
    document.getElementById('uPassword').value = '';
    document.getElementById('uPassHint').textContent = u ? '(blank keeps current)' : '(min 8 chars)';
    document.getElementById('uRole').value = u ? u.role : 'staff';
    document.getElementById('uActive').checked = u ? !!Number(u.is_active) : true;
    MH.openModal('mUser');
  }

  async function save() {
    const payload = {
      action: 'save',
      id: document.getElementById('uId').value || null,
      full_name: document.getElementById('uFullName').value.trim(),
      username: document.getElementById('uUsername').value.trim(),
      email: document.getElementById('uEmail').value.trim(),
      password: document.getElementById('uPassword').value,
      role: document.getElementById('uRole').value,
      is_active: document.getElementById('uActive').checked
    };
    if (!payload.full_name || !payload.username) {
      MH.toast('Full name and username are required.', 'err');
      return;
    }
    try {
      await MH.api('users', payload, 'POST');
      MH.closeModal('mUser');
      MH.toast('User saved.');
      load();
    } catch (e) { MH.toast(e.message, 'err'); }
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('btnAddUser').addEventListener('click', function () { openModal(null); });
    document.getElementById('btnSaveUser').addEventListener('click', save);
    load();
  });
})();
