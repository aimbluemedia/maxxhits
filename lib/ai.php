<?php
// ============================================================
//  lib/ai.php — AI content generation
//  Providers: anthropic (Claude, default claude-opus-5-5) and
//  xai (Grok, OpenAI-compatible). Raw cURL by design: this app
//  runs on shared hosting with no Composer.
//  Keys live AES-encrypted in settings (ai_key_*_enc).
// ============================================================

const AI_PROVIDERS = [
    'anthropic' => ['label' => 'Claude (Anthropic)', 'default_model' => 'claude-opus-5-5'],
    'xai'       => ['label' => 'Grok (xAI)',         'default_model' => 'grok-4'],
];

function ai_settings(): array {
    $provider = get_setting('ai_provider', 'anthropic');
    if (!isset(AI_PROVIDERS[$provider])) $provider = 'anthropic';
    return [
        'provider'          => $provider,
        'model'             => get_setting('ai_model') ?: AI_PROVIDERS[$provider]['default_model'],
        'has_key_anthropic' => (bool)get_setting('ai_key_anthropic_enc'),
        'has_key_xai'       => (bool)get_setting('ai_key_xai_enc'),
    ];
}

/**
 * Generate content variants for a brand + platform.
 * $opts: brand (row), platform, topic, instructions, count (1-5)
 * → ['ok'=>true,'variants'=>[['title','content'],…],'model'=>…] | ['ok'=>false,'error'=>…]
 */
function ai_generate(array $opts): array {
    $s = ai_settings();
    $key = decrypt_secret(get_setting('ai_key_' . $s['provider'] . '_enc'));
    if ($key === '') {
        return ['ok' => false, 'error' => 'No API key configured for ' . AI_PROVIDERS[$s['provider']]['label'] . '. Add one in Settings.'];
    }

    $count  = max(1, min(5, (int)($opts['count'] ?? 1)));
    $system = ai_system_prompt($opts['brand'] ?? [], $opts['platform'] ?? 'wordpress');
    $user   = 'Write ' . $count . ' distinct variant' . ($count > 1 ? 's' : '')
            . ' about: ' . trim($opts['topic'] ?? '');
    if (!empty($opts['instructions'])) $user .= "\nAdditional instructions: " . trim($opts['instructions']);

    $r = $s['provider'] === 'anthropic'
        ? ai_call_anthropic($key, $s['model'], $system, $user)
        : ai_call_xai($key, $s['model'], $system, $user);
    if (!$r['ok']) return $r;

    $variants = ai_parse_variants($r['text'], $count);
    if (!$variants) return ['ok' => false, 'error' => 'The AI response could not be parsed. Try again.'];
    return ['ok' => true, 'variants' => $variants, 'model' => $s['model']];
}

function ai_system_prompt(array $brand, string $platform): string {
    $guides = [
        'wordpress' => 'a long-form SEO blog article: engaging headline, 500-900 word plain-text body with short paragraphs',
        'facebook'  => 'a Facebook page post: conversational, 40-120 words, light on hashtags',
        'instagram' => 'an Instagram caption: hook first line, 30-100 words, 3-8 relevant hashtags at the end',
        'twitter'   => 'an X post: at most 280 characters total including hashtags',
        'linkedin'  => 'a LinkedIn post: professional but human, 60-180 words, a clear takeaway, minimal hashtags',
        'reddit'    => 'a Reddit post for staff review: genuinely useful, community-first tone, zero marketing speak',
        'pinterest' => 'a Pinterest pin title + description with searchable keywords',
        'tiktok'    => 'a TikTok caption plus a short spoken-script outline',
        'youtube'   => 'a YouTube video title + keyword-rich description',
        'threads'   => 'a Threads post: conversational, under 500 characters',
    ];
    $lines = [
        'You are the content writer for a marketing team.',
        'Write original, platform-appropriate content. Each piece is ' . ($guides[$platform] ?? 'a social media post') . '.',
        'Never invent statistics, reviews, testimonials or medical claims. No fake patient stories. Keep claims general and verifiable.',
    ];
    if (!empty($brand['name']))        $lines[] = 'Brand: ' . $brand['name'];
    if (!empty($brand['website_url'])) $lines[] = 'Website: ' . $brand['website_url'];
    if (!empty($brand['brand_voice'])) $lines[] = 'Brand voice and rules: ' . $brand['brand_voice'];
    $lines[] = 'Respond with ONLY a JSON array, no other text: [{"title": "...", "content": "..."}]. '
             . '"title" is a short internal working title; "content" is the full post text ready to publish.';
    return implode("\n", $lines);
}

