<?php
/**
 * LOGOUT HANDLER
 * Destroys session and redirects to login
 * Works from any directory (admin, manager, employee)
 */

// Suppress errors to prevent header issues
@error_reporting(0);
@ini_set('display_errors', 0);

// Configure session path (same as config.php)
$sessionPath = __DIR__ . '/sessions';
if (file_exists($sessionPath) && is_writable($sessionPath)) {
    @ini_set('session.save_path', $sessionPath);
}

// Start session if not already active
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// Clear all session data
$_SESSION = array();

// Delete session cookie
if (isset($_COOKIE[session_name()])) {
    $params = @session_get_cookie_params();
    @setcookie(
        session_name(),
        '',
        time() - 86400,
        !empty($params['path']) ? $params['path'] : '/',
        !empty($params['domain']) ? $params['domain'] : '',
        !empty($params['secure']),
        !empty($params['httponly'])
    );
}

// Destroy the session
@session_destroy();

// Prevent caching
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

// Redirect to login page
header('Location: login.php', true, 302);
exit();
