<?php
// Application Configuration

// Fix session path for shared hosting
$sessionPath = __DIR__ . '/../sessions';
if (!file_exists($sessionPath)) {
    mkdir($sessionPath, 0755, true);
}
if (is_writable($sessionPath)) {
    ini_set('session.save_path', $sessionPath);
}

session_start();

// Timezone
date_default_timezone_set('Asia/Kolkata');

// Site Configuration
define('SITE_NAME', 'Neofox Productivity Platform');
define('SITE_URL', 'https://neofoxmedia.com/foxhole/tests/v1');

// Include database configuration
require_once __DIR__ . '/database.php';

// Security: Prevent direct access
if (!defined('SECURE_ACCESS')) {
    define('SECURE_ACCESS', true);
}

// Helper function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

// Helper function to check user role
function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

// Helper function to redirect
function redirect($path) {
    header("Location: " . $path);
    exit;
}

// Helper function to get current user
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }

    $db = getDBConnection();
    $stmt = $db->prepare("SELECT id, username, email, full_name, role, job_title FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}
?>
