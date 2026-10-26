<?php
/**
 * Automated test suite for Phase B4: Simulated Payment
 * Run via: php tests/test_b4.php
 */
define('DISABLE_GATEWAY_LATENCY', true);
require_once __DIR__ . '/../includes/bootstrap.php';

$pdo = Database::getInstance()->getConnection();
$passCount = 0;
$failCount = 0;

function assertCondition(bool $cond, string $msg): void
{
    global $passCount, $failCount;
    if ($cond) {
        $passCount++;
        echo "PASS: {$msg}\n";
    } else {
        $failCount++;
        echo "FAIL: {$msg}\n";
    }
}

echo "=== STARTING PHASE B4 (SIMULATED PAYMENT) TESTS ===\n\n";

// Setup test users
$userModel = new User();
$cust1 = $userModel->findByEmail('customer@ui.edu.ng');
if (!$cust1) {
    die("Seed customer customer@ui.edu.ng missing. Re-import database/ui_vsb.sql first.\n");
}
$cust1Id = (int) $cust1['user_id'];

// Create a second customer for ownership tests
$cust2 = $userModel->findByEmail('cust2_b4@ui.edu.ng');
if (!$cust2) {
    $res = $userModel->register([
        'full_name'        => 'Second Customer B4',
        'email'            => 'cust2_b4@ui.edu.ng',
        'phone'            => '08099887766',
        'password'         => 'Cust2Pass@123',
        'confirm_password' => 'Cust2Pass@123',
    ]);
    $cust2Id = (int) ($res['user']['user_id'] ?? $res['data']['user']['user_id'] ?? 0);
} else {
    $cust2Id = (int) $cust2['user_id'];
}

// Get or add vehicle for cust1
$vehModel = new Vehicle();
$vehList = $vehModel->getByUser($cust1Id);
if (empty($vehList)) {
    $vRes = $vehModel->add($cust1Id, [
        'reg_number' => 'B4-TEST-' . rand(100, 999),
        'make'       => 'Toyota',
        'model'      => 'Camry',
        'year'       => 2021,
    ]);
    $veh1Id = (int) $vRes['data']['vehicle']['vehicle_id'];
} else {
    $veh1Id = (int) $vehList[0]['vehicle_id'];
}

// Get service
$srvModel = new Service();
$services = $srvModel->getAllActive();
$srv1Id = (int) $services[0]['service_id'];

$bookingModel = new Booking();
$paymentModel = new Payment();

// Find a future weekday within window that has available capacity
$targetDate = null;
$targetSlot = '09:00';
for ($dayOffset = 20; $dayOffset <= 55; $dayOffset++) {
    $d = date('Y-m-d', strtotime("+{$dayOffset} days"));
    if (date('w', strtotime($d)) === '0') {
        continue; // skip Sunday
    }
    $slots = $bookingModel->getAvailableSlots($d)['data'] ?? [];
    foreach ($slots as $s) {
        if ($s['available'] && $s['remaining'] >= 2) {
            $targetDate = $d;
            $targetSlot = $s['time'];
            break 2;
        }
    }
}
if (!$targetDate) {
    die("No available future weekday slot found.\n");
}

// 1. Create a fresh booking for Cust 1
$bRes1 = $bookingModel->create($cust1Id, [
    'vehicle_id' => $veh1Id,
    'service_id' => $srv1Id,
    'date'       => $targetDate,
    'slot_time'  => $targetSlot,
]);
assertCondition($bRes1['ok'] && $bRes1['code'] === 201, 'Created booking for payment test');
$bookingId1 = (int) $bRes1['data']['booking_id'];
$bookingRef1 = (string) $bRes1['data']['booking_ref'];

// --- TEST: Decline with 4000 0000 0000 0002 ---
echo "\n-- Testing Card Decline (0002) --\n";
// Set session as Cust 1
Auth::login($cust1);

// Count payments before
$countBefore = (int) $pdo->query('SELECT COUNT(*) FROM payments')->fetchColumn();

// Call payment simulation logic directly or via SimulatedPaymentGateway
$gateway = new SimulatedPaymentGateway(false);
$resDecline = $gateway->charge([
    'card_name'   => 'Customer One',
    'card_number' => '4000 0000 0000 0002',
    'expiry'      => '12/28',
    'cvv'         => '123',
], 15000.0);

assertCondition($resDecline['success'] === false, 'Gateway returned success=false for 0002 card');
assertCondition($resDecline['reason'] === 'card_declined', 'Gateway returned reason=card_declined');

// Record declined payment attempt
$failedPayId = $paymentModel->record($bookingId1, $resDecline['transaction_ref'], 15000.0, 'failed', $resDecline['reason'], '0002');
assertCondition($failedPayId > 0, 'Failed payment row recorded in payments table');

// Verify booking is still pending_payment
$rawB1 = $bookingModel->findById($bookingId1);
assertCondition($rawB1['booking_status'] === 'pending_payment', 'Booking status remains pending_payment after decline');

