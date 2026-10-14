<?php
/**
 * POST api/auth/logout.php  (customer or admin)
 * Ends the session (contract 2.8).
 */
require_once __DIR__ . '/../../includes/bootstrap.php';

try {
    ApiResponse::requireMethod('POST');
    Auth::requireLogin();
    Auth::logout();
    ApiResponse::success(null, 'Logged out');
} catch (Throwable $e) {
    error_log('logout.php: ' . $e->getMessage());
    ApiResponse::error('Server error. Please try again.', 500);
}
