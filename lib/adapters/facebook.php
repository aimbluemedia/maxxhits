<?php
// ============================================================
//  lib/adapters/facebook.php — Facebook Page adapter
//  Config: { page_id, access_token } — long-lived PAGE token
//  with pages_manage_posts (Meta Graph API).
// ============================================================

const MH_FB_GRAPH = 'https://graph.facebook.com/v21.0';

function adapter_publish_facebook(array $config, array $post): array {
    $pageId = $config['page_id'] ?? '';
    $token  = $config['access_token'] ?? '';
    if (!$pageId || !$token) return ['ok' => false, 'error' => 'Facebook destination is missing page_id or access_token.'];

    $message = trim($post['title'] . "\n\n" . ($post['content'] ?? ''));

    if (!empty($post['image_url'])) {
        $res = adapter_http(MH_FB_GRAPH . "/{$pageId}/photos", [
            'method' => 'POST',
            'body'   => http_build_query(['url' => $post['image_url'], 'message' => $message, 'access_token' => $token]),
        ]);
        $extId = $res['json']['post_id'] ?? $res['json']['id'] ?? null;
    } else {
        $res = adapter_http(MH_FB_GRAPH . "/{$pageId}/feed", [
            'method' => 'POST',
            'body'   => http_build_query(['message' => $message, 'access_token' => $token]),
        ]);
        $extId = $res['json']['id'] ?? null;
    }

    if ($res['status'] === 200 && $extId) {
        return ['ok' => true, 'external_id' => (string)$extId, 'url' => 'https://www.facebook.com/' . $extId];
    }
    return ['ok' => false, 'error' => adapter_http_error($res, 'Facebook rejected the post')];
}

function adapter_test_facebook(array $config): array {
    $pageId = $config['page_id'] ?? '';
    $token  = $config['access_token'] ?? '';
    if (!$pageId || !$token) return ['ok' => false, 'error' => 'Missing page_id or access_token.'];
    $res = adapter_http(MH_FB_GRAPH . "/{$pageId}?fields=id,name&access_token=" . urlencode($token));
    if ($res['status'] === 200 && !empty($res['json']['id'])) {
        return ['ok' => true, 'external_id' => $res['json']['id'], 'url' => null];
    }
    return ['ok' => false, 'error' => adapter_http_error($res, 'Could not reach the Facebook page')];
}
