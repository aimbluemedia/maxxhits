<?php
// ============================================================
//  lib/adapters/adapter.php — Publishing adapter dispatcher
//
//  Each platform module defines:
//    adapter_publish_<platform>(array $config, array $post): array
//    adapter_test_<platform>(array $config): array
//  Both return ['ok'=>true,'external_id'=>?,'url'=>?] or
//  ['ok'=>false,'error'=>message]. $config is the decrypted
//  destination credential JSON; $post is the posts row.
// ============================================================

require_once __DIR__ . '/wordpress.php';
require_once __DIR__ . '/facebook.php';
require_once __DIR__ . '/instagram.php';
require_once __DIR__ . '/twitter.php';
require_once __DIR__ . '/linkedin.php';

function adapter_publish(string $platform, array $config, array $post): array {
    $fn = 'adapter_publish_' . $platform;
    if (!function_exists($fn)) {
        return ['ok' => false, 'error' => "Platform '$platform' has no publishing adapter — it is manual-handoff only."];
    }
    return $fn($config, $post);
}

function adapter_test(string $platform, array $config): array {
    $fn = 'adapter_test_' . $platform;
    if (!function_exists($fn)) {
        return ['ok' => false, 'error' => "Platform '$platform' has no publishing adapter."];
    }
    return $fn($config);
}

/** Decrypted destination config as array. */
function destination_config(array $destination): array {
    $cfg = json_decode(decrypt_secret($destination['config_enc'] ?? null), true);
    return is_array($cfg) ? $cfg : [];
}

/**
 * Shared HTTP helper.
 * $opts: method, headers (list), body (array→JSON | string), timeout.
 * → ['status','body','json','error']
 */
function adapter_http(string $url, array $opts = []): array {
    $ch = curl_init($url);
    $headers = $opts['headers'] ?? [];
    $body    = $opts['body'] ?? null;
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_TIMEOUT        => $opts['timeout'] ?? 30,
        CURLOPT_CUSTOMREQUEST  => strtoupper($opts['method'] ?? 'GET'),
        CURLOPT_USERAGENT      => 'MaxxHits/' . (defined('MH_VERSION') ? MH_VERSION : '2.0'),
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

    if ($resBody === false) return ['status' => 0, 'body' => '', 'json' => null, 'error' => $err ?: 'Connection failed'];
    $json = json_decode($resBody, true);
    return ['status' => $status, 'body' => $resBody, 'json' => is_array($json) ? $json : null, 'error' => $err];
}

/** Readable error from an HTTP result. */
function adapter_http_error(array $res, string $fallback): string {
    if ($res['error']) return $res['error'];
    $j = $res['json'];
    if ($j) {
        $msg = $j['message'] ?? ($j['error']['message'] ?? null) ?? ($j['detail'] ?? null) ?? ($j['title'] ?? null);
        if (is_string($msg) && $msg !== '') return $msg . ' (HTTP ' . $res['status'] . ')';
    }
    return $fallback . ' (HTTP ' . $res['status'] . ')';
}
