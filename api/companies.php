<?php
// ============================================================
//  api/companies.php — Companies CRUD
//  GET               → list companies visible to current user
//  GET  ?id=X        → single company
//  POST action=save  → create/update (admin)
//  POST action=delete→ delete (admin)
// ============================================================

require_once __DIR__ . '/_init.php';

$action = $_GET['action'] ?? $body['action'] ?? 'list';

try {
    switch ($action) {

        case 'list':
            $filter = user_company_filter();
            if ($filter !== null && empty($filter)) json_ok([]);

            $sql = "
                SELECT c.*,
                       cm.name AS content_manager_name,
                       sp.name AS sales_person_name
                FROM companies c
                LEFT JOIN people cm ON cm.id = c.content_manager_id
                LEFT JOIN people sp ON sp.id = c.sales_person_id
            ";
            $params = [];
            if ($filter !== null) {
                $sql .= ' WHERE c.id IN (' . implode(',', array_fill(0, count($filter), '?')) . ')';
                $params = $filter;
            }
            $sql .= ' ORDER BY c.name';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();

            $counts = $pdo->query("
                SELECT company_id,
                       COUNT(*) AS total,
                       SUM(status='published') AS published,
                       SUM(status='scheduled') AS scheduled,
                       SUM(status='failed')    AS failed
                FROM posts GROUP BY company_id
            ")->fetchAll(PDO::FETCH_UNIQUE);

            foreach ($rows as &$co) {
                $co['posting_days']  = $co['posting_days'] ? explode(',', $co['posting_days']) : [];
                $co['monthly_posts'] = $co['monthly_posts'] !== null ? (int)$co['monthly_posts'] : null;
                $co['fee']           = $co['fee'] !== null ? (float)$co['fee'] : null;
                $co['fee_sp_pct']    = (float)$co['fee_sp_pct'];
                $co['fee_cm_pct']    = (float)$co['fee_cm_pct'];
                $co['fee_sm_pct']    = (float)$co['fee_sm_pct'];
                $co['payment_date']  = $co['payment_date'] !== null ? (int)$co['payment_date'] : null;
                $co['post_counts']   = $counts[$co['id']] ?? ['total' => 0, 'published' => 0, 'scheduled' => 0, 'failed' => 0];
            }
            json_ok($rows);
            break;

        case 'get':
            $id = $_GET['id'] ?? '';
            if (!$id) json_error('Missing id', 422);
            if (!can_view_company($id)) json_error('Forbidden', 403);
            $stmt = $pdo->prepare("
                SELECT c.*,
                       cm.name AS content_manager_name,
                       sp.name AS sales_person_name
                FROM companies c
                LEFT JOIN people cm ON cm.id = c.content_manager_id
                LEFT JOIN people sp ON sp.id = c.sales_person_id
                WHERE c.id = ?
            ");
            $stmt->execute([$id]);
            $co = $stmt->fetch();
            if (!$co) json_error('Company not found', 404);
            $co['posting_days'] = $co['posting_days'] ? explode(',', $co['posting_days']) : [];
            json_ok($co);
            break;

        case 'save':
            require_admin_api();
            $id     = $body['id'] ?: uid();
            $name   = trim($body['name'] ?? '');
            $color  = $body['color'] ?? '#6c47ff';
            $cmId   = $body['content_manager_id'] ?: null;
            $spId   = $body['sales_person_id'] ?: null;
            $posts  = isset($body['monthly_posts']) && $body['monthly_posts'] !== '' ? (int)$body['monthly_posts'] : null;
            $fee    = isset($body['fee']) && $body['fee'] !== '' ? (float)$body['fee'] : null;
            $spPct  = isset($body['fee_sp_pct']) ? (float)$body['fee_sp_pct'] : 40;
            $cmPct  = isset($body['fee_cm_pct']) ? (float)$body['fee_cm_pct'] : 40;
            $smPct  = isset($body['fee_sm_pct']) ? (float)$body['fee_sm_pct'] : 20;
            $payDay = isset($body['payment_date']) && $body['payment_date'] !== '' ? (int)$body['payment_date'] : null;
            $days   = !empty($body['posting_days']) && is_array($body['posting_days'])
                        ? implode(',', array_intersect($body['posting_days'], ['Mon','Tue','Wed','Thu','Fri','Sat','Sun']))
                        : null;

            if (!$name) json_error('Company name is required.', 422);
            if (!valid_hex_color($color)) $color = '#6c47ff';
            if ($payDay !== null && ($payDay < 1 || $payDay > 31)) json_error('Payment date must be 1–31.', 422);

            $stmt = $pdo->prepare("
                INSERT INTO companies
                  (id, name, color, content_manager_id, sales_person_id, monthly_posts,
                   fee, fee_sp_pct, fee_cm_pct, fee_sm_pct, payment_date, posting_days)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE
                  name=VALUES(name), color=VALUES(color),
                  content_manager_id=VALUES(content_manager_id),
                  sales_person_id=VALUES(sales_person_id),
                  monthly_posts=VALUES(monthly_posts), fee=VALUES(fee),
                  fee_sp_pct=VALUES(fee_sp_pct), fee_cm_pct=VALUES(fee_cm_pct),
                  fee_sm_pct=VALUES(fee_sm_pct), payment_date=VALUES(payment_date),
                  posting_days=VALUES(posting_days)
            ");
            $stmt->execute([$id, $name, $color, $cmId, $spId, $posts, $fee, $spPct, $cmPct, $smPct, $payDay, $days]);
            json_ok(['id' => $id]);
            break;

        case 'delete':
            require_admin_api();
            $id = $body['id'] ?? '';
            if (!$id) json_error('Missing id', 422);
            $pdo->prepare('DELETE FROM companies WHERE id = ?')->execute([$id]);
            json_ok(['deleted' => $id]);
            break;

        default:
            json_error('Unknown action: ' . $action, 404);
    }
} catch (PDOException $e) {
    json_error('Database error: ' . $e->getMessage(), 500);
}
