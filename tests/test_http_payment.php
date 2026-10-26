<?php
/**
 * End-to-end HTTP integration test for api/payment.php over Apache.
 * Run via: php tests/test_http_payment.php
 */
$cookieFile = __DIR__ . '/test_cookie.txt';
if (file_exists($cookieFile)) {
    unlink($cookieFile);
}

function httpReq(string $path, string $method = 'GET', ?array $data = null, bool $useCookie = true): array
{
    global $cookieFile;
    $url = "http://localhost/ui-vsb/" . ltrim($path, '/');
    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    if ($useCookie) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    }

    if ($data !== null) {
        $json = json_encode($data);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'Content-Length: ' . strlen($json),
        ]);
    } else {
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
        ]);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'code' => $httpCode,
        'body' => json_decode($response, true) ?: $response,
    ];
}

echo "=== HTTP API PAYMENT INTEGRATION TEST ===\n";

// 1. Login as customer
$login = httpReq('api/auth/login.php', 'POST', [
    'email'    => 'customer@ui.edu.ng',
    'password' => 'Customer@123',
]);
echo "1. Login status: {$login['code']}\n";
if ($login['code'] !== 200) {
    die("Login failed: " . json_encode($login['body']) . "\n");
}

// 2. Get customer vehicles
$vList = httpReq('api/vehicles.php', 'GET');
$vehicles = $vList['body']['data'] ?? [];
if (empty($vehicles)) {
    $newV = httpReq('api/vehicles.php', 'POST', [
        'action'     => 'add',
        'reg_number' => 'HTTP-TEST-' . rand(100, 999),
        'make'       => 'Toyota',
        'model'      => 'Corolla',
        'year'       => 2022,
    ]);
    $vehicleId = $newV['body']['data']['vehicle']['vehicle_id'];
} else {
    $vehicleId = $vehicles[0]['vehicle_id'];
}

// 3. Find an available slot and book it
$targetDate = null;
$targetSlot = '11:00';
for ($offset = 25; $offset <= 55; $offset++) {
    $d = date('Y-m-d', strtotime("+{$offset} days"));
    if (date('w', strtotime($d)) === '0') continue;
    $slotsRes = httpReq("api/slots.php?date={$d}&service_id=1", 'GET');
    $slotsList = $slotsRes['body']['data'] ?? [];
    foreach ($slotsList as $s) {
        if (!empty($s['available']) && $s['remaining'] >= 2) {
            $targetDate = $d;
            $targetSlot = $s['time'];
            break 2;
        }
    }
}

$bookRes = httpReq('api/book.php', 'POST', [
    'vehicle_id' => $vehicleId,
    'service_id' => 1,
    'date'       => $targetDate,
    'slot_time'  => $targetSlot,
]);
echo "2. Book status: {$bookRes['code']}\n";
$bookingId = $bookRes['body']['data']['booking_id'] ?? null;
if (!$bookingId) {
    die("Booking failed: " . json_encode($bookRes['body']) . "\n");
}

// 4. Test bad Luhn (should return 400)
$badLuhn = httpReq('api/payment.php', 'POST', [
    'booking_id'  => $bookingId,
    'card_name'   => 'Test Customer',
    'card_number' => '4242 4242 4242 4241',
    'expiry'      => '12/28',
    'cvv'         => '123',
]);
echo "3. Bad Luhn status: {$badLuhn['code']} (Expected: 400)\n";

// 5. Test card decline 0002 (should return 422)
$decline = httpReq('api/payment.php', 'POST', [
    'booking_id'  => $bookingId,
    'card_name'   => 'Test Customer',
    'card_number' => '4000 0000 0000 0002',
    'expiry'      => '12/28',
    'cvv'         => '123',
]);
echo "4. Card decline status: {$decline['code']} (Expected: 422)\n";
echo "   Decline reason: " . ($decline['body']['data']['reason'] ?? 'none') . "\n";

// 6. Test successful payment 4242 (should return 200)
$success = httpReq('api/payment.php', 'POST', [
    'booking_id'  => $bookingId,
    'card_name'   => 'Test Customer',
    'card_number' => '4242 4242 4242 4242',
    'expiry'      => '12/28',
    'cvv'         => '123',
]);
echo "5. Success payment status: {$success['code']} (Expected: 200)\n";
echo "   Transaction ref: " . ($success['body']['data']['transaction_ref'] ?? 'none') . "\n";

// 7. Test paying again (should return 409)
$payAgain = httpReq('api/payment.php', 'POST', [
    'booking_id'  => $bookingId,
    'card_name'   => 'Test Customer',
    'card_number' => '4242 4242 4242 4242',
    'expiry'      => '12/28',
    'cvv'         => '123',
]);
echo "6. Pay again status: {$payAgain['code']} (Expected: 409)\n";

if (file_exists($cookieFile)) {
    unlink($cookieFile);
}

if ($badLuhn['code'] === 400 && $decline['code'] === 422 && $success['code'] === 200 && $payAgain['code'] === 409) {
    echo "\n=== ALL HTTP PAYMENT TESTS PASSED ===\n";
    exit(0);
} else {
    echo "\n=== SOME HTTP PAYMENT TESTS FAILED ===\n";
    exit(1);
}
