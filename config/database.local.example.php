<?php
/**
 * PRODUCTION OVERRIDES — copy this file to `database.local.php` on the
 * server (NOT in the git repo) and fill in real values.
 *
 * Loaded automatically by config/database.php if present.
 *
 * Only define the constants you actually want to override. Anything you
 * leave out falls back to the defaults in database.php.
 */

declare(strict_types=1);

// ---- MySQL credentials (required on shared hosting) ----
define('DB_HOST', 'sqlNNN.your-host.example.com');
define('DB_PORT', '3306');
define('DB_NAME', 'if0_XXXXXXX_finance');
define('DB_USER', 'if0_XXXXXXX');
define('DB_PASS', 'replace-with-real-password');

// ---- App settings ----
// Empty string when the app lives at the domain root (e.g. https://example.com/).
// Use a subfolder path when deployed under e.g. https://example.com/fintrack/.
define('APP_BASE_URL', '');

// Optional: change the timezone on production
// define('APP_TIMEZONE', 'Asia/Kuala_Lumpur');
