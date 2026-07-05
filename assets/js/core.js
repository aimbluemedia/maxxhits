/* ============================================================
   MaxxHits — core.js
   Shared client utilities. Every page loads this ONE file plus
   its own page script. All shared helpers live under the MH
   namespace so page scripts never collide.
   ============================================================ */
(function () {
  'use strict';

  const CSRF = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

  /**
   * Call an API endpoint.
   *   MH.api('companies')                       → GET api/companies.php
   *   MH.api('companies', {action:'save', ...}) → GET with query params
   *   MH.api('companies', body, 'POST')         → POST JSON body
   * Endpoints return { ok, data | error }; resolves with data,
   * rejects with Error(message).
   */
  async function api(endpoint, params, method) {
    method = method || (params && params._method) || 'GET';
    let url = 'api/' + endpoint + '.php';
    const opts = { method: method, headers: { 'X-CSRF-Token': CSRF } };

    if (params) {
      const p = Object.assign({}, params);
      delete p._method;
      if (method === 'GET') {
        const qs = new URLSearchParams(p).toString();
        if (qs) url += '?' + qs;
      } else {
        opts.headers['Content-Type'] = 'application/json';
        opts.body = JSON.stringify(p);
      }
    }

    let res, json;
    try {
      res = await fetch(url, opts);
    } catch (e) {
      throw new Error('Network error — check your connection.');
    }
    try {
      json = await res.json();
    } catch (e) {
      throw new Error('Server returned an invalid response (HTTP ' + res.status + ').');
    }
    if (!json.ok) {
      if (res.status === 401) { window.location.href = 'login.php'; }
      throw new Error(json.error || 'Request failed (HTTP ' + res.status + ').');
    }
    return json.data;
  }

  /** Escape text for interpolation into HTML strings. */
  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  /** Toast notification. type: 'ok' | 'err' | 'info' */
  function toast(msg, type) {
    type = type || 'info';
    const stack = document.getElementById('toastStack');
    if (!stack) { alert(msg); return; }
    const el = document.createElement('div');
    el.className = 'mh-toast ' + type;
    const icon = type === 'err' ? 'bi-x-circle' : (type === 'ok' ? 'bi-check-circle' : 'bi-info-circle');
    el.innerHTML = '<i class="bi ' + icon + '"></i><span>' + esc(msg) + '</span>';
    stack.appendChild(el);
    setTimeout(function () { el.remove(); }, 4200);
  }

  /** Confirm dialog (shared modal). Resolves true/false. */
  function confirmDlg(msg, yesLabel) {
    return new Promise(function (resolve) {
      const modalEl = document.getElementById('mhConfirm');
      if (!modalEl) { resolve(window.confirm(msg)); return; }
      document.getElementById('mhConfirmMsg').textContent = msg;
      const yesBtn = document.getElementById('mhConfirmYes');
      yesBtn.textContent = yesLabel || 'Delete';
      const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
      let done = false;
      function finish(val) {
        if (done) return;
        done = true;
        yesBtn.removeEventListener('click', onYes);
        modalEl.removeEventListener('hidden.bs.modal', onHide);
        resolve(val);
      }
      function onYes() { modal.hide(); finish(true); }
      function onHide() { finish(false); }
      yesBtn.addEventListener('click', onYes);
      modalEl.addEventListener('hidden.bs.modal', onHide);
      modal.show();
    });
  }

  /** Format a number as money. */
  function money(v) {
    if (v == null || isNaN(v)) return '—';
    return '$' + Number(v).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
  }

  /** Format YYYY-MM-DD → "Jun 5, 2026". */
  function fmtDate(d) {
    if (!d) return '—';
    const dt = new Date(d + 'T00:00:00');
    if (isNaN(dt)) return d;
    return dt.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
  }

  /** Initials for avatar dots. */
  function initials(name) {
    return String(name || '?').trim().split(/\s+/).slice(0, 2)
      .map(function (w) { return w[0]; }).join('').toUpperCase();
  }

  /** Platform badge HTML. */
  function platformBadge(pf) {
    const icons = {
      blog: 'bi-file-text', wordpress: 'bi-wordpress', facebook: 'bi-facebook',
      instagram: 'bi-instagram', twitter: 'bi-twitter-x', linkedin: 'bi-linkedin',
      tiktok: 'bi-tiktok', youtube: 'bi-youtube', reddit: 'bi-reddit', pinterest: 'bi-pinterest'
    };
    const labels = { twitter: 'X / Twitter', blog: 'Blog' };
    const label = labels[pf] || (pf ? pf.charAt(0).toUpperCase() + pf.slice(1) : '—');
    return '<span class="pf-badge pf-' + esc(pf) + '"><i class="bi ' + (icons[pf] || 'bi-globe') + '"></i>' + esc(label) + '</span>';
  }

  /** Status badge HTML. */
  function statusBadge(st) {
    const label = st ? st.charAt(0).toUpperCase() + st.slice(1) : '—';
    return '<span class="badge-status st-' + esc(st) + '">' + esc(label) + '</span>';
  }

  /** Add a button to the topbar actions slot. */
  function topbarButton(html, onClick) {
    const slot = document.getElementById('tbActions');
    if (!slot) return null;
    const wrap = document.createElement('span');
    wrap.innerHTML = html;
    const btn = wrap.firstElementChild;
    if (onClick) btn.addEventListener('click', onClick);
    slot.appendChild(btn);
    return btn;
  }

  // Sidebar toggle (desktop collapse / mobile slide-in)
  document.addEventListener('DOMContentLoaded', function () {
    const btn = document.getElementById('btnToggle');
    const sb = document.getElementById('sidebar');
    if (btn && sb) {
      btn.addEventListener('click', function () {
        if (window.innerWidth <= 900) sb.classList.toggle('sb-open');
        else sb.classList.toggle('collapsed');
      });
    }
  });

  window.MH = {
    api: api,
    esc: esc,
    toast: toast,
    confirm: confirmDlg,
    money: money,
    fmtDate: fmtDate,
    initials: initials,
    platformBadge: platformBadge,
    statusBadge: statusBadge,
    topbarButton: topbarButton
  };
})();
