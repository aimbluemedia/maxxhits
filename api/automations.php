<?php
// ============================================================
//  api/automations.php — Recurring automation workflows
//  GET  ?platform=X        → configurations + run history
//  POST action=save        → create/update
//  POST action=toggle      → enable/pause
//  POST action=delete      → delete
//  POST action=test        → run now (creates drafts, never publishes)
// ============================================================

require_once __DIR__ . '/_init.php';

$action = $_GET['action'] ?? $body['action'] ?? 'list';

try {
    switch ($action) {

        case 'list':
            $platform = $_GET['platform'] ?? '';
            if (!isset(mh_platforms()[$platform])) json_error('Invalid platform.', 422);
            [$bw, $bp] = brand_where('a.brand_id');

            $stmt = $pdo->prepare("
                SELECT a.*, b.name AS brand_name, b.color AS brand_color,
                       d.name AS destination_name
                FROM automations a
                JOIN brands b ON b.id = a.brand_id
                LEFT JOIN destinations d ON d.id = a.destination_id
                WHERE a.platform = ? $bw
                ORDER BY b.name, a.name
            ");
            $stmt->execute(array_merge([$platform], $bp));
            $autos = $stmt->fetchAll();
            foreach ($autos as &$a) $a['enabled'] = (bool)$a['enabled'];

            $runs = [];
            if ($autos) {
                $ids = array_column($autos, 'id');
                $in  = implode(',', array_fill(0, count($ids), '?'));
                $rs = $pdo->prepare("
                    SELECT r.*, a.name AS automation_name
                    FROM automation_runs r
                    JOIN automations a ON a.id = r.automation_id
                    WHERE r.automation_id IN ($in)
                    ORDER BY r.id DESC LIMIT 50
                ");
                $rs->execute($ids);
                $runs = $rs->fetchAll();
            }
            json_ok(['automations' => $autos, 'runs' => $runs]);
            break;

        case 'save':
            $id       = $body['id'] ?: uid();
            $brandId  = $body['brand_id'] ?? '';
            $platform = $body['platform'] ?? '';
            $name     = trim($body['name'] ?? '');
            $destId   = $body['destination_id'] ?: null;
            $topic    = trim($body['topic_prompt'] ?? '');
            $freq     = $body['frequency'] ?? 'daily';
            $time     = trim($body['run_time'] ?? '06:00');
            $max      = (int)($body['max_drafts'] ?? 1);

            if (!$brandId || !$name || !$topic) json_error('Brand, name and topic instructions are required.', 422);
            if (!isset(mh_platforms()[$platform])) json_error('Invalid platform.', 422);
            if (!in_array($freq, ['daily', 'weekdays', 'weekly', 'monthly'], true)) json_error('Invalid frequency.', 422);
            if (!valid_time($time)) json_error('Invalid run time (expected HH:MM).', 422);
            if ($max < 1 || $max > 5) json_error('Max drafts must be 1–5.', 422);

            if ($destId) {
                $chk = $pdo->prepare('SELECT brand_id, platform FROM destinations WHERE id = ? AND is_active = 1');
                $chk->execute([$destId]);
                $d = $chk->fetch();
                if (!$d) json_error('Selected destination not found or inactive.', 422);
                if ($d['brand_id'] !== $brandId) json_error('Destination belongs to a different brand.', 422);
                if ($d['platform'] !== $platform) json_error('Destination platform does not match.', 422);
            }

            // Saving always disables until explicitly re-enabled
            // (config changes need a fresh look before they run).
            $pdo->prepare('
                INSERT INTO automations
                  (id, brand_id, platform, name, destination_id, topic_prompt, frequency, run_time, max_drafts, enabled)
                VALUES (?,?,?,?,?,?,?,?,?,0)
                ON DUPLICATE KEY UPDATE
                  brand_id=VALUES(brand_id), name=VALUES(name), destination_id=VALUES(destination_id),
                  topic_prompt=VALUES(topic_prompt), frequency=VALUES(frequency),
                  run_time=VALUES(run_time), max_drafts=VALUES(max_drafts), enabled=0
            ')->execute([$id, $brandId, $platform, $name, $destId, $topic, $freq, $time, $max]);
            audit('automation_saved', 'automation', $id, $name . ' (' . $platform . ') — saved as paused');
            json_ok(['id' => $id]);
            break;

        case 'toggle':
            $id = $body['id'] ?? '';
            if (!$id) json_error('Missing id', 422);
            $stmt = $pdo->prepare('SELECT enabled, name FROM automations WHERE id = ?');
            $stmt->execute([$id]);
            $a = $stmt->fetch();
            if (!$a) json_error('Automation not found', 404);
            $new = $a['enabled'] ? 0 : 1;
            if ($new && emergency_stopped()) json_error('Emergency stop is active — resume operations first.', 422);
            $pdo->prepare('UPDATE automations SET enabled = ? WHERE id = ?')->execute([$new, $id]);
            audit($new ? 'automation_enabled' : 'automation_paused', 'automation', $id, $a['name']);
            json_ok(['id' => $id, 'enabled' => (bool)$new]);
            break;

        case 'delete':
            require_admin_api();
            $id = $body['id'] ?? '';
            if (!$id) json_error('Missing id', 422);
            $pdo->prepare('DELETE FROM automations WHERE id = ?')->execute([$id]);
            audit('automation_deleted', 'automation', $id);
            json_ok(['deleted' => $id]);
            break;

        case 'test':
            $id = $body['id'] ?? '';
            if (!$id) json_error('Missing id', 422);
            if (emergency_stopped()) json_error('Emergency stop is active.', 422);
            $stmt = $pdo->prepare('SELECT * FROM automations WHERE id = ?');
            $stmt->execute([$id]);
            $auto = $stmt->fetch();
            if (!$auto) json_error('Automation not found', 404);

            require_once APP_ROOT . '/includes/runner.php';
            // Unique key per test run; never publishes anything.
            $r = run_automation($pdo, $auto, 'test-' . uid(), 'test');
            if ($r['ok']) json_ok(['drafts' => $r['drafts'], 'message' => $r['message']]);
            json_error($r['error'] ?? 'Test run failed.', 422);
            break;

        default:
            json_error('Unknown action: ' . $action, 404);
    }
} catch (PDOException $e) {
    json_error('Database error: ' . $e->getMessage(), 500);
}
