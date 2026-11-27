<?php
// ============================================================
//  lib/adapters/twitter.php — X (Twitter) adapter
//  Config: { access_token } — OAuth 2.0 user token with
//  tweet.read, tweet.write, users.read scopes (X API v2).
//  Tokens expire: refresh in the destination when 401s appear.
// ============================================================

const MH_X_API = 'https://api.twitter.com/2';

function adapter_publish_twitter(array $config, array $post): array {
    $token = $config['access_token'] ?? '';
    if (!$token) return ['ok' => false, 'error' => 'X destination is missing access_token.'];

    $text = trim($post['title'] . "\n\n" . ($post['content'] ?? ''));
    $text = mb_substr($text, 0, 280);

    $res = adapter_http(MH_X_API . '/tweets', [
        'method'  => 'POST',
        'headers' => ['Authorization: Bearer ' . $token],
        'body'    => ['text' => $text],
    ]);
    $id = $res['json']['data']['id'] ?? null;
    if ($res['status'] === 201 && $id) {
        return ['ok' => true, 'external_id' => (string)$id, 'url' => 'https://x.com/i/status/' . $id];
    }
    return ['ok' => false, 'error' => adapter_http_error($res, 'X rejected the post')];
}

function adapter_test_twitter(array $config): array {
    $token = $config['access_token'] ?? '';
    if (!$token) return ['ok' => false, 'error' => 'Missing access_token.'];
    $res = adapter_http(MH_X_API . '/users/me', ['headers' => ['Authorization: Bearer ' . $token]]);
    if ($res['status'] === 200 && !empty($res['json']['data']['id'])) {
        return ['ok' => true, 'external_id' => $res['json']['data']['id'], 'url' => null];
    }
    return ['ok' => false, 'error' => adapter_http_error($res, 'Could not authenticate with X')];
}
