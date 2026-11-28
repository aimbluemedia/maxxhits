<?php
// ============================================================
//  maxxhits.db.config.sample.php — Configuration template
//
//  Copy this file, fill in the values, and save it as
//  "maxxhits.db.config.php" ONE DIRECTORY ABOVE the webroot
//  (e.g. /home/uXXXXXXXXX/maxxhits.db.config.php on Hostinger,
//  next to — not inside — public_html).
//
//  Easier alternative: open config.php in the browser — the
//  wizard writes this file for you and generates APP_KEY.
//
//  NEVER commit the real file to git (it is gitignored).
// ============================================================

define('DB_HOST', 'localhost');                 // Hostinger MySQL host (usually localhost)
define('DB_NAME', 'uXXXXXXXXX_maxxhits');       // Database name from hPanel → Databases
define('DB_USER', 'uXXXXXXXXX_youruser');       // Database user
define('DB_PASS', 'your-database-password');    // Database password

// Encryption key for stored credentials (AI keys, destination
// tokens). Any long random string; KEEP IT — changing it makes
// previously saved secrets unreadable.
define('APP_KEY', 'replace-with-64-random-hex-characters');
