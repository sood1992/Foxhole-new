<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = getDBConnection();
$currentUser = getCurrentUser();

try {
    // GET: Get tasks list
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $exclude = isset($_GET['exclude']) ? intval($_GET['exclude']) : 0;

        $sql = "
            SELECT
                t.id,
                t.task_name,
                t.status,
                t.priority,
                p.project_name
            FROM tasks t
            JOIN projects p ON t.project_id = p.id
            WHERE 1=1
        ";

        if ($exclude) {
            $sql .= " AND t.id != ?";
        }

        $sql .= " ORDER BY p.project_name, t.task_name LIMIT 200";

        $stmt = $db->prepare($sql);
        if ($exclude) {
            $stmt->execute([$exclude]);
        } else {
            $stmt->execute();
        }

        $tasks = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'tasks' => $tasks
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
