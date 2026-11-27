<?php
// ============================================================
//  index.php — Entry point (routes to the Overview)
// ============================================================
require_once __DIR__ . '/includes/bootstrap.php';
header('Location: ' . (is_logged_in() ? 'dashboard.php' : 'login.php'));
exit;
