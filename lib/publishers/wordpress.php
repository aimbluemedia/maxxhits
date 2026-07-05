<?php
// ============================================================
//  lib/publishers/wordpress.php — WordPress REST API publisher
//
//  Channel config: { site_url, username, app_password }
//  Uses WordPress Application Passwords (Users → Profile →
//  Application Passwords) over the core REST API.
// ============================================================

function mh_wp_auth_header(array $config): string {
    return 'Authorization: Basic ' . base64_encode(
        ($config['username'] ?? '') . ':' . ($config['app_password'] ?? '')
    );
}

function mh_wp_base(array $config): string {
    return rtrim($config['site_url'] ?? '', '/');
}

function mh_publish_wordpress(array $config, array $post): array {
    $base = mh_wp_base($config);
    if (!$base || empty($config['username']) || empty($config['app_password'])) {
        return ['ok' => false, 'error' => 'WordPress channel is missing site_url, username or app_password.'];
    }

    $payload = [
        'title'   => $post['title'],
        'content' => $post['content'] ?? '',
        'status'  => 'publish',
    ];

    // Attach featured image by sideloading the URL, if given.
    if (!empty($post['image_url'])) {
        $mediaId = mh_wp_sideload_image($config, $post['image_url'], $post['title']);
        if ($mediaId) $payload['featured_media'] = $mediaId;
    }

    $res = publisher_http($base . '/wp-json/wp/v2/posts', [
        'method'  => 'POST',
        'headers' => [mh_wp_auth_header($config)],
        'body'    => $payload,
    ]);

    if ($res['status'] === 201 && !empty($res['json']['id'])) {
        return [
            'ok'          => true,
            'external_id' => (string)$res['json']['id'],
            'url'         => $res['json']['link'] ?? null,
        ];
    }
    return ['ok' => false, 'error' => publisher_http_error($res, 'WordPress rejected the post')];
}

/** Download an image and upload it to the WP media library. Returns media ID or null. */
function mh_wp_sideload_image(array $config, string $imageUrl, string $title): ?int {
    $img = publisher_http($imageUrl, ['timeout' => 20]);
    if ($img['status'] !== 200 || $img['body'] === '') return null;

    $ext  = strtolower(pathinfo(parse_url($imageUrl, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION)) ?: 'jpg';
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) $ext = 'jpg';
    $mime = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'][$ext];
    $name = preg_replace('/[^a-z0-9]+/i', '-', substr($title, 0, 50)) . '.' . $ext;

    $res = publisher_http(mh_wp_base($config) . '/wp-json/wp/v2/media', [
        'method'  => 'POST',
        'headers' => [
            mh_wp_auth_header($config),
            'Content-Type: ' . $mime,
            'Content-Disposition: attachment; filename="' . $name . '"',
        ],
        'body'    => $img['body'],
        'timeout' => 60,
    ]);
    return ($res['status'] === 201 && !empty($res['json']['id'])) ? (int)$res['json']['id'] : null;
}

function mh_test_wordpress(array $config): array {
    $base = mh_wp_base($config);
    if (!$base || empty($config['username']) || empty($config['app_password'])) {
        return ['ok' => false, 'error' => 'Missing site_url, username or app_password.'];
    }
    $res = publisher_http($base . '/wp-json/wp/v2/users/me', [
        'headers' => [mh_wp_auth_header($config)],
    ]);
    if ($res['status'] === 200 && !empty($res['json']['id'])) {
        return ['ok' => true, 'external_id' => (string)$res['json']['id'], 'url' => null];
    }
    return ['ok' => false, 'error' => publisher_http_error($res, 'Could not authenticate with WordPress')];
}
