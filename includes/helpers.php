<?php
// ============================================================
//  includes/helpers.php — Shared helpers
// ============================================================

/** HTML-escape for output. */
function e($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

/** UUID v4 style identifier. */
function uid(): string {
    $b = random_bytes(16);
    $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
    $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
}

// ── JSON responses (API) ───────────────────────────────────

function json_ok($data): void {
    echo json_encode(['ok' => true, 'data' => $data]);
    exit;
}

function json_error(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

// ── Secret encryption (destination credentials, API keys) ──

function enc_key(): string {
    if (defined('APP_KEY')) return hash('sha256', APP_KEY, true);
    return str_repeat("\0", 32);
}

function encrypt_secret(?string $plain): ?string {
    if ($plain === null || $plain === '') return null;
    $iv  = random_bytes(16);
    $enc = openssl_encrypt($plain, 'AES-256-CBC', enc_key(), OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $enc);
}

function decrypt_secret(?string $stored): string {
    if (!$stored) return '';
    $raw = base64_decode($stored, true);
    if ($raw === false || strlen($raw) < 17) return '';
    $iv  = substr($raw, 0, 16);
    $enc = substr($raw, 16);
    return openssl_decrypt($enc, 'AES-256-CBC', enc_key(), OPENSSL_RAW_DATA, $iv) ?: '';
}

// ── Settings ───────────────────────────────────────────────

function get_setting(string $key, ?string $default = null): ?string {
    $pdo = db_connect();
    if (!$pdo) return $default;
    $stmt = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
    $stmt->execute([$key]);
    $v = $stmt->fetchColumn();
    return $v === false ? $default : $v;
}

function set_setting(string $key, ?string $value): void {
    $pdo = db_connect();
    if (!$pdo) return;
    $stmt = $pdo->prepare('
        INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ');
    $stmt->execute([$key, $value]);
}

/** Emergency stop blocks every outbound/publishing action. */
function emergency_stopped(): bool {
    return get_setting('emergency_stop', '0') === '1';
}

// ── Audit log ──────────────────────────────────────────────

function audit(string $action, string $targetType = '', string $targetId = '', string $message = ''): void {
    $pdo = db_connect();
    if (!$pdo) return;
    $actor = $_SESSION['mh_user']['username'] ?? 'system';
    $stmt = $pdo->prepare('
        INSERT INTO audit_events (actor, action, target_type, target_id, message)
        VALUES (?,?,?,?,?)
    ');
    $stmt->execute([$actor, $action, $targetType, $targetId, $message]);
}

// ── Validation ─────────────────────────────────────────────

function valid_date(?string $d): bool {
    if (!$d) return false;
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt !== false && $dt->format('Y-m-d') === $d;
}

function valid_time(?string $t): bool {
    return $t !== null && preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $t) === 1;
}

function valid_hex_color(?string $c): bool {
    return $c !== null && preg_match('/^#[0-9a-fA-F]{6}$/', $c) === 1;
}
