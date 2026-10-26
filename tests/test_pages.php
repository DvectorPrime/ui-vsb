<?php
/**
 * Automated end-to-end page render verification test.
 * Verifies that all guest, customer, and admin pages render HTTP 200 with session guards.
 */

$cookieFile = __DIR__ . '/page_test_cookie.txt';
if (file_exists($cookieFile)) unlink($cookieFile);

function getPage(string $path, bool $useCookie = true): array
{
    global $cookieFile;
    $url = "http://localhost/ui-vsb/" . ltrim($path, '/');
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); // Don't auto follow redirect so we can detect 302
    if ($useCookie) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    }
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $redirect = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    curl_close($ch);
    return ['code' => $code, 'body' => $body, 'redirect' => $redirect];
}

function loginAs(string $email, string $password): int
{
    global $cookieFile;
    $url = "http://localhost/ui-vsb/api/auth/login.php";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['email' => $email, 'password' => $password]));
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code;
}

echo "=== VERIFYING ALL FRONTEND PAGES OVER APACHE HTTP ===\n\n";

// 1. Guest pages
echo "-- 1. Testing Public / Guest Pages --\n";
foreach (['index.php', 'login.php', 'register.php', 'customer/services.php'] as $p) {
    $res = getPage($p, false);
    echo "  {$p}: HTTP {$res['code']}" . ($res['code'] === 200 ? " (OK)" : " (FAILED)") . "\n";
    if ($res['code'] !== 200) exit(1);
}

// 2. Unauthenticated access to protected page (should redirect 302 to login.php)
echo "\n-- 2. Testing Unauthenticated Access Redirect --\n";
$unauth = getPage('customer/dashboard.php', false);
echo "  customer/dashboard.php (guest): HTTP {$unauth['code']}" . ($unauth['code'] === 302 ? " (OK redirect)" : " (FAILED)") . "\n";
if ($unauth['code'] !== 302) exit(1);

// 3. Customer Pages
echo "\n-- 3. Testing Customer Portal Pages --\n";
if (file_exists($cookieFile)) unlink($cookieFile);
$loginCode = loginAs('customer@ui.edu.ng', 'Customer@123');
echo "  Login as customer: HTTP {$loginCode}\n";
if ($loginCode !== 200) exit(1);

$customerPages = [
    'customer/dashboard.php',
    'customer/vehicles.php',
    'customer/services.php',
    'customer/book.php',
    'customer/payment.php?booking_id=1',
    'customer/confirmation.php?ref=VSB-20261010-A101',
    'customer/track.php'
];

foreach ($customerPages as $p) {
    $res = getPage($p, true);
    echo "  {$p}: HTTP {$res['code']}" . ($res['code'] === 200 ? " (OK)" : " (FAILED)") . "\n";
    if ($res['code'] !== 200) exit(1);
}

// 4. Admin Pages
echo "\n-- 4. Testing Admin Portal Pages --\n";
if (file_exists($cookieFile)) unlink($cookieFile);
$adminLoginCode = loginAs('admin@ui.edu.ng', 'Admin@123');
echo "  Login as admin: HTTP {$adminLoginCode}\n";
if ($adminLoginCode !== 200) exit(1);

$adminPages = [
    'admin/dashboard.php',
    'admin/services.php',
    'admin/bookings.php',
    'admin/customers.php',
    'admin/payments.php',
    'admin/records.php'
];

foreach ($adminPages as $p) {
    $res = getPage($p, true);
    echo "  {$p}: HTTP {$res['code']}" . ($res['code'] === 200 ? " (OK)" : " (FAILED)") . "\n";
    if ($res['code'] !== 200) exit(1);
}

if (file_exists($cookieFile)) unlink($cookieFile);

echo "\n============================================\n";
echo " ALL 17 FRONTEND PAGES RENDER HTTP 200 OK\n";
echo "============================================\n";
