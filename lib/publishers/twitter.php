<?php
// ============================================================
//  lib/publishers/twitter.php — X (Twitter) publisher
//
//  Channel config: { access_token }
//  access_token is an OAuth 2.0 user context token with the
//  tweet.read, tweet.write and users.read scopes (X API v2).
//  Note: OAuth2 user tokens expire — refresh them in the
//  channel settings when posting starts failing with 401.
// ============================================================

const MH_X_API = 'https://api.twitter.com/2';

function mh_publish_twitter(array $config, array $post): array {
    $token = $config['access_token'] ?? '';
    if (!$token) return ['ok' => false, 'error' => 'X/Twitter channel is missing access_token.'];

    $text = trim($post['title'] . "\n\n" . ($post['content'] ?? ''));
    if (!empty($post['post_url'])) $text .= "\n" . $post['post_url'];
    $text = mb_substr($text, 0, 280);

    $res = publisher_http(MH_X_API . '/tweets', [
        'method'  => 'POST',
        'headers' => ['Authorization: Bearer ' . $token],
        'body'    => ['text' => $text],
    ]);

    $id = $res['json']['data']['id'] ?? null;
    if ($res['status'] === 201 && $id) {
        return ['ok' => true, 'external_id' => (string)$id, 'url' => 'https://x.com/i/status/' . $id];
    }
    return ['ok' => false, 'error' => publisher_http_error($res, 'X/Twitter rejected the post')];
}

function mh_test_twitter(array $config): array {
    $token = $config['access_token'] ?? '';
    if (!$token) return ['ok' => false, 'error' => 'Missing access_token.'];

    $res = publisher_http(MH_X_API . '/users/me', [
        'headers' => ['Authorization: Bearer ' . $token],
    ]);
    if ($res['status'] === 200 && !empty($res['json']['data']['id'])) {
        return ['ok' => true, 'external_id' => $res['json']['data']['id'], 'url' => null];
    }
    return ['ok' => false, 'error' => publisher_http_error($res, 'Could not authenticate with X/Twitter')];
}
