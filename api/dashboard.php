<?php
// ============================================================
//  api/dashboard.php — Overview aggregates
//  GET → pipeline counts, brand summaries, recent activity
// ============================================================

require_once __DIR__ . '/_init.php';

try {
    [$bw, $bp] = brand_where('brand_id');

    $stmt = $pdo->prepare("
        SELECT
          COUNT(*)                                                    AS total,
          SUM(status='draft')                                         AS drafts,
          SUM(status='review')                                        AS in_review,
          SUM(status='approved')                                      AS approved,
          SUM(status='scheduled')                                     AS scheduled,
          SUM(status='published' AND DATE(published_at) = CURDATE())  AS published_today,
          SUM(status='published')                                     AS published_total,
          SUM(status='failed')                                        AS failed
        FROM posts WHERE 1=1 $bw
    ");
    $stmt->execute($bp);
    $pipeline = $stmt->fetch() ?: [];
    foreach ($pipeline as $k => $v) $pipeline[$k] = (int)$v;

    [$abw, $abp] = brand_where('brand_id');
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS total, SUM(enabled=1) AS enabled
        FROM automations WHERE 1=1 $abw
    ");
    $stmt->execute($abp);
    $automations = $stmt->fetch() ?: [];
    foreach ($automations as $k => $v) $automations[$k] = (int)$v;

    // Per-brand summary (all brands regardless of workspace filter)
    $brands = $pdo->query("
        SELECT b.id, b.name, b.color, b.is_active,
               SUM(p.status='review')    AS in_review,
               SUM(p.status='scheduled') AS scheduled,
               SUM(p.status='published') AS published,
               SUM(p.status='failed')    AS failed,
               COUNT(p.id)               AS total_posts
        FROM brands b
        LEFT JOIN posts p ON p.brand_id = b.id
        WHERE b.is_active = 1
        GROUP BY b.id, b.name, b.color, b.is_active
        ORDER BY b.name
    ")->fetchAll();
    foreach ($brands as &$b) {
        foreach (['in_review', 'scheduled', 'published', 'failed', 'total_posts'] as $k) $b[$k] = (int)$b[$k];
    }

    // Exceptions: failed posts + failed automation runs (recent)
    [$fbw, $fbp] = brand_where('p.brand_id');
    $stmt = $pdo->prepare("
        SELECT p.id, p.title, p.publish_error, p.updated_at, b.name AS brand_name
        FROM posts p JOIN brands b ON b.id = p.brand_id
        WHERE p.status='failed' $fbw
        ORDER BY p.updated_at DESC LIMIT 10
    ");
    $stmt->execute($fbp);
    $failures = $stmt->fetchAll();

    $recentRuns = $pdo->query("
        SELECT r.status, r.message, r.created_at, a.name AS automation_name
        FROM automation_runs r JOIN automations a ON a.id = r.automation_id
        ORDER BY r.id DESC LIMIT 10
    ")->fetchAll();

    json_ok(compact('pipeline', 'automations', 'brands', 'failures', 'recentRuns'));

} catch (PDOException $e) {
    json_error('Database error: ' . $e->getMessage(), 500);
}
