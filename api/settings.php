<?php
// ============================================================
//  api/settings.php — System settings, AI config, brand select,
//  emergency stop, cron info
//  POST action=select_brand   → set session workspace (anyone)
//  GET  action=overview       → settings snapshot (admin)
//  POST action=save_ai        → AI provider/model/keys (admin)
//  POST action=emergency_stop → toggle global stop (admin)
//  GET  action=cron_info      → cron URL + key (admin)
// ============================================================

require_once __DIR__ . '/_init.php';
require_once APP_ROOT . '/lib/ai.php';

$action = $_GET['action'] ?? $body['action'] ?? '';

try {
    switch ($action) {

        case 'select_brand':
            $brandId = $body['brand_id'] ?? '';
            if ($brandId !== '') {
                $stmt = $pdo->prepare('SELECT id FROM brands WHERE id = ?');
                $stmt->execute([$brandId]);
                if (!$stmt->fetch()) json_error('Brand not found', 404);
            }
            set_current_brand($brandId);
            json_ok(['brand_id' => $brandId]);
            break;

        case 'overview':
            require_admin_api();
            json_ok([
                'ai'             => ai_settings(),
                'emergency_stop' => emergency_stopped(),
                'timezone'       => date_default_timezone_get(),
            ]);
            break;

        case 'save_ai':
            require_admin_api();
            $provider = $body['provider'] ?? 'anthropic';
            $model    = trim($body['model'] ?? '');
            if (!isset(AI_PROVIDERS[$provider])) json_error('Invalid provider.', 422);
            set_setting('ai_provider', $provider);
            set_setting('ai_model', $model !== '' ? $model : AI_PROVIDERS[$provider]['default_model']);
            foreach (['anthropic', 'xai'] as $p) {
                $k = trim($body['key_' . $p] ?? '');
                if ($k !== '') set_setting('ai_key_' . $p . '_enc', encrypt_secret($k));
            }
            audit('ai_settings_saved', 'settings', '', $provider . ' / ' . ($model ?: 'default model'));
            json_ok(ai_settings());
            break;

        case 'emergency_stop':
            require_admin_api();
            $stopped = !empty($body['stopped']);
            set_setting('emergency_stop', $stopped ? '1' : '0');
            audit($stopped ? 'emergency_stop' : 'operations_resumed', 'settings', '',
                $stopped ? 'ALL publishing and automations paused' : 'Operations resumed');
            json_ok(['stopped' => $stopped]);
            break;

        case 'run_scheduler':
            require_admin_api();
            if (emergency_stopped()) json_error('Emergency stop is active — resume operations first.', 422);
            require_once APP_ROOT . '/includes/runner.php';
            $automations = run_due_automations($pdo);
            $publish     = run_due_posts($pdo);
            json_ok([
                'automations' => $automations,
                'published'   => count(array_filter($publish, fn($r) => $r['ok'])),
                'failed'      => count(array_filter($publish, fn($r) => !$r['ok'])),
                'publish'     => $publish,
            ]);
            break;

        case 'cron_info':
            require_admin_api();
            $key = get_setting('cron_key');
            if (!$key) {
                $key = bin2hex(random_bytes(24));
                set_setting('cron_key', $key);
            }
            $scheme = !empty($_SERVER['HTTPS']) ? 'https' : 'http';
            $host   = $_SERVER['HTTP_HOST'] ?? 'your-domain.com';
            $base   = dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
            $base   = rtrim($base === '/' || $base === '\\' ? '' : $base, '/');
            json_ok([
                'cron_key' => $key,
                'cron_url' => "$scheme://$host$base/cron/scheduler.php?key=$key",
            ]);
            break;

        default:
            json_error('Unknown action: ' . $action, 404);
    }
} catch (PDOException $e) {
    json_error('Database error: ' . $e->getMessage(), 500);
}
