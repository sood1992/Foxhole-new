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

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    try {
        $entityType = $_POST['entity_type'] ?? null;
        $entityId = $_POST['entity_id'] ?? null;

        if (!$entityType || !$entityId) {
            throw new Exception('Entity type and ID are required');
        }

        $file = $_FILES['file'];

        // Validate file
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload error');
        }

        // Check file size (max 10MB)
        if ($file['size'] > 10 * 1024 * 1024) {
            throw new Exception('File too large. Maximum size is 10MB');
        }

        // Allowed file types
        $allowedTypes = [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'application/pdf',
            'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain', 'text/csv',
            'application/zip', 'application/x-rar-compressed'
        ];

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedTypes)) {
            throw new Exception('File type not allowed');
        }

        // Create uploads directory if it doesn't exist
        $uploadDir = __DIR__ . '/../uploads/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Create subdirectories by entity type
        $entityDir = $uploadDir . $entityType . 's/';
        if (!file_exists($entityDir)) {
            mkdir($entityDir, 0755, true);
        }

        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '_' . time() . '.' . $extension;
        $filepath = $entityDir . $filename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            throw new Exception('Failed to save file');
        }

        // Save to database
        $relativePath = 'uploads/' . $entityType . 's/' . $filename;
        $stmt = $db->prepare("
            INSERT INTO file_uploads (file_name, file_path, file_type, file_size, uploaded_by, entity_type, entity_id)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $file['name'],
            $relativePath,
            $mimeType,
            $file['size'],
            $userId,
            $entityType,
            $entityId
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'File uploaded successfully',
            'file' => [
                'id' => $db->lastInsertId(),
                'name' => $file['name'],
                'size' => $file['size'],
                'type' => $mimeType,
                'path' => $relativePath
            ]
        ]);

    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    exit;
}

// Get files for an entity
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $entityType = $_GET['entity_type'] ?? null;
    $entityId = $_GET['entity_id'] ?? null;

    if (!$entityType || !$entityId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Entity type and ID are required']);
        exit;
    }

    $stmt = $db->prepare("
        SELECT f.*, u.full_name as uploaded_by_name
        FROM file_uploads f
        JOIN users u ON f.uploaded_by = u.id
        WHERE f.entity_type = ? AND f.entity_id = ?
        ORDER BY f.created_at DESC
    ");
    $stmt->execute([$entityType, $entityId]);
    $files = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'files' => $files
    ]);
    exit;
}

// Delete file
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $input = json_decode(file_get_contents('php://input'), true);
    $fileId = $input['file_id'] ?? null;

    if (!$fileId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'File ID required']);
        exit;
    }

    // Get file info
    $stmt = $db->prepare("SELECT * FROM file_uploads WHERE id = ? AND uploaded_by = ?");
    $stmt->execute([$fileId, $userId]);
    $file = $stmt->fetch();

    if (!$file) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'File not found or no permission']);
        exit;
    }

    // Delete physical file
    $fullPath = __DIR__ . '/../' . $file['file_path'];
    if (file_exists($fullPath)) {
        unlink($fullPath);
    }

    // Delete from database
    $stmt = $db->prepare("DELETE FROM file_uploads WHERE id = ?");
    $stmt->execute([$fileId]);

    echo json_encode(['success' => true, 'message' => 'File deleted']);
    exit;
}
?>
