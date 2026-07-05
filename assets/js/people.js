/* ============================================================
   people.js — Content Managers / Sales People pages
   The page sets data-role on #peoplePage; everything else is
   identical between the two pages.
   ============================================================ */
(function () {
  'use strict';

  const page = document.getElementById('peoplePage');
  if (!page) return;

  const ROLE = page.dataset.role;
  const ROLE_LABEL = ROLE === 'content_manager' ? 'content manager' : 'sales person';
  const isAdmin = !!document.getElementById('mPerson');

  let people = [];
  let companies = [];

  async function load() {
    try {
      const res = await Promise.all([
        MH.api('people', { role: ROLE }),
        MH.api('companies')
      ]);
      people = res[0];
      companies = res[1];
    } catch (e) {
      MH.toast(e.message, 'err');
      return;
    }
    render();
  }

  function companyNames(ids) {
    return ids.map(function (id) {
      const c = companies.find(function (x) { return x.id === id; });
      return c ? c.name : null;
    }).filter(Boolean);
  }

  function render() {
    const el = document.getElementById('peopleList');
    if (!people.length) {
      el.innerHTML =
        '<div class="state-box"><i class="bi bi-people"></i>' +
        '<strong>No ' + ROLE_LABEL + 's yet</strong>' +
        '<p>' + (isAdmin ? 'Use the button above to add one.' : '') + '</p></div>';
      return;
    }
    el.innerHTML =
      '<div class="tbl-wrap"><table><thead><tr>' +
      '<th>Name</th><th>Email</th><th>Companies</th><th>Notes</th>' + (isAdmin ? '<th></th>' : '') +
      '</tr></thead><tbody>' +
      people.map(function (p) {
        const names = companyNames(p.company_ids);
        return '<tr>' +
          '<td><strong>' + MH.esc(p.name) + '</strong></td>' +
          '<td>' + (p.email ? '<a href="mailto:' + MH.esc(p.email) + '">' + MH.esc(p.email) + '</a>' : '—') + '</td>' +
          '<td style="font-size:12px">' + (names.length ? MH.esc(names.join(', ')) : '—') + '</td>' +
          '<td style="font-size:12px;color:#64748b">' + MH.esc(p.notes || '—') + '</td>' +
          (isAdmin
            ? '<td class="text-end" style="white-space:nowrap">' +
              '<button class="btn btn-sm btn-outline-secondary me-1" data-edit="' + MH.esc(p.id) + '"><i class="bi bi-pencil"></i></button>' +
              '<button class="btn btn-sm btn-outline-danger" data-del="' + MH.esc(p.id) + '"><i class="bi bi-trash"></i></button></td>'
            : '') +
          '</tr>';
      }).join('') +
      '</tbody></table></div>';

    el.querySelectorAll('[data-edit]').forEach(function (b) {
      b.addEventListener('click', function () { openModal(b.dataset.edit); });
    });
    el.querySelectorAll('[data-del]').forEach(function (b) {
      b.addEventListener('click', function () { del(b.dataset.del); });
    });
  }

  function openModal(id) {
    const p = id ? people.find(function (x) { return x.id === id; }) : null;
    document.getElementById('mPersonTitle').innerHTML =
      '<i class="bi bi-person-plus me-2"></i>' + (p ? 'Edit ' : 'Add ') + ROLE_LABEL;
    document.getElementById('perId').value = p ? p.id : '';
    document.getElementById('perName').value = p ? p.name : '';
    document.getElementById('perEmail').value = p ? (p.email || '') : '';
    document.getElementById('perNotes').value = p ? (p.notes || '') : '';
    const ids = p ? p.company_ids : [];
    document.getElementById('perCompanies').innerHTML = companies.map(function (c) {
      const checked = ids.indexOf(c.id) !== -1 ? ' checked' : '';
      return '<label class="form-check" style="font-size:13px">' +
        '<input class="form-check-input" type="checkbox" value="' + MH.esc(c.id) + '"' + checked + '> ' +
        MH.esc(c.name) + '</label>';
    }).join('') || '<span class="text-muted" style="font-size:12px">No companies yet.</span>';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('mPerson')).show();
  }

  async function save() {
    const ids = Array.prototype.slice.call(
      document.querySelectorAll('#perCompanies input:checked')
    ).map(function (i) { return i.value; });
    const payload = {
      action: 'save',
      id: document.getElementById('perId').value || null,
      role: ROLE,
      name: document.getElementById('perName').value.trim(),
      email: document.getElementById('perEmail').value.trim(),
      notes: document.getElementById('perNotes').value,
      company_ids: ids
    };
    if (!payload.name) { MH.toast('Name is required.', 'err'); return; }
    try {
      await MH.api('people', payload, 'POST');
      bootstrap.Modal.getInstance(document.getElementById('mPerson')).hide();
      MH.toast('Saved.', 'ok');
      load();
    } catch (e) {
      MH.toast(e.message, 'err');
    }
  }

  async function del(id) {
    const p = people.find(function (x) { return x.id === id; });
    const yes = await MH.confirm('Delete "' + (p ? p.name : id) + '"?');
    if (!yes) return;
    try {
      await MH.api('people', { action: 'delete', id: id }, 'POST');
      MH.toast('Deleted.', 'ok');
      load();
    } catch (e) {
      MH.toast(e.message, 'err');
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    if (isAdmin) {
      MH.topbarButton(
        '<button class="btn btn-sm btn-mh"><i class="bi bi-plus-lg me-1"></i>Add ' + MH.esc(ROLE_LABEL) + '</button>',
        function () { openModal(null); }
      );
      document.getElementById('btnSavePerson').addEventListener('click', save);
    }
    load();
  });
})();
