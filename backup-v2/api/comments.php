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
$userId = $_SESSION['user_id'];

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

try {
    switch ($action) {
        case 'add_project_comment':
            $projectId = $input['project_id'] ?? null;
            $comment = $input['comment'] ?? '';
            $isUpdate = $input['is_update'] ?? 0;

            if (!$projectId || empty($comment)) {
                throw new Exception('Project ID and comment are required');
            }

            $stmt = $db->prepare("
                INSERT INTO project_comments (project_id, user_id, comment, is_update, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$projectId, $userId, $comment, $isUpdate]);

            echo json_encode([
                'success' => true,
                'message' => 'Comment added successfully',
                'comment_id' => $db->lastInsertId()
            ]);
            break;

        case 'add_task_comment':
            $taskId = $input['task_id'] ?? null;
            $comment = $input['comment'] ?? '';
            $isUpdate = $input['is_update'] ?? 0;

            if (!$taskId || empty($comment)) {
                throw new Exception('Task ID and comment are required');
            }

            $stmt = $db->prepare("
                INSERT INTO task_comments (task_id, user_id, comment, is_update, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$taskId, $userId, $comment, $isUpdate]);

            echo json_encode([
                'success' => true,
                'message' => 'Comment added successfully',
                'comment_id' => $db->lastInsertId()
            ]);
            break;

        case 'get_project_comments':
            $projectId = $input['project_id'] ?? null;

            if (!$projectId) {
                throw new Exception('Project ID is required');
            }

            $stmt = $db->prepare("
                SELECT pc.*, u.full_name, u.job_title
                FROM project_comments pc
                JOIN users u ON pc.user_id = u.id
                WHERE pc.project_id = ?
                ORDER BY pc.created_at DESC
            ");
            $stmt->execute([$projectId]);
            $comments = $stmt->fetchAll();

            echo json_encode([
                'success' => true,
                'comments' => $comments
            ]);
            break;

        case 'get_task_comments':
            $taskId = $input['task_id'] ?? null;

            if (!$taskId) {
                throw new Exception('Task ID is required');
            }

            $stmt = $db->prepare("
                SELECT tc.*, u.full_name, u.job_title
                FROM task_comments tc
                JOIN users u ON tc.user_id = u.id
                WHERE tc.task_id = ?
                ORDER BY tc.created_at DESC
            ");
            $stmt->execute([$taskId]);
            $comments = $stmt->fetchAll();

            echo json_encode([
                'success' => true,
                'comments' => $comments
            ]);
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