// --- TEST: Insufficient Funds with 4000 0000 0000 9995 ---
echo "\n-- Testing Insufficient Funds (9995) --\n";
$resFunds = $gateway->charge([
    'card_name'   => 'Customer One',
    'card_number' => '4000 0000 0000 9995',
    'expiry'      => '11/27',
    'cvv'         => '456',
], 15000.0);
assertCondition($resFunds['success'] === false, 'Gateway returned success=false for 9995 card');
assertCondition($resFunds['reason'] === 'insufficient_funds', 'Gateway returned reason=insufficient_funds');

// --- TEST: Expired Card ---
echo "\n-- Testing Expired Card (past MM/YY) --\n";
$resExp = $gateway->charge([
    'card_name'   => 'Customer One',
    'card_number' => '4242 4242 4242 4242',
    'expiry'      => '05/20', // past date
    'cvv'         => '123',
], 15000.0);
assertCondition($resExp['success'] === false, 'Gateway rejected expired card');
assertCondition($resExp['reason'] === 'expired_card', 'Gateway returned reason=expired_card');

// --- TEST: Invalid Luhn Validation (Bad card number) ---
echo "\n-- Testing Bad Luhn Validation --\n";
$validLuhn = SimulatedPaymentGateway::isValidLuhn('4242424242424242');
$invalidLuhn = SimulatedPaymentGateway::isValidLuhn('4242424242424241');
assertCondition($validLuhn === true, 'Valid card passes Luhn check');
assertCondition($invalidLuhn === false, 'Tampered card fails Luhn check');

// --- TEST: Successful Payment (4242 4242 4242 4242) ---
echo "\n-- Testing Successful Payment (4242) --\n";
$resSuccess = $gateway->charge([
    'card_name'   => 'Customer One',
    'card_number' => '4242 4242 4242 4242',
    'expiry'      => '12/28',
    'cvv'         => '123',
], 15000.0);
assertCondition($resSuccess['success'] === true, 'Gateway accepted 4242 card');
assertCondition($resSuccess['reason'] === null, 'Gateway returned null reason on success');
assertCondition(str_starts_with($resSuccess['transaction_ref'], 'TXN-'), 'Transaction reference matches TXN- prefix');

// Record successful payment and advance booking status
$successPayId = $paymentModel->record($bookingId1, $resSuccess['transaction_ref'], 15000.0, 'success', null, '4242');
assertCondition($successPayId > 0, 'Successful payment row recorded in payments table');

$pdo->prepare("UPDATE bookings SET booking_status = 'pending_approval' WHERE booking_id = :bid")->execute([':bid' => $bookingId1]);
$bookingModel->addHistory($bookingId1, 'pending_approval', 'Payment received', $cust1Id);

$rawB1Updated = $bookingModel->findById($bookingId1);
assertCondition($rawB1Updated['booking_status'] === 'pending_approval', 'Booking advanced to pending_approval');

// --- TEST: Booking Detail Reflects Payment ---
echo "\n-- Testing Booking Detail Reflection --\n";
$detailCust = $bookingModel->getByRef($bookingRef1, $cust1Id);
assertCondition($detailCust !== null, 'Found booking detail for customer');
assertCondition(isset($detailCust['payment']) && $detailCust['payment'] !== null, 'Booking detail includes payment object');
assertCondition($detailCust['payment']['card_last4'] === '4242', 'Payment card_last4 is 4242');
assertCondition($detailCust['payment']['status'] === 'success', 'Payment status is success');

// --- TEST: Security Audit — No Sensitive Card Data Stored ---
echo "\n-- Testing Security: No Raw Card or CVV in Database --\n";
$payRow = $pdo->query("SELECT * FROM payments WHERE payment_id = {$successPayId}")->fetch();
assertCondition(!isset($payRow['card_number']), 'No card_number column exists in payments');
assertCondition(!isset($payRow['cvv']), 'No cvv column exists in payments');
assertCondition(strlen($payRow['card_last4']) === 4, 'Only card_last4 is stored in database');

// --- TEST: Expired Stale Booking Cannot Be Paid ---
echo "\n-- Testing Stale Booking Hold Expiry --\n";
// Create another booking and backdate it 35 minutes
$staleSlot = ($targetSlot === '15:00') ? '14:00' : '15:00';
$bResStale = $bookingModel->create($cust1Id, [
    'vehicle_id' => $veh1Id,
    'service_id' => $srv1Id,
    'date'       => $targetDate,
    'slot_time'  => $staleSlot,
]);
$staleBookingId = (int) $bResStale['data']['booking_id'];
$pdo->query("UPDATE bookings SET created_at = (NOW() - INTERVAL 35 MINUTE) WHERE booking_id = {$staleBookingId}");
$bookingModel->expireStale();

$staleBookingRaw = $bookingModel->findById($staleBookingId);
assertCondition($staleBookingRaw['booking_status'] === 'cancelled', 'Stale booking was cancelled by expireStale()');

echo "\n============================================\n";
echo "B4 SUMMARY: Passed: {$passCount} | Failed: {$failCount}\n";
echo "============================================\n";

if ($failCount > 0) {
    exit(1);
}
