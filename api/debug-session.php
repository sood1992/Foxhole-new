<?php
header('Content-Type: application/json');
require_once '../config/config.php';
require_once '../includes/functions.php';

echo json_encode([
    'session_started' => session_status() === PHP_SESSION_ACTIVE,
    'session_id' => session_id(),
    'is_logged_in' => isLoggedIn(),
    'session_data' => [
        'user_id' => $_SESSION['user_id'] ?? null,
        'role' => $_SESSION['role'] ?? null,
        'username' => $_SESSION['username'] ?? null,
    ],
    'has_admin_role' => hasRole('admin'),
    'has_manager_role' => hasRole('manager'),
    'has_array_role' => hasRole(['admin', 'manager']),
    'current_user' => getCurrentUser(),
    'post_method' => $_SERVER['REQUEST_METHOD'] === 'POST',
    'get_method' => $_SERVER['REQUEST_METHOD'] === 'GET',
], JSON_PRETTY_PRINT);
