<?php
// ============================================================
//  api/audit.php — Audit log (read-only, append-only storage)
// ============================================================

require_once __DIR__ . '/_init.php';

try {
    $stmt = $pdo->prepare('SELECT * FROM audit_events ORDER BY id DESC LIMIT 200');
    $stmt->execute();
    json_ok($stmt->fetchAll());
} catch (PDOException $e) {
    json_error('Database error: ' . $e->getMessage(), 500);
}
