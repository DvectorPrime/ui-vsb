<?php
/**
 * api/admin/bookings.php (Admin only)
 * GET: Lists bookings with optional filters (q, status, date_from, date_to).
 * GET ?ref=...: Retrieves single booking with customer details and full history.
 * POST: Handles action=approve and action=reject (contract 2.8).
 */
require_once __DIR__ . '/../../includes/bootstrap.php';

try {
    Auth::requireRole('admin');
    $user = Auth::currentUser();
    $adminId = (int) $user['user_id'];
    $bookingModel = new Booking();

    $method = $_SERVER['REQUEST_METHOD'] ?? '';

    if ($method === 'GET') {
        $ref = trim((string) ($_GET['ref'] ?? ''));

        if ($ref !== '') {
            $booking = $bookingModel->getByRefAdmin($ref);
            if ($booking === null) {
                ApiResponse::error('Booking not found', 404);
            }
            ApiResponse::success($booking, 'Booking details retrieved');
        }

        $filters = [
            'q'         => $_GET['q'] ?? null,
            'status'    => $_GET['status'] ?? null,
            'date_from' => $_GET['date_from'] ?? null,
            'date_to'   => $_GET['date_to'] ?? null,
        ];

        $bookings = $bookingModel->getAll($filters);
        ApiResponse::success($bookings, 'Bookings retrieved');
    }

    if ($method === 'POST') {
        $input = ApiResponse::jsonInput();
        $action = (string) ($input['action'] ?? '');
        $bookingId = filter_var($input['booking_id'] ?? null, FILTER_VALIDATE_INT);
        $note = isset($input['note']) ? trim((string) $input['note']) : null;

        if ($bookingId === false || $bookingId <= 0) {
            ApiResponse::error('Valid booking_id is required', 400, ['booking_id' => 'Valid booking_id is required']);
        }

        if ($action === 'approve') {
            $result = $bookingModel->approve($bookingId, $adminId, $note);
            if ($result['ok']) {
                ApiResponse::success($result['booking'], $result['message'], 200);
            }
            ApiResponse::error($result['message'], $result['code']);
        }

        if ($action === 'reject') {
            $result = $bookingModel->reject($bookingId, $adminId, $note);
            if ($result['ok']) {
                ApiResponse::success($result['booking'], $result['message'], 200);
            }
            ApiResponse::error($result['message'], $result['code']);
        }

        ApiResponse::error('Invalid action. Supported actions are "approve" and "reject"', 400);
    }

    header('Allow: GET, POST');
    ApiResponse::error('Method not allowed', 405);
} catch (Throwable $e) {
    error_log('api/admin/bookings.php: ' . $e->getMessage());
    ApiResponse::error('Server error. Please try again.', 500);
}
