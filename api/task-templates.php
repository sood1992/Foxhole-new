<?php
require_once '../config/config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = getDBConnection();
$currentUser = getCurrentUser();

// GET requests - retrieve templates
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'list';

    try {
        if ($action === 'list') {
            // Get user's templates and public templates
            $stmt = $db->prepare("
                SELECT
                    t.*,
                    u.full_name as creator_name,
                    COUNT(ti.id) as task_count
                FROM task_templates t
                LEFT JOIN users u ON t.created_by = u.id
                LEFT JOIN task_template_items ti ON t.id = ti.template_id
                WHERE t.created_by = ? OR t.is_public = 1
                GROUP BY t.id
                ORDER BY t.created_at DESC
            ");
            $stmt->execute([$currentUser['id']]);
            $templates = $stmt->fetchAll();

            echo json_encode([
                'success' => true,
                'templates' => $templates
            ]);
        } elseif ($action === 'get') {
            $templateId = intval($_GET['template_id'] ?? 0);

            if (!$templateId) {
                throw new Exception('Template ID is required');
            }

            // Get template details
            $stmt = $db->prepare("
                SELECT t.*, u.full_name as creator_name
                FROM task_templates t
                LEFT JOIN users u ON t.created_by = u.id
                WHERE t.id = ? AND (t.created_by = ? OR t.is_public = 1)
            ");
            $stmt->execute([$templateId, $currentUser['id']]);
            $template = $stmt->fetch();

            if (!$template) {
                throw new Exception('Template not found or access denied');
            }

            // Get template items
            $itemsStmt = $db->prepare("
                SELECT *
                FROM task_template_items
                WHERE template_id = ?
                ORDER BY order_index ASC
            ");
            $itemsStmt->execute([$templateId]);
            $template['items'] = $itemsStmt->fetchAll();

            echo json_encode([
                'success' => true,
                'template' => $template
            ]);
        }
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// POST requests - create/update/delete templates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    try {
        $db->beginTransaction();

        switch ($action) {
            case 'create':
                $templateName = trim($input['template_name'] ?? '');
                $description = trim($input['description'] ?? '');
                $category = trim($input['category'] ?? '');
                $isPublic = intval($input['is_public'] ?? 0);
                $items = $input['items'] ?? [];

                if (empty($templateName)) {
                    throw new Exception('Template name is required');
                }

                if (empty($items)) {
                    throw new Exception('At least one task item is required');
                }

                // Create template
                $stmt = $db->prepare("
                    INSERT INTO task_templates (template_name, description, created_by, is_public, category)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$templateName, $description, $currentUser['id'], $isPublic, $category]);
                $templateId = $db->lastInsertId();

                // Add template items
                $itemStmt = $db->prepare("
                    INSERT INTO task_template_items
                    (template_id, task_name, description, estimated_hours, priority, order_index, depends_on_index)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");

                foreach ($items as $index => $item) {
                    $itemStmt->execute([
                        $templateId,
                        $item['task_name'],
                        $item['description'] ?? '',
                        $item['estimated_hours'] ?? null,
                        $item['priority'] ?? 'medium',
                        $index,
                        $item['depends_on_index'] ?? null
                    ]);
                }

                $db->commit();

                echo json_encode([
                    'success' => true,
                    'message' => 'Template created successfully',
                    'template_id' => $templateId
                ]);
                break;

            case 'instantiate':
                $templateId = intval($input['template_id'] ?? 0);
                $projectId = intval($input['project_id'] ?? 0);
                $assignedTo = intval($input['assigned_to'] ?? $currentUser['id']);

                if (!$templateId || !$projectId) {
                    throw new Exception('Template ID and Project ID are required');
                }

                // Verify template access
                $templateCheck = $db->prepare("
                    SELECT id FROM task_templates
                    WHERE id = ? AND (created_by = ? OR is_public = 1)
                ");
                $templateCheck->execute([$templateId, $currentUser['id']]);
                if (!$templateCheck->fetch()) {
                    throw new Exception('Template not found or access denied');
                }

                // Get template items
                $itemsStmt = $db->prepare("
                    SELECT *
                    FROM task_template_items
                    WHERE template_id = ?
                    ORDER BY order_index ASC
                ");
                $itemsStmt->execute([$templateId]);
                $items = $itemsStmt->fetchAll();

                // Create tasks from template
                $taskStmt = $db->prepare("
                    INSERT INTO tasks
                    (task_name, description, project_id, assigned_to, assigned_manager, status, priority, estimated_hours, created_at)
                    VALUES (?, ?, ?, ?, ?, 'todo', ?, ?, NOW())
                ");

                $createdTasks = [];
                $taskIdMapping = []; // Map template item index to actual task ID

                foreach ($items as $item) {
                    $taskStmt->execute([
                        $item['task_name'],
                        $item['description'],
                        $projectId,
                        $assignedTo,
                        $currentUser['id'],
                        $item['priority'],
                        $item['estimated_hours']
                    ]);

                    $taskId = $db->lastInsertId();
                    $taskIdMapping[$item['order_index']] = $taskId;
                    $createdTasks[] = $taskId;
                }

                // Add dependencies
                foreach ($items as $item) {
                    if ($item['depends_on_index'] !== null && isset($taskIdMapping[$item['depends_on_index']])) {
                        $dependsOnTaskId = $taskIdMapping[$item['depends_on_index']];
                        $currentTaskId = $taskIdMapping[$item['order_index']];

                        // Add to task_dependencies table if it exists
                        try {
                            $depStmt = $db->prepare("
                                INSERT INTO task_dependencies (task_id, depends_on_task_id)
                                VALUES (?, ?)
                            ");
                            $depStmt->execute([$currentTaskId, $dependsOnTaskId]);
                        } catch (Exception $e) {
                            // Table might not exist yet, skip dependencies
                        }
                    }
                }

                $db->commit();

                echo json_encode([
                    'success' => true,
                    'message' => count($createdTasks) . ' tasks created from template',
                    'task_ids' => $createdTasks
                ]);
                break;

            case 'delete':
                $templateId = intval($input['template_id'] ?? 0);

                if (!$templateId) {
                    throw new Exception('Template ID is required');
                }

                // Verify ownership
                $check = $db->prepare("SELECT id FROM task_templates WHERE id = ? AND created_by = ?");
                $check->execute([$templateId, $currentUser['id']]);
                if (!$check->fetch()) {
                    throw new Exception('Template not found or access denied');
                }

                // Delete template (cascade will delete items)
                $stmt = $db->prepare("DELETE FROM task_templates WHERE id = ?");
                $stmt->execute([$templateId]);

                $db->commit();

                echo json_encode([
                    'success' => true,
                    'message' => 'Template deleted successfully'
                ]);
                break;

            default:
                throw new Exception('Invalid action');
        }
    } catch (Exception $e) {
        $db->rollBack();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
