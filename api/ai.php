<?php
// ============================================================
//  api/ai.php — AI generation (Content Studio)
//  POST action=generate → create variants (any logged-in staff)
// ============================================================

require_once __DIR__ . '/_init.php';
require_once APP_ROOT . '/lib/ai.php';

$action = $_GET['action'] ?? $body['action'] ?? '';

try {
    switch ($action) {

        case 'generate':
            $brandId  = $body['brand_id'] ?? '';
            $platform = $body['platform'] ?? 'wordpress';
            $topic    = trim($body['topic'] ?? '');
            if (!$brandId) json_error('brand_id required', 422);
            if ($topic === '') json_error('Topic is required.', 422);
            if (!isset(mh_platforms()[$platform])) json_error('Invalid platform.', 422);

            $stmt = $pdo->prepare('SELECT * FROM brands WHERE id = ?');
            $stmt->execute([$brandId]);
            $brand = $stmt->fetch();
            if (!$brand) json_error('Brand not found', 404);

            $r = ai_generate([
                'brand'        => $brand,
                'platform'     => $platform,
                'topic'        => $topic,
                'instructions' => trim($body['instructions'] ?? ''),
                'count'        => (int)($body['count'] ?? 3),
            ]);
            if (!$r['ok']) json_error($r['error'], 422);
            json_ok(['variants' => $r['variants'], 'model' => $r['model']]);
            break;

        default:
            json_error('Unknown action: ' . $action, 404);
    }
} catch (PDOException $e) {
    json_error('Database error: ' . $e->getMessage(), 500);
}
