<?php
header('Content-Type: application/json');
require_once '../config/config.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !hasRole('admin')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = getDBConnection();

// Get all users
$users = $db->query("
    SELECT
        id,
        username,
        email,
        full_name,
        role,
        is_active,
        created_at,
        last_login
    FROM users
    ORDER BY id
")->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'total_users' => count($users),
    'current_user_id' => $_SESSION['user_id'],
    'users' => $users
], JSON_PRETTY_PRINT);
