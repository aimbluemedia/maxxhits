<?php
// ============================================================
//  api/dashboard.php — Dashboard aggregates
//  GET → { summary, revenue, today, team, automation }
//  Non-admin users see only their own companies; revenue and
//  team earnings are admin-only.
// ============================================================

require_once __DIR__ . '/_init.php';

try {
    $filter = user_company_filter();

    // Company summary (filtered for CM/SP)
    if ($filter === null) {
        $summary = $pdo->query('SELECT * FROM v_company_summary ORDER BY name')->fetchAll();
    } elseif (empty($filter)) {
        $summary = [];
    } else {
        $in = implode(',', array_fill(0, count($filter), '?'));
        $stmt = $pdo->prepare("SELECT * FROM v_company_summary WHERE id IN ($in) ORDER BY name");
        $stmt->execute($filter);
        $summary = $stmt->fetchAll();
    }
    foreach ($summary as &$co) {
        $co['posting_days'] = $co['posting_days'] ? explode(',', $co['posting_days']) : [];
        $co['fee'] = $co['fee'] !== null ? (float)$co['fee'] : null;
        foreach (['published', 'scheduled', 'total_posts'] as $k) $co[$k] = (int)$co[$k];
    }

    // Posting today (filtered)
    $today = $pdo->query('SELECT * FROM v_posting_today')->fetchAll();
    if ($filter !== null) {
        $today = array_values(array_filter($today, fn($c) => in_array($c['company_id'], $filter, true)));
    }

    // Revenue + team earnings: admin only
    $revenue = null;
    $team    = [];
    if (is_admin()) {
        $revenue = $pdo->query('SELECT * FROM v_revenue_totals')->fetch();
        $team    = $pdo->query('SELECT * FROM v_team_earnings ORDER BY total_monthly_earnings DESC')->fetchAll();
    }

    // Automation + pipeline snapshot
    $autoWhere  = '';
    $autoParams = [];
    if ($filter !== null) {
        if (empty($filter)) {
            $automation = ['drafts' => 0, 'in_review' => 0, 'approved' => 0, 'queued' => 0, 'published_today' => 0, 'failed' => 0];
            json_ok(compact('summary', 'revenue', 'today', 'team', 'automation'));
        }
        $autoWhere  = ' AND company_id IN (' . implode(',', array_fill(0, count($filter), '?')) . ')';
        $autoParams = $filter;
    }
    $stmt = $pdo->prepare("
        SELECT
          SUM(status='draft')                                                      AS drafts,
          SUM(status='review')                                                     AS in_review,
          SUM(status='approved')                                                   AS approved,
          SUM(publish_mode='auto' AND status='scheduled')                          AS queued,
          SUM(status='published' AND DATE(published_at) = CURDATE())               AS published_today,
          SUM(status='failed')                                                     AS failed
        FROM posts WHERE 1=1 $autoWhere
    ");
    $stmt->execute($autoParams);
    $automation = $stmt->fetch() ?: [];
    foreach (['drafts', 'in_review', 'approved', 'queued', 'published_today', 'failed'] as $k) {
        $automation[$k] = (int)($automation[$k] ?? 0);
    }

    json_ok(compact('summary', 'revenue', 'today', 'team', 'automation'));

} catch (PDOException $e) {
    json_error('Database error: ' . $e->getMessage(), 500);
}
