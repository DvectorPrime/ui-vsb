<?php
/**
 * tests/test_b5.php
 * Automated unit and integration tests for Phase B5 (Admin API & Dashboards).
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
    exit(1);
}

echo "=== STARTING PHASE B5 AUTOMATED TESTS ===\n\n";

$adminStmt = $pdo->query("SELECT user_id FROM users WHERE role = 'admin' LIMIT 1");
$adminId = (int) $adminStmt->fetchColumn();

$customerStmt = $pdo->query("SELECT user_id FROM users WHERE email = 'customer@ui.edu.ng' LIMIT 1");
$customerId = (int) $customerStmt->fetchColumn();

$vehStmt = $pdo->query("SELECT vehicle_id FROM vehicles WHERE user_id = {$customerId} LIMIT 1");
$vehicleId = (int) $vehStmt->fetchColumn();

$svcStmt = $pdo->query("SELECT service_id, price FROM services WHERE is_active = 1 LIMIT 1");
$svcRow = $svcStmt->fetch();
$serviceId = (int) $svcRow['service_id'];
$servicePrice = (float) $svcRow['price'];

$bookingModel = new Booking();
$userModel    = new User();
$paymentModel = new Payment();

// -------------------------------------------------------------------------
// TEST GROUP 1: Workflow Approvals & Payment Refund on Reject
// -------------------------------------------------------------------------
echo "-- Testing Admin Workflow (Approve / Reject / Refund) --\n";

// Helper: insert test booking directly in a specific status
function createTestBooking(PDO $pdo, int $uid, int $vid, int $sid, float $amt, string $bStatus, string $sStatus): int {
    $ref = 'VSB-TEST-' . strtoupper(substr(uniqid(), -6));
    $stmt = $pdo->prepare("INSERT INTO bookings (booking_ref, user_id, vehicle_id, service_id, booking_date, slot_time, amount, booking_status, service_status)
                           VALUES (:ref, :uid, :vid, :sid, CURRENT_DATE(), '10:00', :amt, :bstatus, :sstatus)");
    $stmt->execute([
        ':ref'     => $ref,
        ':uid'     => $uid,
        ':vid'     => $vid,
        ':sid'     => $sid,
        ':amt'     => $amt,
        ':bstatus' => $bStatus,
        ':sstatus' => $sStatus,
    ]);
    return (int) $pdo->lastInsertId();
}

// 1. Approval of pending_approval booking
$bidApprove = createTestBooking($pdo, $customerId, $vehicleId, $serviceId, $servicePrice, 'pending_approval', 'not_started');
$appRes = $bookingModel->approve($bidApprove, $adminId, 'Test approval note');
assertTest('Approve pending booking succeeds (200)', $appRes['ok'] === true && $appRes['code'] === 200);
$checkApp = $bookingModel->getByRefAdmin($appRes['booking']['booking_ref']);
assertTest('Booking status updated to approved', $checkApp['booking_status'] === 'approved');

// 2. Approving an already approved booking fails (409)
$dupAppRes = $bookingModel->approve($bidApprove, $adminId);
assertTest('Approving non-pending booking returns 409', $dupAppRes['ok'] === false && $dupAppRes['code'] === 409);

// 3. Rejection of pending_approval booking with payment refund
$bidReject = createTestBooking($pdo, $customerId, $vehicleId, $serviceId, $servicePrice, 'pending_approval', 'not_started');
// Insert an attached successful payment
$txnRef = 'TXN-TEST-' . strtoupper(substr(uniqid(), -6));
$pdo->prepare("INSERT INTO payments (booking_id, transaction_ref, amount, method, card_last4, status)
               VALUES (:bid, :tx, :amt, 'test_card', '4242', 'success')")
    ->execute([':bid' => $bidReject, ':tx' => $txnRef, ':amt' => $servicePrice]);

$rejRes = $bookingModel->reject($bidReject, $adminId, 'Test rejection reason');
assertTest('Reject pending booking succeeds (200)', $rejRes['ok'] === true && $rejRes['code'] === 200);
$checkRej = $bookingModel->getByRefAdmin($rejRes['booking']['booking_ref']);
assertTest('Booking status updated to rejected', $checkRej['booking_status'] === 'rejected');

// Verify payment status changed to refunded
$checkPay = $paymentModel->getByBooking($bidReject);
assertTest('Associated payment is refunded on reject', ($checkPay['status'] ?? '') === 'refunded');

// -------------------------------------------------------------------------
// TEST GROUP 2: Service Status Stepper (Strict Forward Only)
// -------------------------------------------------------------------------
echo "\n-- Testing Service Status Transitions --\n";

// 1. Cannot update service status if booking is not approved (e.g. on rejected booking)
$badStatusRes = $bookingModel->updateServiceStatus($bidReject, 'received', 'Try on rejected', $adminId);
assertTest('Updating service status on unapproved booking returns 409', $badStatusRes['ok'] === false && $badStatusRes['code'] === 409);

// 2. Forward progression on approved booking: not_started -> received
$step1 = $bookingModel->updateServiceStatus($bidApprove, 'received', 'Vehicle arrived at bay', $adminId);
assertTest('Move forward not_started -> received OK (200)', $step1['ok'] === true && $step1['code'] === 200);

// 3. Forward progression: received -> in_progress
$step2 = $bookingModel->updateServiceStatus($bidApprove, 'in_progress', 'Technician started', $adminId);
assertTest('Move forward received -> in_progress OK (200)', $step2['ok'] === true && $step2['code'] === 200);

// 4. Backward move: in_progress -> received fails (409)
$backRes = $bookingModel->updateServiceStatus($bidApprove, 'received', 'Try rollback', $adminId);
assertTest('Backward move in_progress -> received returns 409', $backRes['ok'] === false && $backRes['code'] === 409);

// 5. Same status: in_progress -> in_progress fails (409)
$sameRes = $bookingModel->updateServiceStatus($bidApprove, 'in_progress', 'Try same', $adminId);
assertTest('Same status move returns 409', $sameRes['ok'] === false && $sameRes['code'] === 409);

// 6. Forward progression: in_progress -> completed -> ready_for_pickup
$step3 = $bookingModel->updateServiceStatus($bidApprove, 'completed', 'Work finished', $adminId);
assertTest('Move forward in_progress -> completed OK (200)', $step3['ok'] === true && $step3['code'] === 200);
$step4 = $bookingModel->updateServiceStatus($bidApprove, 'ready_for_pickup', 'Keys at desk', $adminId);
assertTest('Move forward completed -> ready_for_pickup OK (200)', $step4['ok'] === true && $step4['code'] === 200);

// Verify history entries were recorded
$history = $bookingModel->getHistory($bidApprove);
assertTest('Status history contains all stepper steps', count($history) >= 4);

// -------------------------------------------------------------------------
// TEST GROUP 3: Admin Customers Query & Anti-N+1
// -------------------------------------------------------------------------
echo "\n-- Testing Customers Overview (User::getAllCustomers) --\n";

$customers = $userModel->getAllCustomers();
assertTest('getAllCustomers returns customer accounts', count($customers) >= 1);
$foundCustomer = null;
foreach ($customers as $c) {
    if ($c['user_id'] === $customerId) {
        $foundCustomer = $c;
        break;
    }
}
assertTest('Customer record has vehicle_count and booking_count', isset($foundCustomer['vehicle_count']) && isset($foundCustomer['booking_count']));
assertTest('Customer record has nested vehicles array', is_array($foundCustomer['vehicles'] ?? null) && count($foundCustomer['vehicles']) >= 1);

// Test customer search filter
$searchRes = $userModel->getAllCustomers('customer@ui.edu.ng');
assertTest('Search by email returns matching customer', count($searchRes) === 1 && $searchRes[0]['user_id'] === $customerId);

// -------------------------------------------------------------------------
// TEST GROUP 4: Payments Reporting & Dashboards
// -------------------------------------------------------------------------
echo "\n-- Testing Payments Report & Dashboards --\n";

$payReport = $paymentModel->getAll();
assertTest('Payment report contains total_success float', is_float($payReport['total_success']));
assertTest('Payment report contains payments array', is_array($payReport['payments']));

$adminDashboard = $bookingModel->getAdminDashboardStats();
assertTest('Admin dashboard has bookings_today', isset($adminDashboard['bookings_today']));
assertTest('Admin dashboard has pending_approvals', isset($adminDashboard['pending_approvals']));
assertTest('Admin dashboard has total_revenue float', is_float($adminDashboard['total_revenue']));
assertTest('Admin dashboard has active_services', $adminDashboard['active_services'] >= 5);
assertTest('Admin dashboard has recent_bookings list', is_array($adminDashboard['recent_bookings']));

$custDashboard = $bookingModel->getCustomerDashboardStats($customerId);
assertTest('Customer dashboard has vehicle_count', isset($custDashboard['vehicle_count']) && $custDashboard['vehicle_count'] >= 1);
assertTest('Customer dashboard has active_bookings count', isset($custDashboard['active_bookings']));

// Cleanup test records
$pdo->exec("DELETE FROM payments WHERE booking_id IN ({$bidApprove}, {$bidReject})");
$pdo->exec("DELETE FROM status_history WHERE booking_id IN ({$bidApprove}, {$bidReject})");
$pdo->exec("DELETE FROM bookings WHERE booking_id IN ({$bidApprove}, {$bidReject})");

echo "\n============================================\n";
echo "B5 SUMMARY: Passed: {$pass} | Failed: {$fail}\n";
echo "============================================\n";

if ($fail > 0) {
    exit(1);
}
