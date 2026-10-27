<?php
/**
 * tests/test_b2_b3.php
 * Automated unit and integration test for Phase B2 (Vehicles, Services)
 * and Phase B3 (Booking Engine, Slots, Concurrency, Expiry).
 */
require_once __DIR__ . '/../includes/bootstrap.php';

$pass = 0;
$fail = 0;

function assertTest(string $name, bool $condition, string $details = ''): void {
    global $pass, $fail;
    if ($condition) {
        echo "PASS: {$name}\n";
        $pass++;
    } else {
        echo "FAIL: {$name}" . ($details ? " ({$details})" : "") . "\n";
        $fail++;
    }
}

try {
    $pdo = Database::getInstance()->getConnection();
} catch (Throwable $e) {
    echo "DATABASE CONNECTION FAILED: " . $e->getMessage() . "\n";
    echo "Make sure MySQL is running in XAMPP and ui_vsb.sql has been imported.\n";
    exit(1);
}

echo "=== STARTING PHASE B2 & B3 AUTOMATED TESTS ===\n\n";

// Get seed customer (id 2) and admin (id 1)
$customerStmt = $pdo->query("SELECT user_id FROM users WHERE email = 'customer@ui.edu.ng' LIMIT 1");
$customerId = (int) ($customerStmt ? $customerStmt->fetchColumn() : 0);
$tempCustomerCreated = false;
if ($customerId === 0) {
    $hash = password_hash('Customer@123', PASSWORD_DEFAULT);
    $pdo->prepare("INSERT INTO users (full_name, email, phone, password_hash, role) VALUES ('Test Customer', 'test_b2_b3@ui.edu.ng', '08012345678', ?, 'customer')")->execute([$hash]);
    $customerId = (int) $pdo->lastInsertId();
    $tempCustomerCreated = true;
}

$adminStmt = $pdo->query("SELECT user_id FROM users WHERE email = 'admin@ui.edu.ng' LIMIT 1");
$adminId = (int) $adminStmt->fetchColumn();

// ----------------------------------------------------
// TEST GROUP 1: Vehicle Model
// ----------------------------------------------------
echo "-- Testing Vehicle Model --\n";
$vehicleModel = new Vehicle();

$uniqueSuffix = substr((string) time(), -4);
$testPlate = "TEST-{$uniqueSuffix}";

// 1. Add valid vehicle
$addRes = $vehicleModel->add($customerId, [
    'reg_number' => strtolower($testPlate), // test uppercase conversion
    'make'       => 'Toyota',
    'model'      => 'Camry',
    'year'       => 2021,
]);
assertTest('Vehicle added successfully', $addRes['ok'] === true && $addRes['code'] === 201);
assertTest('Plate converted to uppercase', ($addRes['vehicle']['reg_number'] ?? '') === $testPlate);
$testVehicleId = $addRes['vehicle']['vehicle_id'] ?? 0;

// 2. Duplicate plate returns 409
$dupRes = $vehicleModel->add($customerId, [
    'reg_number' => $testPlate,
    'make'       => 'Nissan',
    'model'      => 'Altima',
    'year'       => 2020,
]);
assertTest('Duplicate plate returns 409', $dupRes['ok'] === false && $dupRes['code'] === 409);

// 3. Invalid year returns 400
$badYearRes = $vehicleModel->add($customerId, [
    'reg_number' => "BAD-{$uniqueSuffix}",
    'make'       => 'Toyota',
    'model'      => 'Yaris',
    'year'       => 1970, // below 1980
]);
assertTest('Year < 1980 returns 400', $badYearRes['ok'] === false && $badYearRes['code'] === 400);

// 4. belongsTo verification
assertTest('Vehicle belongs to customer', $vehicleModel->belongsTo($testVehicleId, $customerId) === true);
assertTest('Vehicle does not belong to another user', $vehicleModel->belongsTo($testVehicleId, 99999) === false);

// 5. getByUser
$myVehicles = $vehicleModel->getByUser($customerId);
$found = false;
foreach ($myVehicles as $v) {
    if ($v['vehicle_id'] === $testVehicleId) {
        $found = true;
        break;
    }
}
assertTest('getByUser contains added vehicle', $found);

