<?php
// Start output buffering
ob_start();

require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/task-hooks.php';

if (!isLoggedIn() || !hasRole('manager')) {
    redirect('../login.php');
}

$db = getDBConnection();
$currentUser = getCurrentUser();
$error = '';
$success = '';

// Get task ID
$taskId = intval($_GET['id'] ?? 0);

// Get task details with project info
$taskStmt = $db->prepare("
    SELECT t.*, p.project_name, p.assigned_manager
    FROM tasks t
    JOIN projects p ON t.project_id = p.id
    WHERE t.id = ?
");
$taskStmt->execute([$taskId]);
$task = $taskStmt->fetch();

// Check if task exists
if (!$task) {
    $_SESSION['error_message'] = 'Task not found.';
    header("Location: tasks.php");
    exit();
}

// Check if current user is one of the assigned managers or the primary manager
$isAssignedManager = $db->prepare("
    SELECT COUNT(*) FROM project_managers
    WHERE project_id = ? AND manager_id = ?
");
$isAssignedManager->execute([$task['project_id'], $currentUser['id']]);
$canEdit = $isAssignedManager->fetchColumn() > 0;

if (!$canEdit && $task['assigned_manager'] != $currentUser['id']) {
    $_SESSION['error_message'] = 'You do not have permission to edit this task.';
    header("Location: tasks.php");
    exit();
}

// Get PM's projects (including multi-manager assignments)
$myProjects = $db->prepare("
    SELECT DISTINCT p.id, p.project_name, p.client_name
    FROM projects p
    LEFT JOIN project_managers pm ON p.id = pm.project_id
    WHERE (p.assigned_manager = ? OR pm.manager_id = ?) AND p.status IN ('planning', 'in_progress', 'review')
    ORDER BY p.project_name
");
$myProjects->execute([$currentUser['id'], $currentUser['id']]);
$projects = $myProjects->fetchAll();

// Get employees for assignment
$employees = $db->query("
    SELECT id, full_name, job_title
    FROM users
    WHERE role IN ('employee', 'manager') AND is_active = 1
    ORDER BY full_name
")->fetchAll();

// Get tasks for the current project (for dependencies)
$tasksStmt = $db->prepare("
    SELECT id, task_name, status, assigned_to
    FROM tasks
    WHERE project_id = ? AND id != ?
    ORDER BY created_at DESC
");
$tasksStmt->execute([$task['project_id'], $taskId]);
$availableTasks = $tasksStmt->fetchAll();

// Get current dependency
$currentDependency = $db->prepare("
    SELECT depends_on_task_id, dependency_type
    FROM task_dependencies
    WHERE task_id = ?
    LIMIT 1
");
$currentDependency->execute([$taskId]);
$dependency = $currentDependency->fetch();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $project_id = intval($_POST['project_id'] ?? 0);
        $task_name = trim($_POST['task_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $assigned_to = intval($_POST['assigned_to'] ?? 0);
        $priority = $_POST['priority'] ?? 'medium';
        $status = $_POST['status'] ?? 'todo';
        $due_date = $_POST['due_date'] ?? null;
        $estimated_hours = floatval($_POST['estimated_hours'] ?? 0);
        $depends_on = intval($_POST['depends_on'] ?? 0);
        $dependency_type = $_POST['dependency_type'] ?? 'finish_to_start';

        // Validation
        if (empty($task_name)) {
            $error = 'Task name is required.';
        } elseif ($project_id === 0) {
            $error = 'Please select a project.';
        } elseif ($assigned_to === 0) {
            $error = 'Please assign the task to someone.';
        } else {
            // Store old status for gamification hook
            $oldStatus = $task['status'];
            $oldAssignedTo = $task['assigned_to'];

            // Update task
            $updateStmt = $db->prepare("
                UPDATE tasks SET
                    project_id = ?,
                    task_name = ?,
                    description = ?,
                    assigned_to = ?,
                    status = ?,
                    priority = ?,
                    estimated_hours = ?,
                    due_date = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");

            $result = $updateStmt->execute([
                $project_id,
                $task_name,
                $description,
                $assigned_to,
                $status,
                $priority,
                $estimated_hours > 0 ? $estimated_hours : null,
                !empty($due_date) ? $due_date : null,
                $taskId
            ]);

            if ($result) {
                // Trigger gamification hook if status changed
                if ($status !== $oldStatus) {
                    hookTaskStatusUpdate($taskId, $oldStatus, $status, $assigned_to);
                }
                // Update dependency
                // First, delete existing dependency
                $db->prepare("DELETE FROM task_dependencies WHERE task_id = ?")->execute([$taskId]);

                // Create new dependency if specified
                if ($depends_on > 0) {
                    $depStmt = $db->prepare("
                        INSERT INTO task_dependencies (task_id, depends_on_task_id, dependency_type, created_at)
                        VALUES (?, ?, ?, NOW())
                    ");
                    $depStmt->execute([$taskId, $depends_on, $dependency_type]);
                }

                ob_end_clean();
                $_SESSION['success_message'] = 'Task updated successfully!';
                header("Location: tasks.php");
                exit();
            } else {
                $errorInfo = $updateStmt->errorInfo();
                $error = 'Failed to update task: ' . ($errorInfo[2] ?? 'Unknown database error');
            }
        }
    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
        error_log("Task update error: " . $e->getMessage());
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
        error_log("Task update error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Task V3 - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/vien-v3.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .dependency-info {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            border: 1px solid rgba(102, 126, 234, 0.3);
            padding: 16px;
            border-radius: var(--radius-md);
            margin-top: 12px;
        }

        .workflow-example {
            background: var(--bg-tertiary);
            padding: 12px;
            border-radius: 8px;
            margin-top: 12px;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include '../includes/v3-manager-sidebar.php'; ?>

        <div class="main-content">
            <?php include '../includes/v3-header.php'; ?>

            <div class="content-wrapper">
                <div class="dashboard-card" style="max-width: 800px; margin: 0 auto;">
                    <div class="card-header">
                        <h3><i class="fas fa-edit"></i> Edit Task</h3>
                        <p style="color: var(--text-secondary); font-size: 14px; margin-top: 8px;">
                            Update task details, reassign, or change dependencies.
                        </p>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-error" style="margin-bottom: 24px;">
                                <?php echo e($error); ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="" style="display: grid; gap: 24px;" id="taskForm">
                            <!-- Project Selection -->
                            <div class="form-group">
                                <label for="project_id">Project <span style="color: var(--danger);">*</span></label>
                                <select id="project_id" name="project_id" class="form-control" required>
                                    <option value="">Select a project...</option>
                                    <?php foreach ($projects as $project): ?>
                                        <option value="<?php echo $project['id']; ?>" <?php echo $task['project_id'] == $project['id'] ? 'selected' : ''; ?>>
                                            <?php echo e($project['project_name']); ?>
                                            <?php if ($project['client_name']): ?>
                                                - <?php echo e($project['client_name']); ?>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Task Name -->
                            <div class="form-group">
                                <label for="task_name">Task Name <span style="color: var(--danger);">*</span></label>
                                <input type="text" id="task_name" name="task_name" class="form-control" required
                                       value="<?php echo e($task['task_name']); ?>"
                                       placeholder="e.g., Design homepage mockup">
                            </div>

                            <!-- Description -->
                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea id="description" name="description" class="form-control" rows="4"
                                          placeholder="Describe what needs to be done..."><?php echo e($task['description']); ?></textarea>
                            </div>

                            <!-- Assigned To and Priority -->
                            <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div class="form-group">
                                    <label for="assigned_to">Assign To <span style="color: var(--danger);">*</span></label>
                                    <select id="assigned_to" name="assigned_to" class="form-control" required>
                                        <option value="">Select team member...</option>
                                        <?php foreach ($employees as $employee): ?>
                                            <option value="<?php echo $employee['id']; ?>" <?php echo $task['assigned_to'] == $employee['id'] ? 'selected' : ''; ?>>
                                                <?php echo e($employee['full_name']); ?> - <?php echo e($employee['job_title'] ?? 'Team Member'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="priority">Priority</label>
                                    <select id="priority" name="priority" class="form-control">
                                        <option value="low" <?php echo $task['priority'] == 'low' ? 'selected' : ''; ?>>Low</option>
                                        <option value="medium" <?php echo $task['priority'] == 'medium' ? 'selected' : ''; ?>>Medium</option>
                                        <option value="high" <?php echo $task['priority'] == 'high' ? 'selected' : ''; ?>>High</option>
                                        <option value="urgent" <?php echo $task['priority'] == 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Status -->
                            <div class="form-group">
                                <label for="status">Status</label>
                                <select id="status" name="status" class="form-control">
                                    <option value="todo" <?php echo $task['status'] == 'todo' ? 'selected' : ''; ?>>To Do</option>
                                    <option value="in_progress" <?php echo $task['status'] == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                    <option value="review" <?php echo $task['status'] == 'review' ? 'selected' : ''; ?>>In Review</option>
                                    <option value="blocked" <?php echo $task['status'] == 'blocked' ? 'selected' : ''; ?>>Blocked</option>
                                    <option value="completed" <?php echo $task['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                </select>
                            </div>

                            <!-- Due Date and Estimated Hours -->
                            <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div class="form-group">
                                    <label for="due_date">Due Date</label>
                                    <input type="date" id="due_date" name="due_date" class="form-control"
                                           value="<?php echo $task['due_date'] ?? ''; ?>"
                                           min="<?php echo date('Y-m-d'); ?>">
                                </div>

                                <div class="form-group">
                                    <label for="estimated_hours">Estimated Hours</label>
                                    <input type="number" id="estimated_hours" name="estimated_hours" class="form-control"
                                           step="0.5" min="0" placeholder="e.g., 4.5"
                                           value="<?php echo $task['estimated_hours'] ?? ''; ?>">
                                </div>
                            </div>

                            <!-- Task Dependencies -->
                            <div class="form-group">
                                <label for="depends_on">Task Dependency (Optional)</label>
                                <select id="depends_on" name="depends_on" class="form-control">
                                    <option value="0">No dependency - This task can start immediately</option>
                                    <?php if (!empty($availableTasks)): ?>
                                        <?php foreach ($availableTasks as $availableTask): ?>
                                            <option value="<?php echo $availableTask['id']; ?>"
                                                <?php echo ($dependency && $dependency['depends_on_task_id'] == $availableTask['id']) ? 'selected' : ''; ?>>
                                                Depends on: <?php echo e($availableTask['task_name']); ?> (<?php echo $availableTask['status']; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                                <small style="color: var(--text-secondary); font-size: 12px; display: block; margin-top: 4px;">
                                    Select a task that must be completed before this one can start
                                </small>

                                <div id="dependencyOptions" style="<?php echo $dependency ? 'display: block;' : 'display: none;'; ?> margin-top: 12px;">
                                    <label for="dependency_type">Dependency Type</label>
                                    <select id="dependency_type" name="dependency_type" class="form-control">
                                        <option value="finish_to_start" <?php echo ($dependency && $dependency['dependency_type'] == 'finish_to_start') ? 'selected' : ''; ?>>
                                            Finish-to-Start (Default) - Predecessor must finish before this starts
                                        </option>
                                        <option value="start_to_start" <?php echo ($dependency && $dependency['dependency_type'] == 'start_to_start') ? 'selected' : ''; ?>>
                                            Start-to-Start - Both tasks start together
                                        </option>
                                        <option value="finish_to_finish" <?php echo ($dependency && $dependency['dependency_type'] == 'finish_to_finish') ? 'selected' : ''; ?>>
                                            Finish-to-Finish - Both tasks finish together
                                        </option>
                                    </select>
                                </div>

                                <div class="dependency-info">
                                    <strong>🔄 How Dependencies Work:</strong>
                                    <div class="workflow-example">
                                        <strong>Example Workflow:</strong><br>
                                        1. Creative Team: Design homepage (Task A)<br>
                                        2. Edit Team: Review & edit homepage (Task B - depends on A)<br>
                                        <br>
                                        When Task A is completed → Task B automatically becomes available to start!
                                    </div>
                                </div>
                            </div>

                            <!-- Submit -->
                            <div style="border-top: 2px solid var(--border); padding-top: 24px; display: flex; gap: 12px; justify-content: flex-end;">
                                <a href="tasks.php" class="btn btn-secondary">Cancel</a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Task
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Show dependency type dropdown when a dependency is selected
        document.getElementById('depends_on').addEventListener('change', function() {
            const dependencyOptions = document.getElementById('dependencyOptions');
            if (this.value !== '0') {
                dependencyOptions.style.display = 'block';
            } else {
                dependencyOptions.style.display = 'none';
            }
        });
    </script>
    <script src="../assets/js/theme.js"></script>
</body>
</html>
