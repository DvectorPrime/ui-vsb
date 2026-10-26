<?php
/**
 * logout.php
 * Clears the authenticated session and redirects to the login screen.
 */
require_once __DIR__ . '/includes/bootstrap.php';
Auth::logout();
header('Location: ' . BASE_URL . 'login.php');
exit;
