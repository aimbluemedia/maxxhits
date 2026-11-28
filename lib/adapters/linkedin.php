<?php
// ============================================================
//  lib/adapters/linkedin.php — LinkedIn adapter
//  Config: { author_urn, access_token }
//  author_urn: urn:li:organization:<id> (page) or
//              urn:li:person:<id> (profile).
//  Token scope: w_organization_social or w_member_social.
// ============================================================

const MH_LI_API = 'https://api.linkedin.com';

function adapter_publish_linkedin(array $config, array $post): array {
    $urn   = $config['author_urn'] ?? '';
    $token = $config['access_token'] ?? '';
    if (!$urn || !$token) return ['ok' => false, 'error' => 'LinkedIn destination is missing author_urn or access_token.'];

    $text = mb_substr(trim($post['title'] . "\n\n" . ($post['content'] ?? '')), 0, 3000);

    $res = adapter_http(MH_LI_API . '/v2/ugcPosts', [
        'method'  => 'POST',
        'headers' => ['Authorization: Bearer ' . $token, 'X-Restli-Protocol-Version: 2.0.0'],
        'body'    => [
            'author'          => $urn,
            'lifecycleState'  => 'PUBLISHED',
            'specificContent' => [
                'com.linkedin.ugc.ShareContent' => [
                    'shareCommentary'    => ['text' => $text],
                    'shareMediaCategory' => 'NONE',
                ],
            ],
            'visibility' => ['com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC'],
        ],
    ]);
    $id = $res['json']['id'] ?? null;
    if ($res['status'] === 201 && $id) {
        return ['ok' => true, 'external_id' => (string)$id, 'url' => 'https://www.linkedin.com/feed/update/' . rawurlencode($id) . '/'];
    }
    return ['ok' => false, 'error' => adapter_http_error($res, 'LinkedIn rejected the post')];
}

function adapter_test_linkedin(array $config): array {
    $urn   = $config['author_urn'] ?? '';
    $token = $config['access_token'] ?? '';
    if (!$urn || !$token) return ['ok' => false, 'error' => 'Missing author_urn or access_token.'];
    // 403 on /v2/me = valid token with organization-only scope.
    $res = adapter_http(MH_LI_API . '/v2/me', ['headers' => ['Authorization: Bearer ' . $token]]);
    if ($res['status'] === 200 || $res['status'] === 403) {
        return ['ok' => true, 'external_id' => $urn, 'url' => null];
    }
    return ['ok' => false, 'error' => adapter_http_error($res, 'Could not authenticate with LinkedIn')];
}
