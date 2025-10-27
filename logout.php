<?php
// Disable error output to prevent header issues
error_reporting(0);
ini_set('display_errors', 0);

// Start output buffering
ob_start();

// Start session
session_start();

// Destroy all session data
session_unset();
session_destroy();

// Delete session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Clean output buffer
ob_end_clean();

// Redirect to login
header('Location: login.php');
exit;
