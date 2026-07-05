/* ============================================================
   companies.js — Companies overview page
   ============================================================ */
(function () {
  'use strict';

  const DAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
  const isAdmin = !!document.getElementById('mCompany');
  let companies = [];
  let people = [];

  async function load() {
    try {
      const req = [MH.api('companies')];
      if (isAdmin) req.push(MH.api('people'));
      const res = await Promise.all(req);
      companies = res[0];
      people = res[1] || [];
    } catch (e) {
      MH.toast(e.message, 'err');
      return;
    }
    render();
  }

  function render() {
    const el = document.getElementById('companiesList');
    if (!companies.length) {
      el.innerHTML =
        '<div class="state-box"><i class="bi bi-building"></i>' +
        '<strong>No companies yet</strong>' +
        '<p>' + (isAdmin ? 'Click "Add company" to create the first one.' : 'No companies are assigned to you yet.') + '</p></div>';
      return;
    }
    el.innerHTML =
      '<div class="tbl-wrap"><table><thead><tr>' +
      '<th>Company</th><th>Posts</th><th>Published</th><th>Scheduled</th><th>Fee</th>' +
      '<th>Posting days</th><th>CM</th><th>SP</th>' + (isAdmin ? '<th></th>' : '') +
      '</tr></thead><tbody>' +
      companies.map(function (c) {
        const pc = c.post_counts || {};
        return '<tr>' +
          '<td><a href="company.php?id=' + encodeURIComponent(c.id) + '" style="text-decoration:none;color:inherit">' +
            '<span class="co-dot me-2" style="background:' + MH.esc(c.color) + '">' + MH.initials(c.name) + '</span>' +
            '<strong>' + MH.esc(c.name) + '</strong></a></td>' +
          '<td>' + (pc.total || 0) + (c.monthly_posts ? ' / ' + c.monthly_posts : '') + '</td>' +
          '<td>' + (pc.published || 0) + '</td>' +
          '<td>' + (pc.scheduled || 0) + '</td>' +
          '<td>' + MH.money(c.fee) + '</td>' +
          '<td style="font-size:12px;color:#64748b">' + (c.posting_days.length ? MH.esc(c.posting_days.join(', ')) : '—') + '</td>' +
          '<td>' + MH.esc(c.content_manager_name || '—') + '</td>' +
          '<td>' + MH.esc(c.sales_person_name || '—') + '</td>' +
          (isAdmin
            ? '<td class="text-end" style="white-space:nowrap">' +
              '<button class="btn btn-sm btn-outline-secondary me-1" data-edit="' + MH.esc(c.id) + '" title="Edit"><i class="bi bi-pencil"></i></button>' +
              '<button class="btn btn-sm btn-outline-danger" data-del="' + MH.esc(c.id) + '" title="Delete"><i class="bi bi-trash"></i></button></td>'
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

  // ── Admin: add / edit / delete ────────────────────────────

  function fillPeopleSelects() {
    const cm = document.getElementById('coCM');
    const sp = document.getElementById('coSP');
    cm.innerHTML = '<option value="">—</option>';
    sp.innerHTML = '<option value="">—</option>';
    people.forEach(function (p) {
      const opt = '<option value="' + MH.esc(p.id) + '">' + MH.esc(p.name) + '</option>';
      if (p.role === 'content_manager') cm.insertAdjacentHTML('beforeend', opt);
      else sp.insertAdjacentHTML('beforeend', opt);
    });
  }

  function renderDayChecks(selected) {
    document.getElementById('coDays').innerHTML = DAYS.map(function (d) {
      const checked = selected.indexOf(d) !== -1 ? ' checked' : '';
      return '<label class="form-check form-check-inline" style="font-size:13px">' +
        '<input class="form-check-input" type="checkbox" value="' + d + '"' + checked + '> ' + d + '</label>';
    }).join('');
  }

  function openModal(id) {
    const co = id ? companies.find(function (c) { return c.id === id; }) : null;
    fillPeopleSelects();
    document.getElementById('mCompanyTitle').innerHTML =
      '<i class="bi bi-building me-2"></i>' + (co ? 'Edit company' : 'Add company');
    document.getElementById('coId').value = co ? co.id : '';
    document.getElementById('coName').value = co ? co.name : '';
    document.getElementById('coColor').value = co ? co.color : '#6c47ff';
    document.getElementById('coCM').value = co ? (co.content_manager_id || '') : '';
    document.getElementById('coSP').value = co ? (co.sales_person_id || '') : '';
    document.getElementById('coPosts').value = co && co.monthly_posts != null ? co.monthly_posts : '';
    document.getElementById('coFee').value = co && co.fee != null ? co.fee : '';
    document.getElementById('coPayDay').value = co && co.payment_date != null ? co.payment_date : '';
    document.getElementById('coSpPct').value = co ? co.fee_sp_pct : 40;
    document.getElementById('coCmPct').value = co ? co.fee_cm_pct : 40;
    document.getElementById('coSmPct').value = co ? co.fee_sm_pct : 20;
    renderDayChecks(co ? co.posting_days : []);
    bootstrap.Modal.getOrCreateInstance(document.getElementById('mCompany')).show();
  }

  async function save() {
    const days = Array.prototype.slice.call(
      document.querySelectorAll('#coDays input:checked')
    ).map(function (i) { return i.value; });

    const payload = {
      action: 'save',
      id: document.getElementById('coId').value || null,
      name: document.getElementById('coName').value.trim(),
      color: document.getElementById('coColor').value,
      content_manager_id: document.getElementById('coCM').value || null,
      sales_person_id: document.getElementById('coSP').value || null,
      monthly_posts: document.getElementById('coPosts').value,
      fee: document.getElementById('coFee').value,
      payment_date: document.getElementById('coPayDay').value,
      fee_sp_pct: document.getElementById('coSpPct').value,
      fee_cm_pct: document.getElementById('coCmPct').value,
      fee_sm_pct: document.getElementById('coSmPct').value,
      posting_days: days
    };
    if (!payload.name) { MH.toast('Company name is required.', 'err'); return; }

    try {
      await MH.api('companies', payload, 'POST');
      bootstrap.Modal.getInstance(document.getElementById('mCompany')).hide();
      MH.toast('Company saved.', 'ok');
      load();
    } catch (e) {
      MH.toast(e.message, 'err');
    }
  }

  async function del(id) {
    const co = companies.find(function (c) { return c.id === id; });
    const yes = await MH.confirm('Delete "' + (co ? co.name : id) + '" and ALL of its posts and channels?');
    if (!yes) return;
    try {
      await MH.api('companies', { action: 'delete', id: id }, 'POST');
      MH.toast('Company deleted.', 'ok');
      load();
    } catch (e) {
      MH.toast(e.message, 'err');
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    if (isAdmin) {
      MH.topbarButton(
        '<button class="btn btn-sm btn-mh"><i class="bi bi-plus-lg me-1"></i>Add company</button>',
        function () { openModal(null); }
      );
      document.getElementById('btnSaveCompany').addEventListener('click', save);
    }
    load();
  });
})();
