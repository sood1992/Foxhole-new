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

// Get project ID
$projectId = intval($_GET['id'] ?? 0);

// Get project details
$projectStmt = $db->prepare("
    SELECT *
    FROM projects
    WHERE id = ?
");
$projectStmt->execute([$projectId]);
$project = $projectStmt->fetch();

// Check if project exists and manager owns it
if (!$project) {
    $_SESSION['error_message'] = 'Project not found.';
    header("Location: projects.php");
    exit();
}

if ($project['assigned_manager'] != $currentUser['id']) {
    $_SESSION['error_message'] = 'You do not have permission to edit this project.';
    header("Location: projects.php");
    exit();
}

// Get all employees for assignment
$employees = $db->query("
    SELECT id, full_name, job_title, email
    FROM users
    WHERE role IN ('employee', 'manager') AND is_active = 1
    ORDER BY full_name
")->fetchAll();

// Get currently assigned users (those with tasks in this project)
$assignedUsersStmt = $db->prepare("
    SELECT DISTINCT assigned_to
    FROM tasks
    WHERE project_id = ?
");
$assignedUsersStmt->execute([$projectId]);
$assignedUsers = $assignedUsersStmt->fetchAll(PDO::FETCH_COLUMN);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $project_name = trim($_POST['project_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $client_name = trim($_POST['client_name'] ?? '');
        $status = $_POST['status'] ?? 'planning';
        $priority = $_POST['priority'] ?? 'medium';
        $start_date = $_POST['start_date'] ?? null;
        $due_date = $_POST['due_date'] ?? null;
        $estimated_hours = floatval($_POST['estimated_hours'] ?? 0);
        $assigned_users_new = $_POST['assigned_users'] ?? [];

        // Validation
        if (empty($project_name)) {
            $error = 'Project name is required.';
        } else {
            // Update project
            $updateStmt = $db->prepare("
                UPDATE projects SET
                    project_name = ?,
                    description = ?,
                    client_name = ?,
                    status = ?,
                    priority = ?,
                    start_date = ?,
                    due_date = ?,
                    estimated_hours = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");

            $result = $updateStmt->execute([
                $project_name,
                $description,
                $client_name,
                $status,
                $priority,
                !empty($start_date) ? $start_date : null,
                !empty($due_date) ? $due_date : null,
                $estimated_hours > 0 ? $estimated_hours : null,
                $projectId
            ]);

            if ($result) {
                // Handle team member changes
                // Find users to add (in new list but not in old list)
                $usersToAdd = array_diff($assigned_users_new, $assignedUsers);

                // Create tasks for new users
                if (!empty($usersToAdd)) {
                    foreach ($usersToAdd as $userId) {
                        $taskStmt = $db->prepare("
                            INSERT INTO tasks (
                                project_id, task_name, description, assigned_to,
                                status, priority, created_by, created_at
                            ) VALUES (?, ?, ?, ?, 'todo', ?, ?, NOW())
                        ");

                        $user = array_filter($employees, fn($e) => $e['id'] == $userId);
                        $user = reset($user);
                        $userName = $user['full_name'] ?? 'Team Member';

                        $taskStmt->execute([
                            $projectId,
                            "Work on {$project_name}",
                            "Task assigned for {$userName} on this project",
                            $userId,
                            $priority,
                            $currentUser['id']
                        ]);
                    }
                }

                ob_end_clean();
                $_SESSION['success_message'] = 'Project updated successfully!';
                header("Location: projects.php");
                exit();
            } else {
                $errorInfo = $updateStmt->errorInfo();
                $error = 'Failed to update project: ' . ($errorInfo[2] ?? 'Unknown database error');
            }
        }
    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
        error_log("Project update error: " . $e->getMessage());
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
        error_log("Project update error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Project V3 - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/vien-v3.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .user-select-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 12px;
            margin-top: 12px;
        }

        .user-checkbox {
            display: flex;
            align-items: center;
            padding: 12px;
            background: var(--bg-tertiary);
            border: 2px solid var(--border);
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .user-checkbox:hover {
            border-color: #667eea;
            background: var(--bg-secondary);
        }

        .user-checkbox input[type="checkbox"] {
            width: 20px;
            height: 20px;
            margin-right: 12px;
            cursor: pointer;
        }

        .user-checkbox input[type="checkbox"]:checked + .user-info {
            color: #667eea;
            font-weight: 600;
        }

        .user-info {
            flex: 1;
        }

        .user-name {
            font-weight: 500;
            margin-bottom: 2px;
        }

        .user-title {
            font-size: 12px;
            color: var(--text-secondary);
        }
    </style>
</head>
<body>
    <?php include '../includes/v3-manager-sidebar.php'; ?>

    <div class="app-container">
        <?php include '../includes/v3-header.php'; ?>

        <div class="main-content">
            <div class="content-wrapper">
                <div class="page-header">
                    <div>
                        <h1><i class="fas fa-edit"></i> Edit Project</h1>
                        <p class="page-subtitle">Update project details and manage team assignments</p>
                    </div>
                    <div class="page-actions">
                        <a href="projects.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Projects</a>
                    </div>
                </div>

                <div class="dashboard-card" style="max-width: 1000px; margin: 0 auto;">
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-error" style="margin-bottom: 24px;">
                                <?php echo e($error); ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="" style="display: grid; gap: 24px;">
                            <!-- Project Name -->
                            <div class="form-group">
                                <label for="project_name">Project Name <span style="color: var(--danger);">*</span></label>
                                <input type="text" id="project_name" name="project_name" class="form-control" required
                                       value="<?php echo e($project['project_name']); ?>"
                                       placeholder="e.g., Website Redesign 2024">
                            </div>

                            <!-- Description -->
                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea id="description" name="description" class="form-control" rows="4"
                                          placeholder="Describe the project goals and deliverables..."><?php echo e($project['description']); ?></textarea>
                            </div>

                            <!-- Client Name -->
                            <div class="form-group">
                                <label for="client_name">Client Name</label>
                                <input type="text" id="client_name" name="client_name" class="form-control"
                                       value="<?php echo e($project['client_name']); ?>"
                                       placeholder="e.g., Acme Corporation">
                            </div>

                            <!-- Status and Priority -->
                            <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div class="form-group">
                                    <label for="status">Status</label>
                                    <select id="status" name="status" class="form-control">
                                        <option value="planning" <?php echo $project['status'] == 'planning' ? 'selected' : ''; ?>>Planning</option>
                                        <option value="in_progress" <?php echo $project['status'] == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                        <option value="review" <?php echo $project['status'] == 'review' ? 'selected' : ''; ?>>Review</option>
                                        <option value="on_hold" <?php echo $project['status'] == 'on_hold' ? 'selected' : ''; ?>>On Hold</option>
                                        <option value="completed" <?php echo $project['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="priority">Priority</label>
                                    <select id="priority" name="priority" class="form-control">
                                        <option value="low" <?php echo $project['priority'] == 'low' ? 'selected' : ''; ?>>Low</option>
                                        <option value="medium" <?php echo $project['priority'] == 'medium' ? 'selected' : ''; ?>>Medium</option>
                                        <option value="high" <?php echo $project['priority'] == 'high' ? 'selected' : ''; ?>>High</option>
                                        <option value="urgent" <?php echo $project['priority'] == 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Dates -->
                            <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px;">
                                <div class="form-group">
                                    <label for="start_date">Start Date</label>
                                    <input type="date" id="start_date" name="start_date" class="form-control"
                                           value="<?php echo $project['start_date'] ?? ''; ?>">
                                </div>

                                <div class="form-group">
                                    <label for="due_date">Due Date</label>
                                    <input type="date" id="due_date" name="due_date" class="form-control"
                                           value="<?php echo $project['due_date'] ?? ''; ?>"
                                           min="<?php echo date('Y-m-d'); ?>">
                                </div>

                                <div class="form-group">
                                    <label for="estimated_hours">Estimated Hours</label>
                                    <input type="number" id="estimated_hours" name="estimated_hours" class="form-control"
                                           step="0.5" min="0" placeholder="e.g., 40"
                                           value="<?php echo $project['estimated_hours'] ?? ''; ?>">
                                </div>
                            </div>

                            <!-- Team Assignment -->
                            <div class="form-group">
                                <label>Assign Team Members (Multi-Select)</label>
                                <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 12px;">
                                    Select team members to work on this project. New assignments will create initial tasks.
                                </p>
                                <div class="user-select-grid">
                                    <?php foreach ($employees as $employee): ?>
                                        <label class="user-checkbox">
                                            <input type="checkbox" name="assigned_users[]" value="<?php echo $employee['id']; ?>"
                                                <?php echo in_array($employee['id'], $assignedUsers) ? 'checked' : ''; ?>>
                                            <div class="user-info">
                                                <div class="user-name"><?php echo e($employee['full_name']); ?></div>
                                                <div class="user-title"><?php echo e($employee['job_title'] ?? 'Team Member'); ?></div>
                                            </div>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <p style="font-size: 12px; color: var(--text-secondary); margin-top: 12px;">
                                    <i class="fas fa-info-circle"></i> Note: Adding new members will create initial tasks. Unchecking members won't delete their existing tasks.
                                </p>
                            </div>

                            <!-- Submit -->
                            <div style="border-top: 2px solid var(--border); padding-top: 24px; display: flex; gap: 12px; justify-content: flex-end;">
                                <a href="projects.php" class="btn btn-secondary">Cancel</a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Project
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/theme.js"></script>
</body>
</html>
