<?php
// ============================================================
//  lib/ai.php — AI content generation
//
//  Providers (chosen in settings, admin-configured on the
//  Content Studio page):
//    anthropic — Claude API, default model claude-opus-5-5
//    xai       — Grok API (OpenAI-compatible chat completions)
//
//  API keys are stored AES-encrypted in the settings table
//  (ai_key_anthropic_enc / ai_key_xai_enc). Raw cURL is used
//  deliberately: this project runs on shared hosting with no
//  Composer (see CLAUDE.md).
// ============================================================

const AI_PROVIDERS = [
    'anthropic' => ['label' => 'Claude (Anthropic)', 'default_model' => 'claude-opus-5-5'],
    'xai'       => ['label' => 'Grok (xAI)',         'default_model' => 'grok-4'],
];

function ai_settings(): array {
    $provider = get_setting('ai_provider', 'anthropic');
    if (!isset(AI_PROVIDERS[$provider])) $provider = 'anthropic';
    $model = get_setting('ai_model') ?: AI_PROVIDERS[$provider]['default_model'];
    return [
        'provider'          => $provider,
        'model'             => $model,
        'has_key_anthropic' => (bool)get_setting('ai_key_anthropic_enc'),
        'has_key_xai'       => (bool)get_setting('ai_key_xai_enc'),
    ];
}

function ai_api_key(string $provider): string {
    return decrypt_secret(get_setting('ai_key_' . $provider . '_enc'));
}

/**
 * Generate post variants.
 * $opts: company (row), platform, topic, instructions, count (1-5)
 * Returns ['ok'=>true, 'variants'=>[['title','content'],…]]
 *      or ['ok'=>false, 'error'=>msg]
 */
function ai_generate(array $opts): array {
    $settings = ai_settings();
    $provider = $settings['provider'];
    $key = ai_api_key($provider);
    if ($key === '') {
        return ['ok' => false, 'error' => 'No API key configured for ' . AI_PROVIDERS[$provider]['label'] . '. An admin can add one in Content Studio → AI settings.'];
    }

    $count = max(1, min(5, (int)($opts['count'] ?? 3)));
    $system = ai_build_system_prompt($opts['company'] ?? [], $opts['platform'] ?? 'blog');
    $user = ai_build_user_prompt($opts, $count);

    $result = $provider === 'anthropic'
        ? ai_call_anthropic($key, $settings['model'], $system, $user)
        : ai_call_xai($key, $settings['model'], $system, $user);

    if (!$result['ok']) return $result;

    $variants = ai_parse_variants($result['text'], $count);
    if (!$variants) {
        return ['ok' => false, 'error' => 'The AI response could not be parsed. Try again.'];
    }
    return ['ok' => true, 'variants' => $variants, 'model' => $settings['model']];
}

// ── Prompt building ────────────────────────────────────────

function ai_build_system_prompt(array $company, string $platform): string {
    $platformGuides = [
        'blog'      => 'a long-form SEO blog article: engaging headline, 500-900 word body in clean HTML-free plain text with short paragraphs',
        'facebook'  => 'a Facebook page post: conversational, 40-120 words, light on hashtags',
        'instagram' => 'an Instagram caption: hook first line, 30-100 words, 3-8 relevant hashtags at the end',
        'twitter'   => 'an X/Twitter post: at most 280 characters total including hashtags',
        'linkedin'  => 'a LinkedIn post: professional but human, 60-180 words, a clear takeaway, minimal hashtags',
        'tiktok'    => 'a TikTok video caption + a short spoken-script outline',
        'youtube'   => 'a YouTube video title + description with keywords',
        'reddit'    => 'a Reddit post: genuinely useful, community-first tone, no marketing speak',
        'pinterest' => 'a Pinterest pin title + description with searchable keywords',
    ];
    $guide = $platformGuides[$platform] ?? 'a social media post';

    $lines = [
        'You are the content writer for a social media marketing agency.',
        'Write original, platform-appropriate marketing content. Each piece is ' . $guide . '.',
        'Never invent statistics, reviews, testimonials or medical claims. Keep claims general and verifiable.',
    ];
    if (!empty($company['name']))        $lines[] = 'Brand: ' . $company['name'];
    if (!empty($company['website_url'])) $lines[] = 'Website: ' . $company['website_url'];
    if (!empty($company['brand_voice'])) $lines[] = 'Brand voice and rules: ' . $company['brand_voice'];

    $lines[] = 'Respond with ONLY a JSON array, no other text: [{"title": "...", "content": "..."}, ...]. '
             . '"title" is a short internal working title; "content" is the full post text ready to publish.';
    return implode("\n", $lines);
}

