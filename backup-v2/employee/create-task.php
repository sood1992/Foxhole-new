<?php
// Start output buffering to prevent header issues
ob_start();

require_once '../config/config.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !hasRole('employee')) {
    redirect('../login.php');
}

$db = getDBConnection();
$currentUser = getCurrentUser();
$error = '';
$success = '';

// Get projects the employee is working on
$myProjects = $db->prepare("
    SELECT DISTINCT p.id, p.project_name, p.client_name
    FROM projects p
    JOIN tasks t ON p.id = t.project_id
    WHERE t.assigned_to = ? AND p.status IN ('planning', 'in_progress', 'review')
    ORDER BY p.project_name
");
$myProjects->execute([$currentUser['id']]);
$projects = $myProjects->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $project_id = intval($_POST['project_id'] ?? 0);
        $task_name = trim($_POST['task_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $priority = $_POST['priority'] ?? 'medium';
        $due_date = $_POST['due_date'] ?? null;
        $estimated_hours = floatval($_POST['estimated_hours'] ?? 0);

        // Validation
        if (empty($task_name)) {
            $error = 'Task name is required.';
        } elseif ($project_id === 0) {
            $error = 'Please select a project.';
        } else {
            // Verify the employee has access to this project
            $checkAccess = $db->prepare("
                SELECT COUNT(*) as count
                FROM tasks
                WHERE assigned_to = ? AND project_id = ?
            ");
            $checkAccess->execute([$currentUser['id'], $project_id]);
            $access = $checkAccess->fetch();

            if ($access['count'] == 0) {
                $error = 'You do not have access to this project.';
            } else {
                // Insert new task
                $insertStmt = $db->prepare("
                    INSERT INTO tasks (
                        project_id, task_name, description, assigned_to,
                        status, priority, estimated_hours, due_date,
                        created_by, created_at, start_date
                    ) VALUES (?, ?, ?, ?, 'todo', ?, ?, ?, ?, NOW(), CURDATE())
                ");

                $result = $insertStmt->execute([
                    $project_id,
                    $task_name,
                    $description,
                    $currentUser['id'], // Assign to self
                    $priority,
                    $estimated_hours > 0 ? $estimated_hours : null,
                    !empty($due_date) ? $due_date : null,
                    $currentUser['id'] // Created by
                ]);

                if ($result) {
                    ob_end_clean();
                    $_SESSION['success_message'] = 'Task created successfully!';
                    header("Location: tasks.php");
                    exit();
                } else {
                    $errorInfo = $insertStmt->errorInfo();
                    $error = 'Failed to create task: ' . ($errorInfo[2] ?? 'Unknown database error');
                }
            }
        }
    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
        error_log("Task creation error: " . $e->getMessage());
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
        error_log("Task creation error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Task - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/premium-theme.css">
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <?php include '../includes/employee-sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <div class="topbar">
                <h1>Create New Task</h1>
                <div class="topbar-actions">
                    <a href="tasks.php" class="btn btn-secondary btn-sm">← Back to Tasks</a>
                </div>
            </div>

            <div class="content">
                <div class="card" style="max-width: 800px; margin: 0 auto;">
                    <div class="card-header">
                        <h3>Add a Task You're Working On</h3>
                        <p style="color: var(--text-secondary); font-size: 14px; margin-top: 8px;">
                            Create tasks for work you're doing on your assigned projects. These will be visible to your managers and admins.
                        </p>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-error" style="margin-bottom: 24px;">
                                <?php echo e($error); ?>
                            </div>
                        <?php endif; ?>

                        <?php if (empty($projects)): ?>
                            <div class="alert alert-warning" style="margin-bottom: 24px;">
                                <strong>No Projects Available</strong><br>
                                You are not currently assigned to any active projects. Please contact your manager to be assigned to a project before creating tasks.
                            </div>
                            <a href="tasks.php" class="btn btn-secondary">← Back to Tasks</a>
                        <?php else: ?>

                        <form method="POST" action="" style="display: grid; gap: 24px;">
                            <div class="form-group">
                                <label for="project_id">Project <span style="color: var(--danger);">*</span></label>
                                <select id="project_id" name="project_id" required style="width: 100%; padding: 12px; border: 2px solid var(--border); border-radius: var(--radius-md); font-size: 14px;">
                                    <option value="">Select a project...</option>
                                    <?php foreach ($projects as $project): ?>
                                        <option value="<?php echo $project['id']; ?>">
                                            <?php echo e($project['project_name']); ?>
                                            <?php if ($project['client_name']): ?>
                                                - <?php echo e($project['client_name']); ?>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small style="color: var(--text-secondary); font-size: 12px; margin-top: 4px; display: block;">
                                    Select the project this task belongs to
                                </small>
                            </div>

                            <div class="form-group">
                                <label for="task_name">Task Name <span style="color: var(--danger);">*</span></label>
                                <input type="text" id="task_name" name="task_name" required
                                       placeholder="e.g., Design homepage mockup"
                                       style="width: 100%; padding: 12px; border: 2px solid var(--border); border-radius: var(--radius-md); font-size: 14px;">
                            </div>

                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea id="description" name="description" rows="4"
                                          placeholder="Describe what needs to be done..."
                                          style="width: 100%; padding: 12px; border: 2px solid var(--border); border-radius: var(--radius-md); font-size: 14px; font-family: inherit;"></textarea>
                            </div>

                            <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div class="form-group">
                                    <label for="priority">Priority</label>
                                    <select id="priority" name="priority" style="width: 100%; padding: 12px; border: 2px solid var(--border); border-radius: var(--radius-md); font-size: 14px;">
                                        <option value="low">Low</option>
                                        <option value="medium" selected>Medium</option>
                                        <option value="high">High</option>
                                        <option value="urgent">Urgent</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="estimated_hours">Estimated Hours</label>
                                    <input type="number" id="estimated_hours" name="estimated_hours" step="0.5" min="0"
                                           placeholder="e.g., 4.5"
                                           style="width: 100%; padding: 12px; border: 2px solid var(--border); border-radius: var(--radius-md); font-size: 14px;">
                                    <small style="color: var(--text-secondary); font-size: 12px;">Optional</small>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="due_date">Due Date</label>
                                <input type="date" id="due_date" name="due_date"
                                       min="<?php echo date('Y-m-d'); ?>"
                                       style="width: 100%; padding: 12px; border: 2px solid var(--border); border-radius: var(--radius-md); font-size: 14px;">
                                <small style="color: var(--text-secondary); font-size: 12px;">Optional - Set a deadline for this task</small>
                            </div>

                            <div style="background: var(--bg-tertiary); padding: 16px; border-radius: var(--radius-md); border-left: 4px solid var(--primary);">
                                <strong style="display: block; margin-bottom: 8px;">📋 Note:</strong>
                                <ul style="margin: 0; padding-left: 20px; color: var(--text-secondary); font-size: 14px; line-height: 1.6;">
                                    <li>This task will be assigned to you automatically</li>
                                    <li>It will appear on your manager's and admin's dashboards</li>
                                    <li>You can track time and update status from your Tasks page</li>
                                </ul>
                            </div>

                            <div style="border-top: 2px solid var(--border); padding-top: 24px; display: flex; gap: 12px; justify-content: flex-end;">
                                <a href="tasks.php" class="btn btn-secondary">Cancel</a>
                                <button type="submit" class="btn btn-primary">
                                    ✓ Create Task
                                </button>
                            </div>
                        </form>

                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/theme.js"></script>
</body>
</html>
