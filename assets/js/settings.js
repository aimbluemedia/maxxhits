/* ============================================================
   settings.js — Settings page (admin)
   ============================================================ */
(function () {
  'use strict';

  async function load() {
    try {
      const [ov, cron] = await Promise.all([
        MH.api('settings', { action: 'overview' }),
        MH.api('settings', { action: 'cron_info' })
      ]);
      document.getElementById('aiProvider').value = ov.ai.provider;
      document.getElementById('aiModel').value = ov.ai.model;
      document.getElementById('keyAnthHint').textContent = ov.ai.has_key_anthropic ? '(saved)' : '(not set)';
      document.getElementById('keyXaiHint').textContent = ov.ai.has_key_xai ? '(saved)' : '(not set)';
      const ready = (ov.ai.provider === 'anthropic' && ov.ai.has_key_anthropic) || (ov.ai.provider === 'xai' && ov.ai.has_key_xai);
      document.getElementById('aiStatusLine').textContent = ready
        ? 'Using ' + ov.ai.model + ' via ' + (ov.ai.provider === 'anthropic' ? 'Claude' : 'Grok') + '.'
        : 'No key saved for the selected provider — generation will fail until one is added.';
      document.getElementById('cronUrl').value = cron.cron_url;
      document.getElementById('stopState').textContent = ov.emergency_stop ? 'STOPPED' : 'Operational';
      document.getElementById('stopState').className = ov.emergency_stop ? 'err' : 'ok';
    } catch (e) { MH.toast(e.message, 'err'); }
  }

  async function saveAI() {
    try {
      await MH.api('settings', {
        action: 'save_ai',
        provider: document.getElementById('aiProvider').value,
        model: document.getElementById('aiModel').value.trim(),
        key_anthropic: document.getElementById('aiKeyAnthropic').value,
        key_xai: document.getElementById('aiKeyXai').value
      }, 'POST');
      document.getElementById('aiKeyAnthropic').value = '';
      document.getElementById('aiKeyXai').value = '';
      MH.toast('AI settings saved.');
      load();
    } catch (e) { MH.toast(e.message, 'err'); }
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('btnSaveAI').addEventListener('click', saveAI);
    document.getElementById('btnCopyCron').addEventListener('click', function () {
      const input = document.getElementById('cronUrl');
      input.select();
      navigator.clipboard.writeText(input.value).then(
        function () { MH.toast('Cron URL copied.'); },
        function () { document.execCommand('copy'); MH.toast('Cron URL copied.'); }
      );
    });
    load();
  });
})();
