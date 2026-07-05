<?php
// ============================================================
//  lib/publishers/publisher.php — Publishing dispatcher
//
//  Every platform module exposes two functions:
//    mh_publish_<platform>(array $config, array $post): array
//    mh_test_<platform>(array $config): array
//
//  Both return:
//    ['ok' => true,  'external_id' => ?, 'url' => ?]        on success
//    ['ok' => false, 'error' => 'human readable message']   on failure
//
//  $config is the decrypted channel config JSON.
//  $post   is the posts table row (title, content, image_url, ...).
// ============================================================

require_once __DIR__ . '/wordpress.php';
require_once __DIR__ . '/facebook.php';
require_once __DIR__ . '/instagram.php';
require_once __DIR__ . '/twitter.php';
require_once __DIR__ . '/linkedin.php';

/** Platforms with a working publisher module. */
function publisher_platforms(): array {
    return ['wordpress', 'facebook', 'instagram', 'twitter', 'linkedin'];
}

/** Publish a post through a channel. */
function publisher_publish(string $platform, array $config, array $post): array {
    $fn = 'mh_publish_' . $platform;
    if (!function_exists($fn)) {
        return ['ok' => false, 'error' => "No publisher available for platform '$platform'."];
    }
    return $fn($config, $post);
}

/** Test a channel's credentials without posting. */
function publisher_test(string $platform, array $config): array {
    $fn = 'mh_test_' . $platform;
    if (!function_exists($fn)) {
        return ['ok' => false, 'error' => "No publisher available for platform '$platform'."];
    }
    return $fn($config);
}

/**
 * Shared HTTP helper (cURL).
 * $opts: method, headers (list), body (string|array), timeout.
 * Returns ['status' => int, 'body' => string, 'json' => ?array, 'error' => ?string]
 */
function publisher_http(string $url, array $opts = []): array {
    $ch = curl_init($url);
    $method  = strtoupper($opts['method'] ?? 'GET');
    $headers = $opts['headers'] ?? [];
    $body    = $opts['body'] ?? null;

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_TIMEOUT        => $opts['timeout'] ?? 30,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_USERAGENT      => 'MaxxHits/' . (defined('MAXXHITS_VERSION') ? MAXXHITS_VERSION : '1.0'),
    ]);
    if ($body !== null) {
        if (is_array($body)) {
            $body = json_encode($body);
            $headers[] = 'Content-Type: application/json';
        }
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    if ($headers) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $resBody = curl_exec($ch);
    $status  = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err     = curl_error($ch) ?: null;
    curl_close($ch);

    if ($resBody === false) {
        return ['status' => 0, 'body' => '', 'json' => null, 'error' => $err ?: 'Connection failed'];
    }
    $json = json_decode($resBody, true);
    return ['status' => $status, 'body' => $resBody, 'json' => is_array($json) ? $json : null, 'error' => $err];
}

/** Extract a readable error out of an HTTP result. */
function publisher_http_error(array $res, string $fallback): string {
    if ($res['error']) return $res['error'];
    $j = $res['json'];
    if ($j) {
        // WordPress: {message}, Meta: {error:{message}}, Twitter: {detail|title}, LinkedIn: {message}
        $msg = $j['message'] ?? ($j['error']['message'] ?? null) ?? ($j['detail'] ?? null) ?? ($j['title'] ?? null);
        if (is_string($msg) && $msg !== '') return $msg . ' (HTTP ' . $res['status'] . ')';
    }
    return $fallback . ' (HTTP ' . $res['status'] . ')';
}
