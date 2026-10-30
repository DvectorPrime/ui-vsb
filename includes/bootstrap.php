<?php
/**
 * includes/bootstrap.php
 * Included at the top of every page and API file. It prepares the
 * environment: config, timezone, autoloading of classes, safe sessions
 * and error handling.
 */
require_once __DIR__ . '/../config/config.php';

date_default_timezone_set('Africa/Lagos');

// Load classes from src/ automatically: new User() loads src/User.php.
spl_autoload_register(function (string $class): void {
    $file = __DIR__ . '/../src/' . $class . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

// API calls must never print PHP errors into the JSON; log them instead.
$vsbIsApi = strpos($_SERVER['SCRIPT_NAME'] ?? '', '/api/') !== false;
ini_set('display_errors', $vsbIsApi ? '0' : '1');
ini_set('log_errors', '1');

// Dedicated session name & secure cookie parameters:
// httponly stops JavaScript reading the cookie, samesite=Lax blocks cross-site POSTs.
if (session_status() === PHP_SESSION_NONE) {
    session_name('UI_VSB_SESSION');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