function ai_call_anthropic(string $key, string $model, string $system, string $user): array {
    $res = ai_http('https://api.anthropic.com/v1/messages', [
        'x-api-key: ' . $key,
        'anthropic-version: 2023-06-01',
        'anthropic-beta: server-side-fallback-2026-07-01',
        'Content-Type: application/json',
    ], [
        'model'      => $model,
        'max_tokens' => 8000,
        'system'     => $system,
        'messages'   => [['role' => 'user', 'content' => $user]],
        'fallbacks'  => 'default', // refusal-classifier declines reroute automatically
    ]);
    if (!$res['ok']) return $res;
    $j = $res['json'];
    if (isset($j['error'])) return ['ok' => false, 'error' => 'Claude API: ' . ($j['error']['message'] ?? 'unknown error')];
    if (($j['stop_reason'] ?? '') === 'refusal') {
        return ['ok' => false, 'error' => 'The AI declined this request'
            . (!empty($j['stop_details']['explanation']) ? ': ' . $j['stop_details']['explanation'] : '.')];
    }
    $text = '';
    foreach ($j['content'] ?? [] as $block) {
        if (($block['type'] ?? '') === 'text') $text .= $block['text'];
    }
    return $text !== '' ? ['ok' => true, 'text' => $text]
                        : ['ok' => false, 'error' => 'Claude API returned an empty response.'];
}

function ai_call_xai(string $key, string $model, string $system, string $user): array {
    $res = ai_http('https://api.x.ai/v1/chat/completions', [
        'Authorization: Bearer ' . $key,
        'Content-Type: application/json',
    ], [
        'model'    => $model,
        'messages' => [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user',   'content' => $user],
        ],
    ]);
    if (!$res['ok']) return $res;
    $j = $res['json'];
    if (isset($j['error'])) {
        $msg = is_array($j['error']) ? ($j['error']['message'] ?? 'unknown error') : (string)$j['error'];
        return ['ok' => false, 'error' => 'Grok API: ' . $msg];
    }
    $text = $j['choices'][0]['message']['content'] ?? '';
    return $text !== '' ? ['ok' => true, 'text' => $text]
                        : ['ok' => false, 'error' => 'Grok API returned an empty response.'];
}

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
    if ($raw === false) return ['ok' => false, 'error' => 'Could not reach the AI provider: ' . ($err ?: 'connection failed')];
    $json = json_decode($raw, true);
    if (!is_array($json)) return ['ok' => false, 'error' => 'AI provider returned an invalid response.'];
    return ['ok' => true, 'json' => $json];
}

function ai_parse_variants(string $text, int $count): array {
    $text  = preg_replace('/^```(json)?|```$/m', '', trim($text));
    $start = strpos($text, '[');
    $end   = strrpos($text, ']');
    if ($start !== false && $end !== false && $end > $start) {
        $parsed = json_decode(substr($text, $start, $end - $start + 1), true);
        if (is_array($parsed)) {
            $out = [];
            foreach ($parsed as $v) {
                if (is_array($v) && !empty($v['content'])) {
                    $out[] = [
                        'title'   => trim((string)($v['title'] ?? 'Untitled draft')),
                        'content' => trim((string)$v['content']),
                    ];
                }
            }
            if ($out) return array_slice($out, 0, $count);
        }
    }
    $plain = trim($text);
    return $plain !== '' ? [['title' => 'AI draft', 'content' => $plain]] : [];
}
