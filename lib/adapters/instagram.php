<?php
// ============================================================
//  lib/adapters/instagram.php — Instagram Business adapter
//  Config: { ig_user_id, access_token } — professional account
//  linked to a FB page, instagram_content_publish permission.
//  Instagram REQUIRES an image. Flow: container → poll → publish.
// ============================================================

const MH_IG_GRAPH = 'https://graph.facebook.com/v21.0';

function adapter_publish_instagram(array $config, array $post): array {
    $igId  = $config['ig_user_id'] ?? '';
    $token = $config['access_token'] ?? '';
    if (!$igId || !$token) return ['ok' => false, 'error' => 'Instagram destination is missing ig_user_id or access_token.'];
    if (empty($post['image_url'])) return ['ok' => false, 'error' => 'Instagram requires an image URL — add one to the post.'];

    $caption = mb_substr(trim($post['title'] . "\n\n" . ($post['content'] ?? '')), 0, 2200);

    // 1. Create media container
    $res = adapter_http(MH_IG_GRAPH . "/{$igId}/media", [
        'method' => 'POST',
        'body'   => http_build_query(['image_url' => $post['image_url'], 'caption' => $caption, 'access_token' => $token]),
    ]);
    $container = $res['json']['id'] ?? null;
    if ($res['status'] !== 200 || !$container) {
        return ['ok' => false, 'error' => adapter_http_error($res, 'Instagram rejected the media container')];
    }

    // 2. Wait for processing
    for ($i = 0; $i < 10; $i++) {
        $st = adapter_http(MH_IG_GRAPH . "/{$container}?fields=status_code&access_token=" . urlencode($token));
        $code = $st['json']['status_code'] ?? '';
        if ($code === 'FINISHED') break;
        if ($code === 'ERROR') return ['ok' => false, 'error' => 'Instagram could not process the image (container status ERROR).'];
        sleep(2);
    }

    // 3. Publish
    $pub = adapter_http(MH_IG_GRAPH . "/{$igId}/media_publish", [
        'method' => 'POST',
        'body'   => http_build_query(['creation_id' => $container, 'access_token' => $token]),
    ]);
    $mediaId = $pub['json']['id'] ?? null;
    if ($pub['status'] === 200 && $mediaId) {
        $pl = adapter_http(MH_IG_GRAPH . "/{$mediaId}?fields=permalink&access_token=" . urlencode($token));
        return ['ok' => true, 'external_id' => (string)$mediaId, 'url' => $pl['json']['permalink'] ?? null];
    }
    return ['ok' => false, 'error' => adapter_http_error($pub, 'Instagram rejected the publish step')];
}

function adapter_test_instagram(array $config): array {
    $igId  = $config['ig_user_id'] ?? '';
    $token = $config['access_token'] ?? '';
    if (!$igId || !$token) return ['ok' => false, 'error' => 'Missing ig_user_id or access_token.'];
    $res = adapter_http(MH_IG_GRAPH . "/{$igId}?fields=id,username&access_token=" . urlencode($token));
    if ($res['status'] === 200 && !empty($res['json']['id'])) {
        return ['ok' => true, 'external_id' => $res['json']['id'], 'url' => null];
    }
    return ['ok' => false, 'error' => adapter_http_error($res, 'Could not reach the Instagram account')];
}
