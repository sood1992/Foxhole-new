<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !hasRole('manager')) {
    redirect('../login.php');
}

$db = getDBConnection();
$currentUser = getCurrentUser();

// Get all tasks for manager's projects
$stmt = $db->prepare("
    SELECT t.*, p.project_name, u.full_name as assigned_to_name
    FROM tasks t
    JOIN projects p ON t.project_id = p.id
    LEFT JOIN users u ON t.assigned_to = u.id
    WHERE p.assigned_manager = ?
    ORDER BY
        FIELD(t.status, 'blocked', 'in_progress', 'review', 'todo', 'completed'),
        t.priority = 'urgent' DESC,
        t.priority = 'high' DESC,
        t.due_date ASC
");
$stmt->execute([$currentUser['id']]);
$tasks = $stmt->fetchAll();

// Group by status
$tasksByStatus = [
    'todo' => [],
    'in_progress' => [],
    'review' => [],
    'blocked' => [],
    'completed' => []
];

foreach ($tasks as $task) {
    if (isset($tasksByStatus[$task['status']])) {
        $tasksByStatus[$task['status']][] = $task;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tasks V3 - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/vien-v3.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include '../includes/quick-actions-assets.php'; ?>
</head>
<body>
    <div class="app-container">
        <?php include '../includes/v3-manager-sidebar.php'; ?>

        <div class="main-content">
            <?php include '../includes/v3-header.php'; ?>

            <div class="content-wrapper">
                <!-- Task Stats -->
                <div class="stats-grid" style="margin-bottom: var(--space-6);">
                    <div class="stat-card orange">
                        <div class="stat-card-header">
                            <div>
                                <div class="stat-label">To Do</div>
                                <div class="stat-value"><?php echo count($tasksByStatus['todo']); ?></div>
                            </div>
                            <div class="stat-icon">📝</div>
                        </div>
                    </div>
                    <div class="stat-card blue">
                        <div class="stat-card-header">
                            <div>
                                <div class="stat-label">In Progress</div>
                                <div class="stat-value"><?php echo count($tasksByStatus['in_progress']); ?></div>
                            </div>
                            <div class="stat-icon">🔄</div>
                        </div>
                    </div>
                    <div class="stat-card purple">
                        <div class="stat-card-header">
                            <div>
                                <div class="stat-label">In Review</div>
                                <div class="stat-value"><?php echo count($tasksByStatus['review']); ?></div>
                            </div>
                            <div class="stat-icon">👀</div>
                        </div>
                    </div>
                    <div class="stat-card green">
                        <div class="stat-card-header">
                            <div>
                                <div class="stat-label">Completed</div>
                                <div class="stat-value"><?php echo count($tasksByStatus['completed']); ?></div>
                            </div>
                            <div class="stat-icon">✅</div>
                        </div>
                    </div>
                </div>

                <!-- All Tasks Table -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3>All Tasks</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($tasks)): ?>
                            <p style="text-align: center; color: var(--text-secondary); padding: var(--space-10);">
                                No tasks found
                            </p>
                        <?php else: ?>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Task</th>
                                        <th>Project</th>
                                        <th>Assigned To</th>
                                        <th>Priority</th>
                                        <th>Status</th>
                                        <th>Due Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tasks as $task): ?>
                                    <tr class="<?php echo isOverdue($task['due_date'], $task['status']) ? 'overdue' : ''; ?>">
                                        <td>
                                            <strong><?php echo e($task['task_name']); ?></strong>
                                            <?php if ($task['description']): ?>
                                                <br><small style="color: var(--text-secondary);">
                                                    <?php echo e(substr($task['description'], 0, 50)) . (strlen($task['description']) > 50 ? '...' : ''); ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo e($task['project_name']); ?></td>
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
                                                    <br><span style="color: var(--status-blocked); font-size: var(--font-xs);">⚠️ Overdue</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span style="color: var(--text-tertiary);">No due date</span>
                                            <?php endif; ?>
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
</body>
</html>
