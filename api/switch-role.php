<?php
require_once '../config/config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$newRole = $input['role'] ?? '';

// Validate role
if (!in_array($newRole, ['admin', 'manager', 'employee'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid role']);
    exit;
}

// Check if user has this role
$userRoles = getUserRoles();
if (!in_array($newRole, $userRoles)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You do not have access to this role']);
    exit;
}

// Switch role
$_SESSION['active_role'] = $newRole;

// Determine redirect URL
$redirectUrls = [
    'admin' => '../admin/index.php',
    'manager' => '../manager/index.php',
    'employee' => '../employee/index.php'
];

echo json_encode([
    'success' => true,
    'message' => 'Role switched successfully',
    'redirect_url' => $redirectUrls[$newRole]
]);
