<?php
/**
 * api/admin/status.php (Admin only)
 * POST: Advances the workshop service status of an approved booking forward (contract 2.8).
 */
require_once __DIR__ . '/../../includes/bootstrap.php';

try {
    Auth::requireRole('admin');
    ApiResponse::requireMethod('POST');

    $user    = Auth::currentUser();
    $adminId = (int) $user['user_id'];
    $input   = ApiResponse::jsonInput();

    $bookingId     = filter_var($input['booking_id'] ?? null, FILTER_VALIDATE_INT);
    $serviceStatus = trim((string) ($input['service_status'] ?? ''));
    $note          = isset($input['note']) ? trim((string) $input['note']) : null;

    if ($bookingId === false || $bookingId <= 0) {
        ApiResponse::error('Valid booking_id is required', 400, ['booking_id' => 'Valid booking_id is required']);
    }

    if ($serviceStatus === '') {
        ApiResponse::error('service_status is required', 400, ['service_status' => 'service_status is required']);
    }

    $result = (new Booking())->updateServiceStatus($bookingId, $serviceStatus, $note, $adminId);

    if ($result['ok']) {
        ApiResponse::success($result['booking'], $result['message'], 200);
    }

    ApiResponse::error($result['message'], $result['code']);
} catch (Throwable $e) {
    error_log('api/admin/status.php: ' . $e->getMessage());
    ApiResponse::error('Server error. Please try again.', 500);
}
