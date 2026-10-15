<?php
/**
 * api/bookings.php (Customer only)
 * GET: Lists own bookings (newest first).
 * GET ?ref=...: Retrieves full details of a specific booking including audit history (contract 2.8).
 */
require_once __DIR__ . '/../includes/bootstrap.php';

try {
    Auth::requireRole('customer');
    ApiResponse::requireMethod('GET');

    $user   = Auth::currentUser();
    $userId = (int) $user['user_id'];
    $bookingModel = new Booking();

    $ref = trim((string) ($_GET['ref'] ?? ''));

    if ($ref !== '') {
        $booking = $bookingModel->getByRef($ref, $userId);
        if ($booking === null) {
            ApiResponse::error('Booking not found', 404);
        }
        ApiResponse::success($booking, 'Booking details retrieved');
    }

    $bookings = $bookingModel->getByUser($userId);
    ApiResponse::success($bookings, 'Bookings retrieved');
} catch (Throwable $e) {
    error_log('api/bookings.php: ' . $e->getMessage());
    ApiResponse::error('Server error. Please try again.', 500);
}
