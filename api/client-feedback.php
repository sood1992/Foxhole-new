<?php
/**
 * Client Feedback API
 * For PM to add client feedback and employees to respond
 */

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
$userRole = $_SESSION['role'];

// GET - Fetch feedback for a project
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $projectId = $_GET['project_id'] ?? null;
    $feedbackId = $_GET['feedback_id'] ?? null;

    if ($feedbackId) {
        // Get specific feedback with responses
        $stmt = $db->prepare("
            SELECT cf.*,
                   u1.full_name as added_by_name,
                   u2.full_name as assigned_to_name,
                   p.project_name,
                   t.task_name
            FROM client_feedback cf
            JOIN users u1 ON cf.added_by = u1.id
            LEFT JOIN users u2 ON cf.assigned_to = u2.id
            JOIN projects p ON cf.project_id = p.id
            LEFT JOIN tasks t ON cf.task_id = t.id
            WHERE cf.id = ?
        ");
        $stmt->execute([$feedbackId]);
        $feedback = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$feedback) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Feedback not found']);
            exit;
        }

        // Get responses
        $stmt = $db->prepare("
            SELECT fr.*, u.full_name as user_name, u.role
            FROM feedback_responses fr
            JOIN users u ON fr.user_id = u.id
            WHERE fr.feedback_id = ?
            ORDER BY fr.created_at ASC
        ");
        $stmt->execute([$feedbackId]);
        $feedback['responses'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'feedback' => $feedback]);
        exit;
    }

    if ($projectId) {
        // Get all feedback for project
        $query = "
            SELECT cf.*,
                   u1.full_name as added_by_name,
                   u2.full_name as assigned_to_name,
                   t.task_name,
                   (SELECT COUNT(*) FROM feedback_responses WHERE feedback_id = cf.id) as response_count
            FROM client_feedback cf
            JOIN users u1 ON cf.added_by = u1.id
            LEFT JOIN users u2 ON cf.assigned_to = u2.id
            LEFT JOIN tasks t ON cf.task_id = t.id
            WHERE cf.project_id = ?
        ";

        // Filter by assigned user for employees
        if ($userRole === 'employee') {
            $query .= " AND (cf.assigned_to = ? OR cf.assigned_to IS NULL)";
            $stmt = $db->prepare($query . " ORDER BY cf.created_at DESC");
            $stmt->execute([$projectId, $userId]);
        } else {
            $stmt = $db->prepare($query . " ORDER BY cf.created_at DESC");
            $stmt->execute([$projectId]);
        }

        $feedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'feedbacks' => $feedbacks]);
        exit;
    }

    // Get all feedback assigned to current user (for employee dashboard)
    if ($userRole === 'employee' || $userRole === 'manager') {
        $stmt = $db->prepare("
            SELECT cf.*,
                   p.project_name,
                   t.task_name,
                   u.full_name as added_by_name
            FROM client_feedback cf
            JOIN projects p ON cf.project_id = p.id
            LEFT JOIN tasks t ON cf.task_id = t.id
            JOIN users u ON cf.added_by = u.id
            WHERE cf.assigned_to = ? AND cf.status != 'completed'
            ORDER BY
                FIELD(cf.priority, 'urgent', 'high', 'medium', 'low'),
                cf.due_date ASC
        ");
        $stmt->execute([$userId]);
        $feedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'feedbacks' => $feedbacks]);
        exit;
    }
}

// POST - Add new feedback
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    // Only managers and admins can add feedback
    if (!in_array($userRole, ['admin', 'manager'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Only managers can add client feedback']);
        exit;
    }

    $projectId = $input['project_id'] ?? null;
    $taskId = $input['task_id'] ?? null;
    $title = $input['feedback_title'] ?? null;
    $text = $input['feedback_text'] ?? null;
    $priority = $input['priority'] ?? 'medium';
    $assignedTo = $input['assigned_to'] ?? null;
    $dueDate = $input['due_date'] ?? null;

    if (!$projectId || !$title || !$text) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Project ID, title, and feedback text are required']);
        exit;
    }

    try {
        $stmt = $db->prepare("
            INSERT INTO client_feedback
            (project_id, task_id, feedback_title, feedback_text, priority, assigned_to, added_by, due_date, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([
            $projectId,
            $taskId,
            $title,
            $text,
            $priority,
            $assignedTo,
            $userId,
            $dueDate
        ]);

        $feedbackId = $db->lastInsertId();

        echo json_encode([
            'success' => true,
            'message' => 'Client feedback added successfully',
            'feedback_id' => $feedbackId
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// PUT - Update feedback status or add response
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    $feedbackId = $input['feedback_id'] ?? null;

    if (!$feedbackId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Feedback ID required']);
        exit;
    }

    // Add response
    if (isset($input['response_text'])) {
        $stmt = $db->prepare("
            INSERT INTO feedback_responses (feedback_id, user_id, response_text)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$feedbackId, $userId, $input['response_text']]);

        // Auto-update feedback status to in_progress if pending
        $db->prepare("
            UPDATE client_feedback
            SET status = 'in_progress'
            WHERE id = ? AND status = 'pending'
        ")->execute([$feedbackId]);

        echo json_encode(['success' => true, 'message' => 'Response added successfully']);
        exit;
    }

    // Update feedback status/details
    $updates = [];
    $params = [];

    if (isset($input['status'])) {
        $updates[] = "status = ?";
        $params[] = $input['status'];

        if ($input['status'] === 'completed') {
            $updates[] = "completed_date = NOW()";
        }
    }

    if (isset($input['priority'])) {
        $updates[] = "priority = ?";
        $params[] = $input['priority'];
    }

    if (isset($input['assigned_to'])) {
        $updates[] = "assigned_to = ?";
        $params[] = $input['assigned_to'];
    }

    if (isset($input['due_date'])) {
        $updates[] = "due_date = ?";
        $params[] = $input['due_date'];
    }

    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No updates provided']);
        exit;
    }

    $params[] = $feedbackId;
    $query = "UPDATE client_feedback SET " . implode(", ", $updates) . " WHERE id = ?";

    $stmt = $db->prepare($query);
    $stmt->execute($params);

    echo json_encode(['success' => true, 'message' => 'Feedback updated successfully']);
    exit;
}

// DELETE - Remove feedback
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $input = json_decode(file_get_contents('php://input'), true);
    $feedbackId = $input['feedback_id'] ?? null;

    if (!$feedbackId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Feedback ID required']);
        exit;
    }

    // Only admins and managers can delete
    if (!in_array($userRole, ['admin', 'manager'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        exit;
    }

    $stmt = $db->prepare("DELETE FROM client_feedback WHERE id = ?");
    $stmt->execute([$feedbackId]);

    echo json_encode(['success' => true, 'message' => 'Feedback deleted successfully']);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
?>