// ----------------------------------------------------
// TEST GROUP 2: Service Model
// ----------------------------------------------------
echo "\n-- Testing Service Model --\n";
$serviceModel = new Service();

// 1. getAllActive returns active services with cast types
$activeServices = $serviceModel->getAllActive();
assertTest('getAllActive returns items', count($activeServices) >= 5);
$firstService = $activeServices[0];
assertTest('Service price is float', is_float($firstService['price']));
assertTest('Service is_active is boolean true', $firstService['is_active'] === true);

// 2. Create service
$svcName = "Test Diagnostic {$uniqueSuffix}";
$createSvcRes = $serviceModel->create([
    'name'          => $svcName,
    'description'   => 'Comprehensive electrical test',
    'price'         => 18500.00,
    'duration_mins' => 75,
]);
assertTest('Service created successfully', $createSvcRes['ok'] === true && $createSvcRes['code'] === 201);
$testServiceId = $createSvcRes['service']['service_id'] ?? 0;

// 3. Update service
$updateSvcRes = $serviceModel->update($testServiceId, [
    'name'          => $svcName . ' Updated',
    'description'   => 'Updated description',
    'price'         => 20000.00,
    'duration_mins' => 90,
    'is_active'     => true,
]);
assertTest('Service updated successfully', $updateSvcRes['ok'] === true && $updateSvcRes['code'] === 200);
assertTest('Updated price matches', ($updateSvcRes['service']['price'] ?? 0) === 20000.0);

// ----------------------------------------------------
// TEST GROUP 3: Booking Engine & Slots
// ----------------------------------------------------
echo "\n-- Testing Booking Engine & Slots --\n";
$bookingModel = new Booking();

// Find a future Sunday and a future weekday
$sundayDate = null;
$weekdayDate = null;
for ($i = 1; $i <= 14; $i++) {
    $d = date('Y-m-d', strtotime("+{$i} days"));
    $w = date('w', strtotime($d));
    if ($w === '0' && $sundayDate === null) {
        $sundayDate = $d;
    } elseif ($w !== '0' && $weekdayDate === null) {
        $weekdayDate = $d;
    }
}

// 1. Sunday check
$sundaySlots = $bookingModel->getAvailableSlots($sundayDate);
assertTest('Sunday returns closed message', ($sundaySlots['closed'] ?? false) === true);
assertTest('Sunday returns empty slot list', empty($sundaySlots['data']));

// 2. Weekday slots
$weekdaySlots = $bookingModel->getAvailableSlots($weekdayDate);
assertTest('Weekday returns 8 slots', count($weekdaySlots['data'] ?? []) === 8);
assertTest('Weekday slots available with capacity 2', ($weekdaySlots['data'][0]['remaining'] ?? 0) === 2);

// 3. Past date rejected
$pastSlots = $bookingModel->getAvailableSlots('2020-01-01');
assertTest('Past date returns 400', $pastSlots['ok'] === false && $pastSlots['code'] === 400);

// 4. Booking creation: unauthorized vehicle rejected with 403
$unauthBooking = $bookingModel->create($adminId, [
    'vehicle_id' => $testVehicleId, // belongs to customer, not admin
    'service_id' => $testServiceId,
    'date'       => $weekdayDate,
    'slot_time'  => '10:00',
]);
assertTest('Booking vehicle of another user returns 403', $unauthBooking['code'] === 403);

// 5. Booking 1: Success
$book1 = $bookingModel->create($customerId, [
    'vehicle_id' => $testVehicleId,
    'service_id' => $testServiceId,
    'date'       => $weekdayDate,
    'slot_time'  => '10:00',
    'notes'      => 'First booking slot test',
]);
assertTest('Booking 1 created successfully (201)', $book1['ok'] === true && $book1['code'] === 201);
$ref1 = $book1['data']['booking_ref'] ?? '';
assertTest('Booking ref matches format VSB-YYYYMMDD-XXXX', (bool) preg_match('/^VSB-\d{8}-[A-Z0-9]{4}$/', $ref1));
$bookingId1 = $book1['data']['booking_id'] ?? 0;

