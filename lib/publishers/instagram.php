<?php
// ============================================================
//  lib/publishers/instagram.php — Instagram Business publisher
//
//  Channel config: { ig_user_id, access_token }
//  Requires an Instagram Business/Creator account linked to a
//  Facebook page, with instagram_content_publish permission.
//  Instagram REQUIRES an image — text-only posts are rejected.
//  Flow: create media container → poll status → publish.
// ============================================================

const MH_IG_GRAPH = 'https://graph.facebook.com/v21.0';

function mh_publish_instagram(array $config, array $post): array {
    $igId  = $config['ig_user_id'] ?? '';
    $token = $config['access_token'] ?? '';
    if (!$igId || !$token) {
        return ['ok' => false, 'error' => 'Instagram channel is missing ig_user_id or access_token.'];
    }
    if (empty($post['image_url'])) {
        return ['ok' => false, 'error' => 'Instagram requires an image URL — add one to the post.'];
    }

    $caption = trim($post['title'] . "\n\n" . ($post['content'] ?? ''));

    // 1. Create the media container
    $res = publisher_http(MH_IG_GRAPH . "/{$igId}/media", [
        'method' => 'POST',
        'body'   => http_build_query([
            'image_url'    => $post['image_url'],
            'caption'      => mb_substr($caption, 0, 2200),
            'access_token' => $token,
        ]),
    ]);
    $containerId = $res['json']['id'] ?? null;
    if ($res['status'] !== 200 || !$containerId) {
        return ['ok' => false, 'error' => publisher_http_error($res, 'Instagram rejected the media container')];
    }

    // 2. Wait for the container to be ready (usually a few seconds)
    for ($i = 0; $i < 10; $i++) {
        $st = publisher_http(MH_IG_GRAPH . "/{$containerId}?fields=status_code&access_token=" . urlencode($token));
        $code = $st['json']['status_code'] ?? '';
        if ($code === 'FINISHED') break;
        if ($code === 'ERROR') {
            return ['ok' => false, 'error' => 'Instagram could not process the image (container status ERROR).'];
        }
        sleep(2);
    }

    // 3. Publish the container
    $pub = publisher_http(MH_IG_GRAPH . "/{$igId}/media_publish", [
        'method' => 'POST',
        'body'   => http_build_query([
            'creation_id'  => $containerId,
            'access_token' => $token,
        ]),
    ]);
    $mediaId = $pub['json']['id'] ?? null;
    if ($pub['status'] === 200 && $mediaId) {
        // Fetch permalink (best-effort)
        $pl  = publisher_http(MH_IG_GRAPH . "/{$mediaId}?fields=permalink&access_token=" . urlencode($token));
        $url = $pl['json']['permalink'] ?? null;
        return ['ok' => true, 'external_id' => (string)$mediaId, 'url' => $url];
    }
    return ['ok' => false, 'error' => publisher_http_error($pub, 'Instagram rejected the publish step')];
}

function mh_test_instagram(array $config): array {
    $igId  = $config['ig_user_id'] ?? '';
    $token = $config['access_token'] ?? '';
    if (!$igId || !$token) return ['ok' => false, 'error' => 'Missing ig_user_id or access_token.'];

    $res = publisher_http(MH_IG_GRAPH . "/{$igId}?fields=id,username&access_token=" . urlencode($token));
    if ($res['status'] === 200 && !empty($res['json']['id'])) {
        return ['ok' => true, 'external_id' => $res['json']['id'], 'url' => null];
    }
    return ['ok' => false, 'error' => publisher_http_error($res, 'Could not reach the Instagram account')];
}
