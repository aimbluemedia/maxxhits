<?php
// ============================================================
//  lib/publishers/facebook.php — Facebook Page publisher
//
//  Channel config: { page_id, access_token }
//  access_token must be a long-lived PAGE access token with
//  pages_manage_posts permission (Meta Graph API).
// ============================================================

const MH_FB_GRAPH = 'https://graph.facebook.com/v21.0';

function mh_publish_facebook(array $config, array $post): array {
    $pageId = $config['page_id'] ?? '';
    $token  = $config['access_token'] ?? '';
    if (!$pageId || !$token) {
        return ['ok' => false, 'error' => 'Facebook channel is missing page_id or access_token.'];
    }

    $message = trim($post['title'] . "\n\n" . ($post['content'] ?? ''));

    if (!empty($post['image_url'])) {
        // Photo post
        $res = publisher_http(MH_FB_GRAPH . "/{$pageId}/photos", [
            'method' => 'POST',
            'body'   => http_build_query([
                'url'          => $post['image_url'],
                'message'      => $message,
                'access_token' => $token,
            ]),
        ]);
        $idKey = 'post_id';
    } else {
        // Text/link post
        $res = publisher_http(MH_FB_GRAPH . "/{$pageId}/feed", [
            'method' => 'POST',
            'body'   => http_build_query([
                'message'      => $message,
                'access_token' => $token,
            ]),
        ]);
        $idKey = 'id';
    }

    $extId = $res['json'][$idKey] ?? $res['json']['id'] ?? null;
    if ($res['status'] === 200 && $extId) {
        return [
            'ok'          => true,
            'external_id' => (string)$extId,
            'url'         => 'https://www.facebook.com/' . $extId,
        ];
    }
    return ['ok' => false, 'error' => publisher_http_error($res, 'Facebook rejected the post')];
}

function mh_test_facebook(array $config): array {
    $pageId = $config['page_id'] ?? '';
    $token  = $config['access_token'] ?? '';
    if (!$pageId || !$token) return ['ok' => false, 'error' => 'Missing page_id or access_token.'];

    $res = publisher_http(MH_FB_GRAPH . "/{$pageId}?fields=id,name&access_token=" . urlencode($token));
    if ($res['status'] === 200 && !empty($res['json']['id'])) {
        return ['ok' => true, 'external_id' => $res['json']['id'], 'url' => null];
    }
    return ['ok' => false, 'error' => publisher_http_error($res, 'Could not reach the Facebook page')];
}
