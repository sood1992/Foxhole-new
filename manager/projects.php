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
    <title>My Projects - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/ultra-premium.css">
    <?php include '../includes/quick-actions-assets.php'; ?>
</head>
<body>
    <div class="dashboard">
        <?php include '../includes/manager-sidebar.php'; ?>

        <main class="main-content">
            <div class="topbar">
                <h1>📁 My Projects</h1>
                <div class="topbar-actions">
                    <?php include '../includes/global-search-assets.php'; ?>
                    <?php include '../includes/notifications-dropdown.php'; ?>
                </div>
            </div>

            <div class="content">
                <div class="card">
                    <div class="card-header">
                        <h3>Projects Overview</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($projects)): ?>
                            <p style="text-align: center; color: var(--text-secondary); padding: var(--space-10);">
                                No projects assigned to you yet.
                            </p>
                        <?php else: ?>
                            <table class="table">
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
        </main>
    </div>

    <script src="../assets/js/theme.js"></script>
</body>
</html>
