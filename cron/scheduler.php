<?php
// ============================================================
//  cron/scheduler.php — MaxxHits scheduler
//
//  Each run: (1) executes due automations (AI research → drafts
//  into the Approval center), then (2) publishes due approved
//  posts through their destinations. Emergency stop halts both.
//
//  Run it either way:
//   CLI:  php /path/to/maxxhits/cron/scheduler.php
//   HTTP: https://your-site.com/cron/scheduler.php?key=CRON_KEY
//  (the key is shown on the Settings page, admin only)
//
//  Suggested schedule: every 5–15 minutes.
// ============================================================

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$is_cli = PHP_SAPI === 'cli';

if (!$is_cli) {
    header('Content-Type: application/json; charset=utf-8');
    $stored = get_setting('cron_key');
    if (!$stored || !hash_equals($stored, $_GET['key'] ?? '')) {
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

require_once APP_ROOT . '/includes/runner.php';

$automationResults = run_due_automations($pdo);
$publishResults    = run_due_posts($pdo);

$pubOk   = count(array_filter($publishResults, fn($r) => $r['ok']));
$pubFail = count($publishResults) - $pubOk;

if ($is_cli) {
    foreach ($automationResults as $r) {
        echo ($r['ok'] ? '[AUTO OK]  ' : '[AUTO ERR] ') . $r['automation'] . ' — ' . $r['message'] . "\n";
    }
    foreach ($publishResults as $r) {
        echo ($r['ok'] ? '[PUB OK]   ' : '[PUB ERR]  ') . $r['title']
           . ($r['ok'] ? ($r['url'] ? ' → ' . $r['url'] : '') : ' — ' . $r['error']) . "\n";
    }
    echo 'Done: ' . count($automationResults) . " automation runs, {$pubOk} published, {$pubFail} failed.\n";
    exit($pubFail > 0 ? 1 : 0);
}

echo json_encode([
    'ok'          => true,
    'stopped'     => emergency_stopped(),
    'automations' => $automationResults,
    'published'   => $pubOk,
    'failed'      => $pubFail,
    'publish'     => $publishResults,
]);
