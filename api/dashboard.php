<?php
/**
 * api/dashboard.php (Customer only)
 * GET: Retrieves summary counts and latest booking for the customer dashboard (contract 2.8).
 */
require_once __DIR__ . '/../includes/bootstrap.php';

try {
    Auth::requireRole('customer');
    ApiResponse::requireMethod('GET');

    $user   = Auth::currentUser();
    $userId = (int) $user['user_id'];

    $stats = (new Booking())->getCustomerDashboardStats($userId);
    ApiResponse::success($stats, 'Dashboard data retrieved');
} catch (Throwable $e) {
    error_log('api/dashboard.php: ' . $e->getMessage());
    ApiResponse::error('Server error. Please try again.', 500);
}
