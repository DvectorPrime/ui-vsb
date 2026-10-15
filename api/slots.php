<?php
/**
 * api/slots.php (Customer only)
 * GET: Retrieves real-time workshop slot availability for a specified date (contract 2.8).
 * This is the primary asynchronous Fetch operation required by the project specifications.
 */
require_once __DIR__ . '/../includes/bootstrap.php';

try {
    Auth::requireRole('customer');
    ApiResponse::requireMethod('GET');

    $date = trim((string) ($_GET['date'] ?? ''));
    if ($date === '') {
        ApiResponse::error('Date is required', 400, ['date' => 'Date parameter is required']);
    }

    $serviceId = isset($_GET['service_id']) ? filter_var($_GET['service_id'], FILTER_VALIDATE_INT) : null;
    if ($serviceId !== null && $serviceId <= 0) {
        $serviceId = null;
    }

    $result = (new Booking())->getAvailableSlots($date, $serviceId !== false ? $serviceId : null);

    if (!$result['ok']) {
        ApiResponse::error('Validation failed', $result['code'], $result['errors']);
    }

    if (!empty($result['closed'])) {
        ApiResponse::success([], $result['message'], 200);
    }

    ApiResponse::success($result['data'], $result['message'], 200);
} catch (Throwable $e) {
    error_log('api/slots.php: ' . $e->getMessage());
    ApiResponse::error('Server error. Please try again.', 500);
}
