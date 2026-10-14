<?php
/**
 * config/config.php
 * All settings in one place. Anything here can be overridden by
 * config/config.local.php (not committed), so each developer can use
 * their own database password without touching shared code.
 */

// Local overrides are loaded FIRST. Each constant below is only defined
// if the local file has not already defined it.
if (file_exists(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}

function vsb_define(string $name, $value): void
{
    if (!defined($name)) {
        define($name, $value);
    }
}

// ---- Database ----
vsb_define('DB_HOST', 'localhost');
vsb_define('DB_NAME', 'ui_vsb_db');
vsb_define('DB_USER', 'root');
vsb_define('DB_PASS', '');

// ---- App URL (never hard-code the folder name) ----
// Works out "/ui-vsb/" (or "/" on the PHP dev server) from the folder
// this project lives in, compared with the web server's document root.
$vsbRoot = str_replace('\\', '/', realpath(__DIR__ . '/..'));
$vsbDocRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '');
$vsbBase = ($vsbDocRoot !== '' && strpos($vsbRoot, $vsbDocRoot) === 0)
    ? substr($vsbRoot, strlen($vsbDocRoot))
    : '';
vsb_define('BASE_URL', rtrim($vsbBase, '/') . '/');

// ---- Booking rules (see docs/API_CONTRACT.md 2.5) ----
vsb_define('SLOT_CAPACITY', 2);
vsb_define('SLOT_TIMES', ['08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00']);
vsb_define('BOOKING_HOLD_MINUTES', 30);
vsb_define('BOOKING_WINDOW_DAYS', 60);

// ---- Environment ----
vsb_define('APP_ENV', 'dev');
// DEV ONLY: lets the frontend view protected pages with ?as=customer|admin.
// MUST stay false for the final submission.
vsb_define('DEV_BYPASS_AUTH', false);
