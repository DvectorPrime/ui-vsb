<?php
/**
 * api/services.php (Customer/Logged in)
 * GET: Retrieves all active services for catalogue/booking (contract 2.8).
 */
require_once __DIR__ . '/../includes/bootstrap.php';

try {
    Auth::requireLogin();
    ApiResponse::requireMethod('GET');

    $services = (new Service())->getAllActive();
    ApiResponse::success($services, 'Services retrieved');
} catch (Throwable $e) {
    error_log('api/services.php: ' . $e->getMessage());
    ApiResponse::error('Server error. Please try again.', 500);
}
