<?php
/**
 * api/admin/customers.php (Admin only)
 * GET: Lists customer accounts with aggregate vehicle & booking counts and vehicle lists (contract 2.8).
 */
require_once __DIR__ . '/../../includes/bootstrap.php';

try {
    Auth::requireRole('admin');
    ApiResponse::requireMethod('GET');

    $q = isset($_GET['q']) ? trim((string) $_GET['q']) : null;
    $customers = (new User())->getAllCustomers($q);

    ApiResponse::success($customers, 'Customers retrieved');
} catch (Throwable $e) {
    error_log('api/admin/customers.php: ' . $e->getMessage());
    ApiResponse::error('Server error. Please try again.', 500);
}