function ai_build_user_prompt(array $opts, int $count): string {
    $p = 'Write ' . $count . ' distinct variant' . ($count > 1 ? 's' : '')
       . ' of a ' . ($opts['platform'] ?? 'blog') . ' post about: ' . trim($opts['topic'] ?? '');
    if (!empty($opts['instructions'])) {
        $p .= "\nAdditional instructions: " . trim($opts['instructions']);
    }
    return $p;
}

// ── Providers ──────────────────────────────────────────────

/** Claude API (Messages). Returns ['ok', 'text'|'error']. */
function ai_call_anthropic(string $key, string $model, string $system, string $user): array {
    $body = [
        'model'      => $model,
        'max_tokens' => 8000,
        'system'     => $system,
        'messages'   => [['role' => 'user', 'content' => $user]],
        // Route refusal-classifier declines to a fallback model
        // automatically instead of failing the request.
        'fallbacks'  => 'default',
    ];
    $res = ai_http('https://api.anthropic.com/v1/messages', [
        'x-api-key: ' . $key,
        'anthropic-version: 2023-06-01',
        'anthropic-beta: server-side-fallback-2026-07-01',
        'Content-Type: application/json',
    ], $body);

    if (!$res['ok']) return $res;
    $j = $res['json'];
    if (isset($j['error'])) {
        return ['ok' => false, 'error' => 'Claude API: ' . ($j['error']['message'] ?? 'unknown error')];
    }
    if (($j['stop_reason'] ?? '') === 'refusal') {
        return ['ok' => false, 'error' => 'The AI declined this request'
            . (!empty($j['stop_details']['explanation']) ? ': ' . $j['stop_details']['explanation'] : '.')];
    }
    $text = '';
    foreach ($j['content'] ?? [] as $block) {
        if (($block['type'] ?? '') === 'text') $text .= $block['text'];
    }
    if ($text === '') return ['ok' => false, 'error' => 'Claude API returned an empty response.'];
    return ['ok' => true, 'text' => $text];
}

/** xAI Grok (OpenAI-compatible chat completions). */
function ai_call_xai(string $key, string $model, string $system, string $user): array {
    $body = [
        'model'    => $model,
        'messages' => [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user',   'content' => $user],
        ],
    ];
    $res = ai_http('https://api.x.ai/v1/chat/completions', [
        'Authorization: Bearer ' . $key,
        'Content-Type: application/json',
    ], $body);

    if (!$res['ok']) return $res;
    $j = $res['json'];
    if (isset($j['error'])) {
        $msg = is_array($j['error']) ? ($j['error']['message'] ?? 'unknown error') : (string)$j['error'];
        return ['ok' => false, 'error' => 'Grok API: ' . $msg];
    }
    $text = $j['choices'][0]['message']['content'] ?? '';
    if ($text === '') return ['ok' => false, 'error' => 'Grok API returned an empty response.'];
    return ['ok' => true, 'text' => $text];
}

/** Shared POST-JSON helper. Returns ['ok', 'json'|'error']. */
function ai_http(string $url, array $headers, array $body): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($body),
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 120,
    ]);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return ['ok' => false, 'error' => 'Could not reach the AI provider: ' . ($err ?: 'connection failed')];
    }
    $json = json_decode($raw, true);
    if (!is_array($json)) {
        return ['ok' => false, 'error' => 'AI provider returned an invalid response.'];
    }
    return ['ok' => true, 'json' => $json];
}

// ── Response parsing ───────────────────────────────────────

/** Extract [{title, content}] from the model's text output. */
function ai_parse_variants(string $text, int $count): array {
    // Strip code fences if present, then find the JSON array
    $text = preg_replace('/^```(json)?|```$/m', '', trim($text));
    $start = strpos($text, '[');
    $end   = strrpos($text, ']');
    if ($start !== false && $end !== false && $end > $start) {
        $parsed = json_decode(substr($text, $start, $end - $start + 1), true);
        if (is_array($parsed)) {
            $variants = [];
            foreach ($parsed as $v) {
                if (is_array($v) && !empty($v['content'])) {
                    $variants[] = [
                        'title'   => trim((string)($v['title'] ?? 'Untitled draft')),
                        'content' => trim((string)$v['content']),
                    ];
                }
            }
            if ($variants) return array_slice($variants, 0, $count);
        }
    }
    // Fallback: treat the whole response as a single variant
    $plain = trim($text);
    if ($plain !== '') {
        return [['title' => 'AI draft', 'content' => $plain]];
    }
    return [];
}
