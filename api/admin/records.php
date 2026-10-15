<?php
/**
 * api/admin/records.php (Admin only)
 * GET: Searches historical service records by query, service, status, or date range (contract 2.8).
 */
require_once __DIR__ . '/../../includes/bootstrap.php';

try {
    Auth::requireRole('admin');
    ApiResponse::requireMethod('GET');

    $filters = [
        'q'              => $_GET['q'] ?? null,
        'service_id'     => isset($_GET['service_id']) ? filter_var($_GET['service_id'], FILTER_VALIDATE_INT) : null,
        'service_status' => $_GET['service_status'] ?? null,
        'date_from'      => $_GET['date_from'] ?? null,
        'date_to'        => $_GET['date_to'] ?? null,
    ];

    $records = (new Booking())->searchRecords($filters);
    ApiResponse::success($records, 'Service records retrieved');
} catch (Throwable $e) {
    error_log('api/admin/records.php: ' . $e->getMessage());
    ApiResponse::error('Server error. Please try again.', 500);
}
