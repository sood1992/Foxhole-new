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

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Timezone
date_default_timezone_set('Asia/Kolkata');

// Site Configuration
if (!defined('SITE_NAME')) {
    define('SITE_NAME', 'Foxhole');
}
if (!defined('SITE_TAGLINE')) {
    define('SITE_TAGLINE', 'Your productivity command center');
}
if (!defined('SITE_URL')) {
    define('SITE_URL', 'http://neofox.live');
}

// Currency Configuration
if (!defined('CURRENCY_SYMBOL')) {
    define('CURRENCY_SYMBOL', '₹');
}
if (!defined('CURRENCY_CODE')) {
    define('CURRENCY_CODE', 'INR');
}

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
    // If active_role is set (multi-role system), check that
    if (isset($_SESSION['active_role'])) {
        return $_SESSION['active_role'] === $role;
    }
    // Fallback to primary role
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

// Helper function to get user roles
function getUserRoles($userId = null) {
    if ($userId === null && !isLoggedIn()) {
        return [];
    }

    $userId = $userId ?? $_SESSION['user_id'];
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT role FROM user_roles WHERE user_id = ? ORDER BY is_primary DESC");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Helper function to check if user has any of the given roles
function hasAnyRole($roles) {
    $userRoles = getUserRoles();
    return !empty(array_intersect($roles, $userRoles));
}

// Helper function to switch active role
function switchRole($role) {
    $userRoles = getUserRoles();
    if (in_array($role, $userRoles)) {
        $_SESSION['active_role'] = $role;
        return true;
    }
    return false;
}

// Helper function to get active role
function getActiveRole() {
    return $_SESSION['active_role'] ?? $_SESSION['role'] ?? null;
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