// Verify remaining slots count reduced to 1
$slotsAfterBook1 = $bookingModel->getAvailableSlots($weekdayDate);
$tenSlot = null;
foreach ($slotsAfterBook1['data'] as $s) {
    if ($s['time'] === '10:00') {
        $tenSlot = $s;
        break;
    }
}
assertTest('10:00 slot remaining capacity is now 1', ($tenSlot['remaining'] ?? null) === 1);

// 6. Booking 2: Fills the slot to capacity (2)
$book2 = $bookingModel->create($customerId, [
    'vehicle_id' => $testVehicleId,
    'service_id' => $testServiceId,
    'date'       => $weekdayDate,
    'slot_time'  => '10:00',
]);
assertTest('Booking 2 created successfully (slot now full)', $book2['ok'] === true && $book2['code'] === 201);

// 7. Booking 3: Exceeds capacity -> 409 conflict
$book3 = $bookingModel->create($customerId, [
    'vehicle_id' => $testVehicleId,
    'service_id' => $testServiceId,
    'date'       => $weekdayDate,
    'slot_time'  => '10:00',
]);
assertTest('Booking 3 returns 409 slot full', $book3['ok'] === false && $book3['code'] === 409);

// 8. Vehicle with bookings cannot be deleted (409)
$delVehRes = $vehicleModel->delete($testVehicleId, $customerId);
assertTest('Deleting vehicle with bookings returns 409', $delVehRes['ok'] === false && $delVehRes['code'] === 409);

// 9. Service with bookings is deactivated rather than deleted
$delSvcRes = $serviceModel->delete($testServiceId);
assertTest('Service with bookings is deactivated (200)', $delSvcRes['ok'] === true && $delSvcRes['deactivated'] === true);
$checkSvc = $serviceModel->getById($testServiceId);
assertTest('Service is_active is now false', $checkSvc['is_active'] === false);

// 10. getByUser lists the customer bookings
$customerBookings = $bookingModel->getByUser($customerId);
assertTest('Customer bookings contains booking 1', count($customerBookings) >= 2);

// 11. getByRef returns detail with history
$detail = $bookingModel->getByRef($ref1, $customerId);
assertTest('getByRef returns booking', $detail !== null && $detail['booking_ref'] === $ref1);
assertTest('Booking detail has history array', !empty($detail['history']));
assertTest('History contains "Booking created"', $detail['history'][0]['note'] === 'Booking created');

// 12. Stale hold expiry test: backdate booking 2 by 35 minutes and run expireStale
$pdo->exec("UPDATE bookings SET created_at = NOW() - INTERVAL 35 MINUTE WHERE booking_id = " . (int)$book2['data']['booking_id']);
$bookingModel->expireStale();
$expiredBooking = $bookingModel->getByRef($book2['data']['booking_ref'], $customerId);
assertTest('Stale booking expired to cancelled', $expiredBooking['booking_status'] === 'cancelled');

// Verify that the expired booking freed up a slot!
$slotsAfterExpiry = $bookingModel->getAvailableSlots($weekdayDate);
foreach ($slotsAfterExpiry['data'] as $s) {
    if ($s['time'] === '10:00') {
        assertTest('Slot 10:00 freed up capacity after expiry (remaining: 1)', $s['remaining'] === 1);
        break;
    }
}

// Clean up test bookings and vehicle
$pdo->exec("DELETE FROM status_history WHERE booking_id IN ({$bookingId1}, " . (int)$book2['data']['booking_id'] . ")");
$pdo->exec("DELETE FROM bookings WHERE booking_id IN ({$bookingId1}, " . (int)$book2['data']['booking_id'] . ")");
$pdo->exec("DELETE FROM vehicles WHERE vehicle_id = {$testVehicleId}");
$pdo->exec("DELETE FROM services WHERE service_id = {$testServiceId}");
if ($tempCustomerCreated) {
    $pdo->prepare("DELETE FROM users WHERE user_id = ?")->execute([$customerId]);
}

echo "\n============================================\n";
echo "SUMMARY: Passed: {$pass} | Failed: {$fail}\n";
echo "============================================\n";

if ($fail > 0) {
    exit(1);
}
