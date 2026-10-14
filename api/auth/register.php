<?php
/**
 * POST api/auth/register.php  (public)
 * Creates a customer account and logs the new user in (contract 2.8).
 */
require_once __DIR__ . '/../../includes/bootstrap.php';

try {
    ApiResponse::requireMethod('POST');
    $input  = ApiResponse::jsonInput();
    $result = (new User())->register($input);

    if (!$result['ok']) {
        $message = $result['code'] === 409 ? 'Email is already registered' : 'Validation failed';
        ApiResponse::error($message, $result['code'], $result['errors']);
    }

    // Row from findById is already the public shape; Auth needs the same fields.
    Auth::login($result['user']);
    ApiResponse::success(['user' => $result['user']], 'Account created', 201);
} catch (Throwable $e) {
    error_log('register.php: ' . $e->getMessage());
    ApiResponse::error('Server error. Please try again.', 500);
}
