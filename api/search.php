<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = getDBConnection();
$currentUser = getCurrentUser();
$query = $_GET['q'] ?? '';

if (strlen($query) < 2) {
    echo json_encode(['results' => []]);
    exit;
}

$searchTerm = '%' . $query . '%';
$results = [
    'projects' => [],
    'tasks' => [],
    'users' => []
];

// Search Projects
if (hasRole('admin') || hasRole('manager')) {
    $stmt = $db->prepare("
        SELECT
            p.id,
            p.project_name,
            p.client_name,
            p.status,
            u.full_name as manager_name
        FROM projects p
        LEFT JOIN users u ON p.assigned_manager = u.id
        WHERE p.project_name LIKE ? OR p.client_name LIKE ?
        LIMIT 10
    ");
    $stmt->execute([$searchTerm, $searchTerm]);
    $results['projects'] = $stmt->fetchAll();
}

// Search Tasks
$taskQuery = "
    SELECT
        t.id,
        t.task_name,
        t.status,
        t.priority,
        p.project_name,
        u.full_name as assigned_to_name
    FROM tasks t
    JOIN projects p ON t.project_id = p.id
    LEFT JOIN users u ON t.assigned_to = u.id
    WHERE (t.task_name LIKE ? OR t.description LIKE ?)
";

// Employees only see their own tasks
if (hasRole('employee')) {
    $taskQuery .= " AND t.assigned_to = " . $currentUser['id'];
}

$taskQuery .= " LIMIT 10";

$stmt = $db->prepare($taskQuery);
$stmt->execute([$searchTerm, $searchTerm]);
$results['tasks'] = $stmt->fetchAll();

// Search Users (admin and managers only)
if (hasRole('admin') || hasRole('manager')) {
    $stmt = $db->prepare("
        SELECT
            id,
            full_name,
            email,
            job_title,
            role
        FROM users
        WHERE (full_name LIKE ? OR email LIKE ? OR job_title LIKE ?)
            AND is_active = 1
        LIMIT 10
    ");
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    $results['users'] = $stmt->fetchAll();
}

echo json_encode($results);
