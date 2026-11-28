/* ============================================================
   MaxxHits 2.0 — core.js
   Shared client utilities under the MH namespace. Every page
   loads this plus its own script; no cross-page globals.
   ============================================================ */
(function () {
  'use strict';

  const CSRF = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

  /** Call api/<endpoint>.php. GET params or JSON body for POST. */
  async function api(endpoint, params, method) {
    method = method || 'GET';
    let url = 'api/' + endpoint + '.php';
    const opts = { method: method, headers: { 'X-CSRF-Token': CSRF } };
    if (params) {
      if (method === 'GET') {
        const qs = new URLSearchParams(params).toString();
        if (qs) url += '?' + qs;
      } else {
        opts.headers['Content-Type'] = 'application/json';
        opts.body = JSON.stringify(params);
      }
    }
    let res, json;
    try { res = await fetch(url, opts); }
    catch (e) { throw new Error('Network error — check your connection.'); }
    try { json = await res.json(); }
    catch (e) { throw new Error('Server returned an invalid response (HTTP ' + res.status + ').'); }
    if (!json.ok) {
      if (res.status === 401) window.location.href = 'login.php';
      throw new Error(json.error || 'Request failed (HTTP ' + res.status + ').');
    }
    return json.data;
  }

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  let toastTimer = null;
  function toast(msg, type) {
    const el = document.getElementById('toast');
    if (!el) { alert(msg); return; }
    el.textContent = msg;
    el.className = type === 'err' ? 'err' : '';
    el.style.display = 'block';
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { el.style.display = 'none'; }, 3800);
  }

  /** Confirm dialog. Resolves true/false. */
  function confirmDlg(msg, yesLabel) {
    return new Promise(function (resolve) {
      const ov = document.getElementById('confirmOverlay');
      if (!ov) { resolve(window.confirm(msg)); return; }
      document.getElementById('confirmMsg').textContent = msg;
      const yes = document.getElementById('confirmYes');
      const no = document.getElementById('confirmNo');
      yes.textContent = yesLabel || 'Confirm';
      ov.classList.add('open');
      function finish(v) {
        ov.classList.remove('open');
        yes.removeEventListener('click', onYes);
        no.removeEventListener('click', onNo);
        ov.removeEventListener('click', onBg);
        resolve(v);
      }
      function onYes() { finish(true); }
      function onNo() { finish(false); }
      function onBg(e) { if (e.target === ov) finish(false); }
      yes.addEventListener('click', onYes);
      no.addEventListener('click', onNo);
      ov.addEventListener('click', onBg);
    });
  }

  /** Open/close a page modal (.mh-overlay element by id). */
  function openModal(id) {
    const ov = document.getElementById(id);
    if (!ov) return;
    ov.classList.add('open');
    if (!ov.dataset.wired) {
      ov.dataset.wired = '1';
      ov.addEventListener('click', function (e) { if (e.target === ov) closeModal(id); });
      ov.querySelectorAll('[data-close]').forEach(function (b) {
        b.addEventListener('click', function () { closeModal(id); });
      });
    }
  }
  function closeModal(id) {
    const ov = document.getElementById(id);
    if (ov) ov.classList.remove('open');
  }

  function fmtDate(d) {
    if (!d) return '—';
    const dt = new Date(d + 'T00:00:00');
    if (isNaN(dt)) return d;
    return dt.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
  }

  function statusBadge(st) {
    const label = st ? st.charAt(0).toUpperCase() + st.slice(1) : '—';
    return '<span class="st st-' + esc(st) + '">' + esc(label) + '</span>';
  }

  function platformBadge(pf, label) {
    return '<span class="pf">' + esc(label || pf || '—') + '</span>';
  }

  function brandDot(color, name) {
    return '<span class="dot" style="background:' + esc(color || '#7555df') + '"></span>' + esc(name || '');
  }

  // ── Header wiring (brand selector + emergency stop) ──────
  document.addEventListener('DOMContentLoaded', function () {
    const sel = document.getElementById('brandSelect');
    if (sel) {
      sel.addEventListener('change', async function () {
        try {
          await api('settings', { action: 'select_brand', brand_id: sel.value }, 'POST');
          window.location.reload();
        } catch (e) { toast(e.message, 'err'); }
      });
    }
    const stop = document.getElementById('btnStop');
    if (stop) {
      stop.addEventListener('click', async function () {
        const stopping = stop.dataset.stopped !== '1';
        const yes = await confirmDlg(
          stopping
            ? 'Emergency stop pauses ALL publishing and automation runs until resumed. Continue?'
            : 'Resume operations? Scheduled publishing and automations will run again.',
          stopping ? 'Stop everything' : 'Resume'
        );
        if (!yes) return;
        try {
          await api('settings', { action: 'emergency_stop', stopped: stopping }, 'POST');
          window.location.reload();
        } catch (e) { toast(e.message, 'err'); }
      });
    }
  });

  window.MH = {
    api: api, esc: esc, toast: toast, confirm: confirmDlg,
    openModal: openModal, closeModal: closeModal,
    fmtDate: fmtDate, statusBadge: statusBadge,
    platformBadge: platformBadge, brandDot: brandDot
  };
})();
