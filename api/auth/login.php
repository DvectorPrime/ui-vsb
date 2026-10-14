<?php
/**
 * POST api/auth/login.php  (public)
 * Checks the email and password and starts a session (contract 2.8).
 */
require_once __DIR__ . '/../../includes/bootstrap.php';

try {
    ApiResponse::requireMethod('POST');
    $input = ApiResponse::jsonInput();

    $email    = (string) ($input['email'] ?? '');
    $password = (string) ($input['password'] ?? '');

    $row = ($email === '' || $password === '') ? null : (new User())->login($email, $password);

    // Same message whether the email is unknown or the password is wrong.
    if ($row === null) {
        ApiResponse::error('Invalid email or password', 401);
    }

    Auth::login($row);
    ApiResponse::success([
        'user'     => User::toPublic($row),
        'redirect' => Auth::dashboardFor($row['role']),
    ], 'Login successful');
} catch (Throwable $e) {
    error_log('login.php: ' . $e->getMessage());
    ApiResponse::error('Server error. Please try again.', 500);
}
