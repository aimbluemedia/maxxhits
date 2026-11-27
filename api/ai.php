<?php
// ============================================================
//  api/ai.php — AI content generation + settings
//  GET  action=settings      → provider/model/key status (admin)
//  POST action=save_settings → provider/model/keys (admin)
//  POST action=generate      → create post variants (admin/CM)
// ============================================================

require_once __DIR__ . '/_init.php';
require_once dirname(__DIR__) . '/lib/ai.php';

$action = $_GET['action'] ?? $body['action'] ?? '';

try {
    switch ($action) {

        case 'settings':
            require_admin_api();
            json_ok(ai_settings());
            break;

        case 'save_settings':
            require_admin_api();
            $provider = $body['provider'] ?? 'anthropic';
            $model    = trim($body['model'] ?? '');
            if (!isset(AI_PROVIDERS[$provider])) json_error('Invalid provider.', 422);
            set_setting('ai_provider', $provider);
            set_setting('ai_model', $model !== '' ? $model : AI_PROVIDERS[$provider]['default_model']);

            // Blank key fields keep the stored key
            foreach (['anthropic', 'xai'] as $p) {
                $k = trim($body['key_' . $p] ?? '');
                if ($k !== '') set_setting('ai_key_' . $p . '_enc', encrypt_secret($k));
            }
            json_ok(ai_settings());
            break;

        case 'generate':
            if (!is_admin() && !has_role('content_manager')) json_error('Forbidden', 403);
            $coId     = $body['company_id'] ?? '';
            $platform = $body['platform'] ?? 'blog';
            $topic    = trim($body['topic'] ?? '');
            if (!$coId) json_error('company_id required', 422);
            if (!can_view_company($coId)) json_error('Forbidden', 403);
            if ($topic === '') json_error('Topic is required.', 422);

            $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
            $stmt->execute([$coId]);
            $company = $stmt->fetch();
            if (!$company) json_error('Company not found', 404);

            $result = ai_generate([
                'company'      => $company,
                'platform'     => $platform,
                'topic'        => $topic,
                'instructions' => trim($body['instructions'] ?? ''),
                'count'        => (int)($body['count'] ?? 3),
            ]);
            if (!$result['ok']) json_error($result['error'], 422);
            json_ok(['variants' => $result['variants'], 'model' => $result['model']]);
            break;

        default:
            json_error('Unknown action: ' . $action, 404);
    }
} catch (PDOException $e) {
    json_error('Database error: ' . $e->getMessage(), 500);
}
