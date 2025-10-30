<?php
/**
 * Deliverables API
 * Upload and manage project deliverables
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
$currentUser = getCurrentUser();
$userRole = $_SESSION['role'];

// GET - Fetch deliverables
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $projectId = $_GET['project_id'] ?? null;
    $feedbackId = $_GET['feedback_id'] ?? null;

    if (!$projectId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Project ID required']);
        exit;
    }

    $query = "
        SELECT
            pd.*,
            u.full_name as uploaded_by_name,
            cf.feedback_title,
            (SELECT COUNT(*) FROM deliverable_versions WHERE deliverable_id = pd.id) as version_count
        FROM project_deliverables pd
        LEFT JOIN users u ON pd.uploaded_by = u.id
        LEFT JOIN client_feedback cf ON pd.feedback_id = cf.id
        WHERE pd.project_id = ?
    ";

    $params = [$projectId];

    if ($feedbackId) {
        $query .= " AND pd.feedback_id = ?";
        $params[] = $feedbackId;
    }

    $query .= " ORDER BY pd.created_at DESC";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $deliverables = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'deliverables' => $deliverables
    ]);
    exit;
}

// POST - Upload new deliverable
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Only managers and admins can upload deliverables
    if (!in_array($userRole, ['admin', 'manager'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Only managers can upload deliverables']);
        exit;
    }

    // Check if file upload or data submission
    if (isset($_FILES['file'])) {
        // Handle file upload
        $projectId = $_POST['project_id'] ?? null;
        $feedbackId = $_POST['feedback_id'] ?? null;
        $taskId = $_POST['task_id'] ?? null;
        $deliverableName = $_POST['deliverable_name'] ?? null;
        $description = $_POST['description'] ?? '';

        if (!$projectId || !$deliverableName) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            exit;
        }

        $file = $_FILES['file'];
        $uploadDir = '../uploads/deliverables/project_' . $projectId . '/';

        // Create directory if it doesn't exist
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Generate unique filename
        $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $fileName = uniqid() . '_' . time() . '.' . $fileExtension;
        $filePath = $uploadDir . $fileName;

        // Determine file type
        $fileType = 'other';
        $mimeType = mime_content_type($file['tmp_name']);
        if (strpos($mimeType, 'video/') === 0) {
            $fileType = 'video';
        } elseif (strpos($mimeType, 'image/') === 0) {
            $fileType = 'image';
        } elseif (strpos($mimeType, 'application/pdf') === 0 || strpos($mimeType, 'application/msword') === 0) {
            $fileType = 'document';
        }

        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            // Get next version number for this feedback/task
            $versionQuery = "SELECT COALESCE(MAX(version_number), 0) + 1 as next_version
                           FROM project_deliverables
                           WHERE project_id = ?";
            $versionParams = [$projectId];

            if ($feedbackId) {
                $versionQuery .= " AND feedback_id = ?";
                $versionParams[] = $feedbackId;
            }

            $stmt = $db->prepare($versionQuery);
            $stmt->execute($versionParams);
            $versionNumber = $stmt->fetch(PDO::FETCH_ASSOC)['next_version'];

            // Insert deliverable record
            $stmt = $db->prepare("
                INSERT INTO project_deliverables
                (project_id, task_id, feedback_id, deliverable_name, file_path, file_type, version_number, description, uploaded_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $projectId,
                $taskId ?: null,
                $feedbackId ?: null,
                $deliverableName,
                $filePath,
                $fileType,
                $versionNumber,
                $description,
                $currentUser['id']
            ]);

            $deliverableId = $db->lastInsertId();

            // If this is a revision (version > 1), update feedback revision count
            if ($feedbackId && $versionNumber > 1) {
                $db->prepare("UPDATE client_feedback SET revision_count = revision_count + 1 WHERE id = ?")
                   ->execute([$feedbackId]);
            }

            echo json_encode([
                'success' => true,
                'message' => 'Deliverable uploaded successfully',
                'deliverable_id' => $deliverableId,
                'version_number' => $versionNumber,
                'file_path' => $filePath
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to upload file']);
        }
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No file uploaded']);
    }
    exit;
}

// DELETE - Remove deliverable
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    if (!in_array($userRole, ['admin', 'manager'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        exit;
    }

    parse_str(file_get_contents("php://input"), $input);
    $deliverableId = $input['deliverable_id'] ?? null;

    if (!$deliverableId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Deliverable ID required']);
        exit;
    }

    // Get file path before deleting
    $stmt = $db->prepare("SELECT file_path FROM project_deliverables WHERE id = ?");
    $stmt->execute([$deliverableId]);
    $deliverable = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($deliverable) {
        // Delete file
        if (file_exists($deliverable['file_path'])) {
            unlink($deliverable['file_path']);
        }

        // Delete database record
        $stmt = $db->prepare("DELETE FROM project_deliverables WHERE id = ?");
        $stmt->execute([$deliverableId]);

        echo json_encode(['success' => true, 'message' => 'Deliverable deleted']);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Deliverable not found']);
    }
    exit;
}
