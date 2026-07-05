<?php
// ============================================================
//  cron/publish-scheduler.php — Automated publishing runner
//
//  Publishes every due auto-mode post (status=scheduled,
//  post_date/publish_time in the past) through its channel.
//
//  Run it either way:
//   1. CLI (preferred):
//        php /path/to/maxxhits/cron/publish-scheduler.php
//   2. HTTP (Hostinger cron / external cron service):
//        https://your-site.com/cron/publish-scheduler.php?key=CRON_KEY
//      The key is shown on the Automation page (admin only).
//
//  Suggested schedule: every 5–15 minutes.
// ============================================================

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$is_cli = PHP_SAPI === 'cli';

if (!$is_cli) {
    header('Content-Type: application/json; charset=utf-8');
    $key    = $_GET['key'] ?? '';
    $stored = get_setting('cron_key');
    if (!$stored || !hash_equals($stored, $key)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Invalid cron key.']);
        exit;
    }
}

$pdo = db_connect();
if (!$pdo) {
    $msg = 'Database not connected.';
    if ($is_cli) { fwrite(STDERR, $msg . "\n"); exit(1); }
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

require_once APP_ROOT . '/includes/publish-runner.php';

$results = run_due_posts($pdo);

$ok   = count(array_filter($results, fn($r) => $r['ok']));
$fail = count($results) - $ok;

if ($is_cli) {
    foreach ($results as $r) {
        echo ($r['ok'] ? '[OK]  ' : '[ERR] ') . $r['title']
           . ($r['ok'] ? ($r['url'] ? ' → ' . $r['url'] : '') : ' — ' . $r['error']) . "\n";
    }
    echo "Done: {$ok} published, {$fail} failed, " . count($results) . " total.\n";
    exit($fail > 0 ? 1 : 0);
}

echo json_encode([
    'ok'        => true,
    'processed' => count($results),
    'published' => $ok,
    'failed'    => $fail,
    'results'   => $results,
]);
