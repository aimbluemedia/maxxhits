<?php
// ============================================================
//  index.php — Entry point
//  MaxxHits is a multi-page app: every navigation item is its
//  own page. This file only routes to the right one.
// ============================================================

require_once __DIR__ . '/includes/bootstrap.php';

header('Location: ' . (is_logged_in() ? 'dashboard.php' : 'login.php'));
exit;
