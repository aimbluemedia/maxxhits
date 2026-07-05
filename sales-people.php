<?php
// ============================================================
//  sales-people.php — Sales people page
//  Shares includes/people-page.php with content-managers.php.
// ============================================================

require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$PEOPLE_ROLE  = 'sales_person';
$PEOPLE_TITLE = 'Sales People';
$PAGE = [
    'id'    => 'sales-people',
    'title' => $PEOPLE_TITLE,
    'js'    => ['people.js'],
];

include __DIR__ . '/includes/people-page.php';
