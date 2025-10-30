<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || (!hasRole('admin') && !hasRole('manager'))) {
    redirect('../login.php');
}

$db = getDBConnection();
$currentUser = getCurrentUser();

// Get project ID from URL
$projectId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$projectId) {
    redirect('index.php');
}

// Get project details
$stmt = $db->prepare("
    SELECT p.*, u.full_name as manager_name, u.email as manager_email
    FROM projects p
    LEFT JOIN users u ON p.assigned_manager = u.id
    WHERE p.id = ?
");
$stmt->execute([$projectId]);
$project = $stmt->fetch();

if (!$project) {
    redirect('index.php');
}

// Get task statistics
$stmt = $db->prepare("
    SELECT
        COUNT(*) as total_tasks,
        SUM(CASE WHEN status = 'todo' THEN 1 ELSE 0 END) as todo,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
        SUM(CASE WHEN status = 'review' THEN 1 ELSE 0 END) as review,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'blocked' THEN 1 ELSE 0 END) as blocked,
        SUM(CASE WHEN status != 'completed' AND due_date < CURDATE() THEN 1 ELSE 0 END) as overdue
    FROM tasks
    WHERE project_id = ?
");
$stmt->execute([$projectId]);
$taskStats = $stmt->fetch();

// Get all tasks
$stmt = $db->prepare("
    SELECT t.*, u.full_name as assigned_to_name
    FROM tasks t
    LEFT JOIN users u ON t.assigned_to = u.id
    WHERE t.project_id = ?
    ORDER BY
        FIELD(t.status, 'blocked', 'in_progress', 'review', 'todo', 'completed'),
        t.priority = 'urgent' DESC,
        t.priority = 'high' DESC,
        t.due_date ASC
");
$stmt->execute([$projectId]);
$tasks = $stmt->fetchAll();

// Get team members
$stmt = $db->prepare("
    SELECT DISTINCT u.id, u.full_name, u.job_title, u.email,
           COUNT(DISTINCT t.id) as task_count,
           SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed_count
    FROM users u
    JOIN tasks t ON u.id = t.assigned_to
    WHERE t.project_id = ?
    GROUP BY u.id
    ORDER BY task_count DESC
");
$stmt->execute([$projectId]);
$teamMembers = $stmt->fetchAll();

// Get recent activity
$stmt = $db->prepare("
    SELECT t.task_name, t.status, t.updated_at, u.full_name
    FROM tasks t
    LEFT JOIN users u ON t.assigned_to = u.id
    WHERE t.project_id = ?
    ORDER BY t.updated_at DESC
    LIMIT 10
");
$stmt->execute([$projectId]);
$recentActivity = $stmt->fetchAll();

// Calculate completion percentage
$completionRate = $taskStats['total_tasks'] > 0 ?
    round(($taskStats['completed'] / $taskStats['total_tasks']) * 100) : 0;

// Calculate budget usage
$budgetUsed = $project['actual_hours'] * 50; // Assuming average rate of $50/hr
$budgetRemaining = max(0, $project['budget'] - $budgetUsed);
$budgetPercentage = $project['budget'] > 0 ? round(($budgetUsed / $project['budget']) * 100) : 0;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($project['project_name']); ?> V3 - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/vien-v3.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include '../includes/quick-actions-assets.php'; ?>
</head>
<body>
    <div class="app-container">
        <?php include '../includes/v3-admin-sidebar.php'; ?>

        <div class="main-content">
            <?php include '../includes/v3-header.php'; ?>

            <div class="content-wrapper">
                <!-- Page Title -->
                <div style="margin-bottom: 30px;">
                    <a href="projects.php" style="color: var(--text-secondary); text-decoration: none; font-size: 13px; display: inline-block; margin-bottom: 12px;">
                        <i class="fas fa-arrow-left"></i> Back to Projects
                    </a>
                    <h1 style="margin-bottom: 8px;"><?php echo e($project['project_name']); ?></h1>
                    <div style="display: flex; gap: 12px; align-items: center;">
                        <span class="badge <?php echo getStatusClass($project['status']); ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $project['status'])); ?>
                        </span>
                        <span class="badge <?php echo getPriorityClass($project['priority']); ?>">
                            <?php echo ucfirst($project['priority']); ?> Priority
                        </span>
                    </div>
                </div>

                <!-- Project Stats Cards -->
                <div class="row" style="margin-bottom: 30px;">
                    <div class="col-lg-3 col-md-6">
                        <div class="dashboard-card">
                            <div class="card-icon success">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="card-value"><?php echo $completionRate; ?>%</div>
                            <div class="card-label">Progress</div>
                            <div class="card-trend up"><?php echo $taskStats['completed']; ?>/<?php echo $taskStats['total_tasks']; ?> Tasks</div>
                        </div>
                    </div>

                    <div class="col-lg-3 col-md-6">
                        <div class="dashboard-card">
                            <div class="card-icon primary">
                                <i class="fas fa-tasks"></i>
                            </div>
                            <div class="card-value"><?php echo $taskStats['in_progress']; ?></div>
                            <div class="card-label">In Progress</div>
                            <div class="card-trend up">Active Tasks</div>
                        </div>
                    </div>

                    <div class="col-lg-3 col-md-6">
                        <div class="dashboard-card">
                            <div class="card-icon <?php echo $taskStats['overdue'] > 0 ? 'danger' : 'success'; ?>">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <div class="card-value"><?php echo $taskStats['overdue']; ?></div>
                            <div class="card-label">Overdue</div>
                            <div class="card-trend <?php echo $taskStats['overdue'] > 0 ? 'down' : 'up'; ?>">
                                <?php echo $taskStats['overdue'] > 0 ? 'Need Attention' : 'On Track'; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-3 col-md-6">
                        <div class="dashboard-card">
                            <div class="card-icon info">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="card-value"><?php echo count($teamMembers); ?></div>
                            <div class="card-label">Team Members</div>
                            <div class="card-trend up">Assigned</div>
                        </div>
                    </div>
                </div>

                <!-- Project Info & Details -->
                <div class="row" style="margin-bottom: 30px;">
                    <!-- Project Information -->
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h3 style="margin: 0;">Project Information</h3>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6" style="margin-bottom: 20px;">
                                        <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 4px;">Client</div>
                                        <div style="font-size: 16px; font-weight: 600; color: var(--heading-color);">
                                            <?php echo e($project['client_name'] ?? 'No client'); ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6" style="margin-bottom: 20px;">
                                        <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 4px;">Project Manager</div>
                                        <div style="font-size: 16px; font-weight: 600; color: var(--heading-color);">
                                            <?php echo e($project['manager_name'] ?? 'Unassigned'); ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6" style="margin-bottom: 20px;">
                                        <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 4px;">Start Date</div>
                                        <div style="font-size: 14px; color: var(--text-primary);">
                                            <?php echo date('M d, Y', strtotime($project['start_date'])); ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6" style="margin-bottom: 20px;">
                                        <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 4px;">Due Date</div>
                                        <div style="font-size: 14px; color: var(--text-primary);">
                                            <?php echo date('M d, Y', strtotime($project['due_date'])); ?>
                                            <?php if (isOverdue($project['due_date'], $project['status'])): ?>
                                                <span style="color: var(--danger); margin-left: 8px;"><i class="fas fa-exclamation-triangle"></i> Overdue</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <?php if ($project['description']): ?>
                                <div style="border-top: 1px solid var(--border-light); padding-top: 16px; margin-top: 8px;">
                                    <div style="font-size: 13px; font-weight: 600; color: var(--heading-color); margin-bottom: 8px;">Description</div>
                                    <div style="font-size: 14px; color: var(--text-secondary); line-height: 1.6;">
                                        <?php echo nl2br(e($project['description'])); ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Budget & Progress -->
                    <div class="col-lg-4">
                        <?php if ($project['budget'] > 0): ?>
                        <div class="card">
                            <div class="card-header">
                                <h3 style="margin: 0;">Budget</h3>
                            </div>
                            <div class="card-body">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 12px;">
                                    <span style="color: var(--text-secondary); font-size: 13px;">Total Budget</span>
                                    <span style="font-weight: 600; color: var(--heading-color); font-size: 14px;">$<?php echo number_format($project['budget']); ?></span>
                                </div>
                                <div style="display: flex; justify-content: space-between; margin-bottom: 16px;">
                                    <span style="color: var(--text-secondary); font-size: 13px;">Used</span>
                                    <span style="font-weight: 600; color: <?php echo $budgetPercentage > 90 ? 'var(--danger)' : 'var(--success)'; ?>; font-size: 14px;">
                                        $<?php echo number_format($budgetUsed); ?>
                                    </span>
                                </div>
                                <div style="height: 8px; background: var(--border-light); border-radius: 4px; overflow: hidden; margin-bottom: 8px;">
                                    <div style="width: <?php echo min(100, $budgetPercentage); ?>%; height: 100%; background: <?php echo $budgetPercentage > 90 ? 'linear-gradient(90deg, #ef4444 0%, #dc2626 100%)' : 'linear-gradient(90deg, #17b06b 0%, #14d48f 100%)'; ?>; transition: width 0.3s;"></div>
                                </div>
                                <div style="font-size: 12px; color: var(--text-secondary); text-align: center;">
                                    <?php echo $budgetPercentage; ?>% of budget used
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Team Members -->
                <?php if (!empty($teamMembers)): ?>
                <div class="card" style="margin-bottom: 30px;">
                    <div class="card-header">
                        <h3 style="margin: 0;"><i class="fas fa-users"></i> Team Members (<?php echo count($teamMembers); ?>)</h3>
                    </div>
                    <div class="card-body">
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 16px;">
                            <?php foreach ($teamMembers as $member): ?>
                            <div style="padding: 16px; background: var(--bg-secondary); border-radius: 8px; display: flex; align-items: center; gap: 12px;">
                                <div style="width: 48px; height: 48px; border-radius: 8px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 18px;">
                                    <?php echo strtoupper(substr($member['full_name'], 0, 1)); ?>
                                </div>
                                <div style="flex: 1;">
                                    <div style="font-weight: 600; color: var(--heading-color); margin-bottom: 4px;">
                                        <?php echo e($member['full_name']); ?>
                                    </div>
                                    <div style="font-size: 12px; color: var(--text-secondary);">
                                        <?php echo $member['task_count']; ?> tasks • <?php echo $member['completed_count']; ?> done
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Client Feedback Widget -->
                <?php include '../includes/client-feedback-widget.php'; ?>

                <!-- Budget Dashboard Widget -->
                <?php include '../includes/budget-dashboard-widget.php'; ?>

                <!-- All Tasks -->
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3 style="margin: 0;"><i class="fas fa-check-square"></i> All Tasks (<?php echo $taskStats['total_tasks']; ?>)</h3>
                        </div>
                        <a href="project-detail.php?id=<?php echo $projectId; ?>#add-task" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus"></i> Add Task
                        </a>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <?php if (empty($tasks)): ?>
                            <div style="text-align: center; padding: 60px 20px; color: var(--text-secondary);">
                                <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 16px; opacity: 0.3;"></i>
                                <p>No tasks created yet</p>
                                <a href="project-detail.php?id=<?php echo $projectId; ?>#add-task" class="btn btn-primary btn-sm" style="margin-top: 16px;">
                                    <i class="fas fa-plus"></i> Create First Task
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="data-table-container" style="border: none; box-shadow: none;">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Task</th>
                                        <th>Assigned To</th>
                                        <th>Priority</th>
                                        <th>Status</th>
                                        <th>Due Date</th>
                                        <th>Progress</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tasks as $task): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo e($task['task_name']); ?></strong>
                                            <?php if ($task['description']): ?>
                                                <br><small style="color: var(--text-secondary);">
                                                    <?php echo e(substr($task['description'], 0, 50)) . (strlen($task['description']) > 50 ? '...' : ''); ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo e($task['assigned_to_name'] ?? 'Unassigned'); ?></td>
                                        <td>
                                            <span class="badge <?php echo getPriorityClass($task['priority']); ?>">
                                                <?php echo ucfirst($task['priority']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo getStatusClass($task['status']); ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $task['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($task['due_date']): ?>
                                                <?php echo date('M d, Y', strtotime($task['due_date'])); ?>
                                                <?php if (isOverdue($task['due_date'], $task['status'])): ?>
                                                    <br><span style="color: var(--status-blocked); font-size: var(--font-xs);"><i class="fas fa-exclamation-triangle"></i> Overdue</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span style="color: var(--text-tertiary);">No due date</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 8px;">
                                                <div style="flex: 1; height: 6px; background: var(--border-light); border-radius: 3px; overflow: hidden;">
                                                    <div style="width: <?php echo calculateProgress($task['status']); ?>%; height: 100%; background: <?php echo $task['status'] == 'completed' ? 'linear-gradient(90deg, #17b06b 0%, #14d48f 100%)' : 'linear-gradient(90deg, #3b82f6 0%, #2563eb 100%)'; ?>; transition: width 0.3s;"></div>
                                                </div>
                                                <span style="font-size: 12px; font-weight: 600; color: var(--text-secondary); min-width: 35px;">
                                                    <?php echo calculateProgress($task['status']); ?>%
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/theme.js"></script>
</body>
</html>
