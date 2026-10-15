<?php
/**
 * api/admin/payments.php (Admin only)
 * GET: Retrieves payment audit logs and calculates total revenue (contract 2.8).
 */
require_once __DIR__ . '/../../includes/bootstrap.php';

try {
    Auth::requireRole('admin');
    ApiResponse::requireMethod('GET');

    $filters = [
        'status'    => $_GET['status'] ?? null,
        'date_from' => $_GET['date_from'] ?? null,
        'date_to'   => $_GET['date_to'] ?? null,
    ];

    $report = (new Payment())->getAll($filters);
    ApiResponse::success($report, 'Payments retrieved');
} catch (Throwable $e) {
    error_log('api/admin/payments.php: ' . $e->getMessage());
    ApiResponse::error('Server error. Please try again.', 500);
}
