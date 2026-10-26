<?php
/**
 * api/payment.php (Customer/Logged in)
 * POST: Processes a simulated payment for a booking hold (contract 2.8, 2.9, Phase B4).
 * Enforces strict Luhn validation, role & ownership verification, 30-minute hold expiry,
 * and swappable payment gateway charging via PaymentGatewayInterface.
 * Full card number and CVV are NEVER stored or logged.
 */
require_once __DIR__ . '/../includes/bootstrap.php';

try {
    Auth::requireRole('customer');
    $user = Auth::currentUser();
    $userId = (int) $user['user_id'];
    ApiResponse::requireMethod('POST');

    $bookingModel = new Booking();
    $bookingModel->expireStale();

    // Read JSON payload or fallback to POST form data
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput === false ? '' : $rawInput, true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $bookingId  = filter_var($input['booking_id'] ?? null, FILTER_VALIDATE_INT);
    $cardName   = trim((string) ($input['card_name'] ?? ''));
    $cardNumber = (string) ($input['card_number'] ?? '');
    $expiry     = trim((string) ($input['expiry'] ?? ''));
    $cvv        = trim((string) ($input['cvv'] ?? ''));

    if (!$bookingId || $bookingId <= 0) {
        ApiResponse::error('Invalid booking ID', 400, ['booking_id' => 'Valid booking_id is required']);
    }

    $booking = $bookingModel->findById($bookingId);
    if (!$booking) {
        ApiResponse::error('Booking not found', 404);
    }

    // Ownership check (IDOR prevention)
    if ((int) $booking['user_id'] !== $userId) {
        ApiResponse::error('Forbidden. This booking does not belong to you.', 403);
    }

    // Lifecycle check
    $currentStatus = (string) $booking['booking_status'];
    if ($currentStatus === 'pending_approval' || $currentStatus === 'approved') {
        ApiResponse::error('This booking has already been paid.', 409);
    }
    if ($currentStatus === 'cancelled') {
        ApiResponse::error('This booking has expired. Please make a new booking.', 409);
    }
    if ($currentStatus === 'rejected') {
        ApiResponse::error('This booking was rejected.', 409);
    }
    if ($currentStatus !== 'pending_payment') {
        ApiResponse::error('This booking is not available for payment.', 409);
    }

    // Validate card details server-side
    $errors = [];

    if ($cardName === '' || strlen($cardName) < 2 || strlen($cardName) > 100) {
        $errors['card_name'] = 'Name on card is required (2-100 characters)';
    }

    $cleanNumber = preg_replace('/\s+|-/', '', $cardNumber);
    if (!preg_match('/^\d{16}$/', $cleanNumber)) {
        $errors['card_number'] = 'Card number must be 16 digits';
    } elseif (!SimulatedPaymentGateway::isValidLuhn($cleanNumber)) {
        $errors['card_number'] = 'Invalid card number';
    }

    if (!preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $expiry)) {
        $errors['expiry'] = 'Invalid expiry format (MM/YY)';
    }

    if (!preg_match('/^\d{3}$/', $cvv)) {
        $errors['cvv'] = 'CVV must be 3 digits';
    }

    // On validation failure, return HTTP 400 immediately — NO payment row is created
    if (!empty($errors)) {
        ApiResponse::error('Payment validation failed', 400, $errors);
    }

    // Call swappable Payment Gateway via PaymentGatewayInterface (polymorphism)
    $gateway = new SimulatedPaymentGateway();
    $cardPayload = [
        'card_name'   => $cardName,
        'card_number' => $cleanNumber,
        'expiry'      => $expiry,
        'cvv'         => $cvv,
    ];

    $chargeResult = $gateway->charge($cardPayload, (float) $booking['amount']);
    $cardLast4 = substr($cleanNumber, -4);
    $paymentModel = new Payment();

    if ($chargeResult['success'] === true) {
        // Successful payment: wrap in transaction to update status, record payment, and log history
        $pdo = Database::getInstance()->getConnection();
        $pdo->beginTransaction();

        try {
            $paymentModel->record(
                $bookingId,
                $chargeResult['transaction_ref'],
                (float) $booking['amount'],
                'success',
                null,
                $cardLast4,
                'test_card'
            );

            $updateStmt = $pdo->prepare("UPDATE bookings SET booking_status = 'pending_approval' WHERE booking_id = :bid");
            $updateStmt->execute([':bid' => $bookingId]);

            $bookingModel->addHistory($bookingId, 'pending_approval', 'Payment received', $userId);

            $pdo->commit();
        } catch (Throwable $txErr) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $txErr;
        }

        ApiResponse::success([
            'transaction_ref' => $chargeResult['transaction_ref'],
            'booking_ref'     => $booking['booking_ref'],
            'amount'          => (float) $booking['amount'],
            'status'          => 'success',
            'card_last4'      => $cardLast4,
            'paid_at'         => date('Y-m-d H:i:s'),
        ], 'Payment successful');
    } else {
        // Failed payment attempt: record failure row for audit, leave booking in pending_payment
        $paymentModel->record(
            $bookingId,
            $chargeResult['transaction_ref'],
            (float) $booking['amount'],
            'failed',
            $chargeResult['reason'],
            $cardLast4,
            'test_card'
        );

        $messages = [
            'card_declined'      => 'Your card was declined',
            'insufficient_funds' => 'Insufficient funds',
            'expired_card'       => 'Card has expired',
        ];
        $msg = $messages[$chargeResult['reason']] ?? 'Payment was declined';

        ApiResponse::error($msg, 422, null, [
            'reason'          => $chargeResult['reason'],
            'transaction_ref' => $chargeResult['transaction_ref'],
        ]);
    }
} catch (Throwable $e) {
    error_log('api/payment.php error: ' . $e->getMessage());
    ApiResponse::error('Server error processing payment', 500);
}
