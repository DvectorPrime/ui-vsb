<?php
/**
 * GET api/auth/me.php  (customer or admin)
 * Returns the logged-in user. The frontend calls this on page load to
 * learn who is signed in (contract 2.8).
 */
require_once __DIR__ . '/../../includes/bootstrap.php';

try {
    ApiResponse::requireMethod('GET');
    Auth::requireLogin();

    // Read from the database (not the session) so changes show up straight away.
    $user = (new User())->findById(Auth::currentUser()['user_id']);
    if ($user === null) {          // account deleted while the session was alive
        Auth::logout();
        ApiResponse::error('Please log in first', 401);
    }
    ApiResponse::success(['user' => $user], 'OK');
} catch (Throwable $e) {
    error_log('me.php: ' . $e->getMessage());
    ApiResponse::error('Server error. Please try again.', 500);
}
