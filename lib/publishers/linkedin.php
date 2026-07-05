<?php
// ============================================================
//  lib/publishers/linkedin.php — LinkedIn publisher
//
//  Channel config: { author_urn, access_token }
//  author_urn examples:
//    urn:li:organization:12345   (company page)
//    urn:li:person:AbC123        (personal profile)
//  access_token needs the w_member_social (person) or
//  w_organization_social (organization) scope.
// ============================================================

const MH_LI_API = 'https://api.linkedin.com';

function mh_publish_linkedin(array $config, array $post): array {
    $urn   = $config['author_urn'] ?? '';
    $token = $config['access_token'] ?? '';
    if (!$urn || !$token) {
        return ['ok' => false, 'error' => 'LinkedIn channel is missing author_urn or access_token.'];
    }

    $text = trim($post['title'] . "\n\n" . ($post['content'] ?? ''));
    if (!empty($post['post_url'])) $text .= "\n" . $post['post_url'];

    $payload = [
        'author'          => $urn,
        'lifecycleState'  => 'PUBLISHED',
        'specificContent' => [
            'com.linkedin.ugc.ShareContent' => [
                'shareCommentary'    => ['text' => mb_substr($text, 0, 3000)],
                'shareMediaCategory' => 'NONE',
            ],
        ],
        'visibility' => [
            'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC',
        ],
    ];

    $res = publisher_http(MH_LI_API . '/v2/ugcPosts', [
        'method'  => 'POST',
        'headers' => [
            'Authorization: Bearer ' . $token,
            'X-Restli-Protocol-Version: 2.0.0',
        ],
        'body'    => $payload,
    ]);

    $id = $res['json']['id'] ?? null;
    if ($res['status'] === 201 && $id) {
        return ['ok' => true, 'external_id' => (string)$id, 'url' => 'https://www.linkedin.com/feed/update/' . rawurlencode($id) . '/'];
    }
    return ['ok' => false, 'error' => publisher_http_error($res, 'LinkedIn rejected the post')];
}

function mh_test_linkedin(array $config): array {
    $urn   = $config['author_urn'] ?? '';
    $token = $config['access_token'] ?? '';
    if (!$urn || !$token) return ['ok' => false, 'error' => 'Missing author_urn or access_token.'];

    // /v2/me works for member tokens; organization tokens may not
    // have profile scope, so treat 403 as "token valid, scope limited".
    $res = publisher_http(MH_LI_API . '/v2/me', [
        'headers' => ['Authorization: Bearer ' . $token],
    ]);
    if ($res['status'] === 200 || $res['status'] === 403) {
        return ['ok' => true, 'external_id' => $urn, 'url' => null];
    }
    return ['ok' => false, 'error' => publisher_http_error($res, 'Could not authenticate with LinkedIn')];
}
