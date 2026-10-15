<?php
/**
 * api/book.php (Customer only)
 * POST: Places a booking hold on a workshop slot (contract 2.8).
 * Enforces transaction locking to prevent race conditions / double bookings.
 */
require_once __DIR__ . '/../includes/bootstrap.php';

try {
    Auth::requireRole('customer');
    ApiResponse::requireMethod('POST');

    $user   = Auth::currentUser();
    $userId = (int) $user['user_id'];
    $input  = ApiResponse::jsonInput();

    $result = (new Booking())->create($userId, $input);

    if ($result['ok']) {
        ApiResponse::success($result['data'], $result['message'], 201);
    }

    if ($result['code'] === 403 || $result['code'] === 409) {
        ApiResponse::error($result['message'], $result['code']);
    }

    ApiResponse::error($result['message'] ?? 'Validation failed', $result['code'], $result['errors'] ?? null);
} catch (Throwable $e) {
    error_log('api/book.php: ' . $e->getMessage());
    ApiResponse::error('Server error. Please try again.', 500);
}
