<?php
// ============================================================
//  content-managers.php — Content managers page
//  Shares includes/people-page.php with sales-people.php.
// ============================================================

require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$PEOPLE_ROLE  = 'content_manager';
$PEOPLE_TITLE = 'Content Managers';
$PAGE = [
    'id'    => 'content-managers',
    'title' => $PEOPLE_TITLE,
    'js'    => ['people.js'],
];

include __DIR__ . '/includes/people-page.php';
