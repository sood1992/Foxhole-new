<?php
// Start output buffering
ob_start();

require_once '../config/config.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !hasRole('manager')) {
    redirect('../login.php');
}

$db = getDBConnection();
$currentUser = getCurrentUser();
$error = '';
$success = '';

// Get PM's projects
$myProjects = $db->prepare("
    SELECT id, project_name, client_name
    FROM projects
    WHERE assigned_manager = ? AND status IN ('planning', 'in_progress', 'review')
    ORDER BY project_name
");
$myProjects->execute([$currentUser['id']]);
$projects = $myProjects->fetchAll();

// Get employees for assignment
$employees = $db->query("
    SELECT id, full_name, job_title
    FROM users
    WHERE role IN ('employee', 'manager') AND is_active = 1
    ORDER BY full_name
")->fetchAll();

// Get tasks for selected project (via AJAX or on form change)
$projectId = $_GET['project_id'] ?? ($_POST['project_id'] ?? null);
$availableTasks = [];
if ($projectId) {
    $tasksStmt = $db->prepare("
        SELECT id, task_name, status, assigned_to
        FROM tasks
        WHERE project_id = ?
        ORDER BY created_at DESC
    ");
    $tasksStmt->execute([$projectId]);
    $availableTasks = $tasksStmt->fetchAll();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $project_id = intval($_POST['project_id'] ?? 0);
        $task_name = trim($_POST['task_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $assigned_to = intval($_POST['assigned_to'] ?? 0);
        $priority = $_POST['priority'] ?? 'medium';
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
            // Insert task
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
                $assigned_to,
                $priority,
                $estimated_hours > 0 ? $estimated_hours : null,
                !empty($due_date) ? $due_date : null,
                $currentUser['id']
            ]);

            if ($result) {
                $taskId = $db->lastInsertId();

                // Create dependency if specified
                if ($depends_on > 0) {
                    $depStmt = $db->prepare("
                        INSERT INTO task_dependencies (task_id, depends_on_task_id, dependency_type, created_at)
                        VALUES (?, ?, ?, NOW())
                    ");
                    $depStmt->execute([$taskId, $depends_on, $dependency_type]);
                }

                ob_end_clean();
                $_SESSION['success_message'] = 'Task created successfully' . ($depends_on > 0 ? ' with dependency!' : '!');
                header("Location: tasks.php");
                exit();
            } else {
                $errorInfo = $insertStmt->errorInfo();
                $error = 'Failed to create task: ' . ($errorInfo[2] ?? 'Unknown database error');
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
    <link rel="stylesheet" href="../assets/css/ultra-premium.css">
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
    <div class="dashboard">
        <?php include '../includes/manager-sidebar.php'; ?>

        <main class="main-content">
            <div class="topbar">
                <h1>📝 Create New Task</h1>
                <div class="topbar-actions">
                    <a href="tasks.php" class="btn btn-secondary btn-sm">← Back to Tasks</a>
                </div>
            </div>

            <div class="content">
                <div class="card" style="max-width: 800px; margin: 0 auto;">
                    <div class="card-header">
                        <h3>Task Details</h3>
                        <p style="color: var(--text-secondary); font-size: 14px; margin-top: 8px;">
                            Create a task and assign it to a team member. Optionally set dependencies for workflow automation.
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
                                You need to create a project first before you can add tasks.
                            </div>
                            <a href="create-project.php" class="btn btn-primary">+ Create Project</a>
                        <?php else: ?>

                        <form method="POST" action="" style="display: grid; gap: 24px;" id="taskForm">
                            <!-- Project Selection -->
                            <div class="form-group">
                                <label for="project_id">Project <span style="color: var(--danger);">*</span></label>
                                <select id="project_id" name="project_id" required onchange="loadProjectTasks()">
                                    <option value="">Select a project...</option>
                                    <?php foreach ($projects as $project): ?>
                                        <option value="<?php echo $project['id']; ?>" <?php echo $projectId == $project['id'] ? 'selected' : ''; ?>>
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
                                <input type="text" id="task_name" name="task_name" required
                                       placeholder="e.g., Design homepage mockup">
                            </div>

                            <!-- Description -->
                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea id="description" name="description" rows="4"
                                          placeholder="Describe what needs to be done..."></textarea>
                            </div>

                            <!-- Assigned To and Priority -->
                            <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div class="form-group">
                                    <label for="assigned_to">Assign To <span style="color: var(--danger);">*</span></label>
                                    <select id="assigned_to" name="assigned_to" required>
                                        <option value="">Select team member...</option>
                                        <?php foreach ($employees as $employee): ?>
                                            <option value="<?php echo $employee['id']; ?>">
                                                <?php echo e($employee['full_name']); ?> - <?php echo e($employee['job_title'] ?? 'Team Member'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="priority">Priority</label>
                                    <select id="priority" name="priority">
                                        <option value="low">Low</option>
                                        <option value="medium" selected>Medium</option>
                                        <option value="high">High</option>
                                        <option value="urgent">Urgent</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Due Date and Estimated Hours -->
                            <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div class="form-group">
                                    <label for="due_date">Due Date</label>
                                    <input type="date" id="due_date" name="due_date"
                                           min="<?php echo date('Y-m-d'); ?>">
                                </div>

                                <div class="form-group">
                                    <label for="estimated_hours">Estimated Hours</label>
                                    <input type="number" id="estimated_hours" name="estimated_hours"
                                           step="0.5" min="0" placeholder="e.g., 4.5">
                                </div>
                            </div>

                            <!-- Task Dependencies -->
                            <div class="form-group">
                                <label for="depends_on">Task Dependency (Optional)</label>
                                <select id="depends_on" name="depends_on">
                                    <option value="0">No dependency - This task can start immediately</option>
                                    <?php if (!empty($availableTasks)): ?>
                                        <?php foreach ($availableTasks as $task): ?>
                                            <option value="<?php echo $task['id']; ?>">
                                                Depends on: <?php echo e($task['task_name']); ?> (<?php echo $task['status']; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                                <small style="color: var(--text-secondary); font-size: 12px; display: block; margin-top: 4px;">
                                    Select a task that must be completed before this one can start
                                </small>

                                <div id="dependencyOptions" style="display: none; margin-top: 12px;">
                                    <label for="dependency_type">Dependency Type</label>
                                    <select id="dependency_type" name="dependency_type">
                                        <option value="finish_to_start">Finish-to-Start (Default) - Predecessor must finish before this starts</option>
                                        <option value="start_to_start">Start-to-Start - Both tasks start together</option>
                                        <option value="finish_to_finish">Finish-to-Finish - Both tasks finish together</option>
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

        // Reload page with project_id to load available tasks
        function loadProjectTasks() {
            const projectId = document.getElementById('project_id').value;
            if (projectId) {
                window.location.href = '?project_id=' + projectId;
            }
        }
    </script>
    <script src="../assets/js/theme.js"></script>
</body>
</html>
