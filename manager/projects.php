<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !hasRole('manager')) {
    redirect('../login.php');
}

$db = getDBConnection();
$currentUser = getCurrentUser();

// Get manager's projects
$stmt = $db->prepare("
    SELECT p.*,
           (SELECT COUNT(*) FROM tasks WHERE project_id = p.id) as task_count,
           (SELECT COUNT(*) FROM tasks WHERE project_id = p.id AND status = 'completed') as completed_tasks
    FROM projects p
    WHERE p.assigned_manager = ?
    ORDER BY
        FIELD(p.status, 'in_progress', 'review', 'planning', 'on_hold', 'completed'),
        p.due_date ASC
");
$stmt->execute([$currentUser['id']]);
$projects = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Projects V3 - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/vien-v3.css">

    <!-- Synto Dashboard Template Design -->
    <link rel="stylesheet" href="../assets/css/synto-design.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include '../includes/quick-actions-assets.php'; ?>
</head>
<body>
    <div class="app-container">
        <?php include '../includes/v3-manager-sidebar.php'; ?>

        <div class="main-content">
            <?php include '../includes/v3-header.php'; ?>

            <div class="content-wrapper">
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3>Projects Overview</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($projects)): ?>
                            <p style="text-align: center; color: var(--text-secondary); padding: var(--space-10);">
                                No projects assigned to you yet.
                            </p>
                        <?php else: ?>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Project Name</th>
                                        <th>Client</th>
                                        <th>Status</th>
                                        <th>Priority</th>
                                        <th>Progress</th>
                                        <th>Due Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($projects as $project): ?>
                                    <tr>
                                        <td><strong><?php echo e($project['project_name']); ?></strong></td>
                                        <td><?php echo e($project['client_name'] ?? 'N/A'); ?></td>
                                        <td>
                                            <span class="badge <?php echo getStatusClass($project['status']); ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $project['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo getPriorityClass($project['priority']); ?>">
                                                <?php echo ucfirst($project['priority']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $completion = $project['task_count'] > 0 ?
                                                round(($project['completed_tasks'] / $project['task_count']) * 100) : 0;
                                            ?>
                                            <div class="progress-bar-container">
                                                <div class="progress-bar" style="width: <?php echo $completion; ?>%"></div>
                                            </div>
                                            <small style="color: var(--text-secondary);">
                                                <?php echo $completion; ?>% (<?php echo $project['completed_tasks']; ?>/<?php echo $project['task_count']; ?>)
                                            </small>
                                        </td>
                                        <td>
                                            <?php if ($project['due_date']): ?>
                                                <?php echo date('M d, Y', strtotime($project['due_date'])); ?>
                                                <?php if (isOverdue($project['due_date'], $project['status'])): ?>
                                                    <br><span style="color: var(--status-blocked);">⚠️ Overdue</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span style="color: var(--text-tertiary);">No deadline</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="../admin/project-detail.php?id=<?php echo $project['id']; ?>" class="btn btn-primary btn-sm">
                                                View Details
                                            </a>
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
