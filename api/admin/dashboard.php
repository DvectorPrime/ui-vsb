<?php
/**
 * api/admin/dashboard.php (Admin only)
 * GET: Retrieves operational KPIs and 5 most recent bookings for the admin dashboard (contract 2.8).
 */
require_once __DIR__ . '/../../includes/bootstrap.php';

try {
    Auth::requireRole('admin');
    ApiResponse::requireMethod('GET');

    $stats = (new Booking())->getAdminDashboardStats();
    ApiResponse::success($stats, 'Dashboard data retrieved');
} catch (Throwable $e) {
    error_log('api/admin/dashboard.php: ' . $e->getMessage());
    ApiResponse::error('Server error. Please try again.', 500);
}
