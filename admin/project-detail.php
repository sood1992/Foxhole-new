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

    <!-- Synto Dashboard Template Design -->
    <link rel="stylesheet" href="../assets/css/synto-design.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include '../includes/quick-actions-assets.php'; ?>
</head>
<body>
    <div class="app-container">
        <?php include '../includes/v3-admin-sidebar.php'; ?>

        <div class="main-content">
            <?php include '../includes/v3-header.php'; ?>

            <div class="content-wrapper">
                <div class="page-header">
                    <div>
                        <a href="index.php" style="color: var(--text-secondary); text-decoration: none; font-size: var(--font-sm);"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
                        <h1><?php echo e($project['project_name']); ?></h1>
                    </div>
                </div>

                <!-- Project Overview Card -->
                <div class="card" style="margin-bottom: var(--space-6);">
                    <div class="card-body">
                        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: var(--space-8);">
                            <!-- Left: Project Info -->
                            <div>
                                <div style="display: flex; gap: var(--space-4); margin-bottom: var(--space-6);">
                                    <span class="badge <?php echo getStatusClass($project['status']); ?>" style="font-size: var(--font-base); padding: var(--space-2) var(--space-4);">
                                        <?php echo ucfirst(str_replace('_', ' ', $project['status'])); ?>
                                    </span>
                                    <span class="badge <?php echo getPriorityClass($project['priority']); ?>" style="font-size: var(--font-base); padding: var(--space-2) var(--space-4);">
                                        <?php echo ucfirst($project['priority']); ?> Priority
                                    </span>
                                </div>

                                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: var(--space-4); margin-bottom: var(--space-6);">
                                    <div>
                                        <div style="font-size: var(--font-xs); color: var(--text-secondary); margin-bottom: var(--space-1);">Client</div>
                                        <div style="font-size: var(--font-lg); font-weight: 700; color: var(--text-primary);">
                                            <?php echo e($project['client_name'] ?? 'No client'); ?>
                                        </div>
                                    </div>
                                    <div>
                                        <div style="font-size: var(--font-xs); color: var(--text-secondary); margin-bottom: var(--space-1);">Project Manager</div>
                                        <div style="font-size: var(--font-lg); font-weight: 700; color: var(--text-primary);">
                                            <?php echo e($project['manager_name'] ?? 'Unassigned'); ?>
                                        </div>
                                    </div>
                                    <div>
                                        <div style="font-size: var(--font-xs); color: var(--text-secondary); margin-bottom: var(--space-1);">Start Date</div>
                                        <div style="font-size: var(--font-base); color: var(--text-primary);">
                                            <?php echo date('M d, Y', strtotime($project['start_date'])); ?>
                                        </div>
                                    </div>
                                    <div>
                                        <div style="font-size: var(--font-xs); color: var(--text-secondary); margin-bottom: var(--space-1);">Due Date</div>
                                        <div style="font-size: var(--font-base); color: var(--text-primary);">
                                            <?php echo date('M d, Y', strtotime($project['due_date'])); ?>
                                            <?php if (isOverdue($project['due_date'], $project['status'])): ?>
                                                <span style="color: var(--status-blocked); margin-left: var(--space-2);"><i class="fas fa-exclamation-triangle"></i> Overdue</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <?php if ($project['description']): ?>
                                <div>
                                    <div style="font-size: var(--font-sm); font-weight: 700; color: var(--text-primary); margin-bottom: var(--space-2);">Description</div>
                                    <div style="font-size: var(--font-sm); color: var(--text-secondary); line-height: 1.6;">
                                        <?php echo nl2br(e($project['description'])); ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Right: Stats -->
                            <div>
                                <div style="background: var(--bg-tertiary); border-radius: var(--radius-lg); padding: var(--space-6);">
                                    <h3 style="font-size: var(--font-lg); font-weight: 700; margin-bottom: var(--space-6); color: var(--text-primary);">
                                        Project Progress
                                    </h3>

                                    <div style="text-align: center; margin-bottom: var(--space-6);">
                                        <div style="font-size: 64px; font-weight: 900; background: var(--primary-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                                            <?php echo $completionRate; ?>%
                                        </div>
                                        <div style="font-size: var(--font-sm); color: var(--text-secondary);">Complete</div>
                                    </div>

                                    <div style="display: flex; flex-direction: column; gap: var(--space-3);">
                                        <div style="display: flex; justify-content: space-between;">
                                            <span style="color: var(--text-secondary);">Total Tasks</span>
                                            <span style="font-weight: 700; color: var(--text-primary);"><?php echo $taskStats['total_tasks']; ?></span>
                                        </div>
                                        <div style="display: flex; justify-content: space-between;">
                                            <span style="color: var(--text-secondary);">Completed</span>
                                            <span style="font-weight: 700; color: #10b981;"><?php echo $taskStats['completed']; ?></span>
                                        </div>
                                        <div style="display: flex; justify-content: space-between;">
                                            <span style="color: var(--text-secondary);">In Progress</span>
                                            <span style="font-weight: 700; color: #3b82f6;"><?php echo $taskStats['in_progress']; ?></span>
                                        </div>
                                        <?php if ($taskStats['overdue'] > 0): ?>
                                        <div style="display: flex; justify-content: space-between;">
                                            <span style="color: var(--text-secondary);">Overdue</span>
                                            <span style="font-weight: 700; color: #ef4444;"><?php echo $taskStats['overdue']; ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Budget Info -->
                                <?php if ($project['budget'] > 0): ?>
                                <div style="background: var(--bg-tertiary); border-radius: var(--radius-lg); padding: var(--space-6); margin-top: var(--space-4);">
                                    <h3 style="font-size: var(--font-lg); font-weight: 700; margin-bottom: var(--space-4); color: var(--text-primary);">
                                        Budget
                                    </h3>
                                    <div style="display: flex; justify-content: space-between; margin-bottom: var(--space-3);">
                                        <span style="color: var(--text-secondary);">Total</span>
                                        <span style="font-weight: 700; color: var(--text-primary);">$<?php echo number_format($project['budget']); ?></span>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; margin-bottom: var(--space-3);">
                                        <span style="color: var(--text-secondary);">Used</span>
                                        <span style="font-weight: 700; color: <?php echo $budgetPercentage > 90 ? '#ef4444' : '#10b981'; ?>;">
                                            $<?php echo number_format($budgetUsed); ?>
                                        </span>
                                    </div>
                                    <div class="progress-bar-container" style="margin-top: var(--space-4);">
                                        <div class="progress-bar <?php echo $budgetPercentage > 90 ? 'overbudget' : 'high'; ?>" style="width: <?php echo min(100, $budgetPercentage); ?>%"></div>
                                    </div>
                                    <div style="font-size: var(--font-xs); color: var(--text-tertiary); margin-top: var(--space-2); text-align: center;">
                                        <?php echo $budgetPercentage; ?>% of budget used
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Team Members -->
                <?php if (!empty($teamMembers)): ?>
                <div class="card" style="margin-bottom: var(--space-6);">
                    <div class="card-header">
                        <h3><i class="fas ri-team-line"></i> Team Members (<?php echo count($teamMembers); ?>)</h3>
                    </div>
                    <div class="card-body">
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: var(--space-4);">
                            <?php foreach ($teamMembers as $member): ?>
                            <div style="padding: var(--space-4); background: var(--bg-tertiary); border-radius: var(--radius-lg); display: flex; align-items: center; gap: var(--space-3);">
                                <div style="width: 48px; height: 48px; border-radius: var(--radius-md); background: var(--primary-gradient); display: flex; align-items: center; justify-content: center; color: white; font-weight: 900; font-size: var(--font-lg);">
                                    <?php echo strtoupper(substr($member['full_name'], 0, 1)); ?>
                                </div>
                                <div style="flex: 1;">
                                    <div style="font-weight: 700; color: var(--text-primary); margin-bottom: var(--space-1);">
                                        <?php echo e($member['full_name']); ?>
                                    </div>
                                    <div style="font-size: var(--font-xs); color: var(--text-secondary);">
                                        <?php echo $member['task_count']; ?> tasks • <?php echo $member['completed_count']; ?> done
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- All Tasks -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas ri-check-line-square"></i> All Tasks (<?php echo $taskStats['total_tasks']; ?>)</h3>
                        <a href="tasks.php?project=<?php echo $projectId; ?>" class="btn btn-primary btn-sm">+ Add Task</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($tasks)): ?>
                            <p style="text-align: center; color: var(--text-secondary); padding: var(--space-10);">
                                No tasks created yet
                            </p>
                        <?php else: ?>
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
                                            <div class="progress-bar-container">
                                                <div class="progress-bar <?php echo $task['status'] == 'completed' ? 'complete' : 'medium'; ?>"
                                                     style="width: <?php echo calculateProgress($task['status']); ?>%"></div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/theme.js"></script>

    <!-- Synto Dashboard Interactions -->
    <script src="../assets/js/synto-interactions.js"></script>
</body>
</html>
