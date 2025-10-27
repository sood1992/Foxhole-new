<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !hasRole('manager')) {
    redirect('../login.php');
}

$db = getDBConnection();
$currentUser = getCurrentUser();

// Get team members working on manager's projects
$stmt = $db->prepare("
    SELECT
        u.id,
        u.full_name,
        u.email,
        u.job_title,
        COUNT(DISTINCT t.id) as total_tasks,
        SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
        SUM(CASE WHEN t.status = 'in_progress' THEN 1 ELSE 0 END) as active_tasks,
        (SELECT SUM(duration_minutes) FROM time_logs WHERE user_id = u.id AND project_id IN (SELECT id FROM projects WHERE assigned_manager = ?)) as total_minutes
    FROM users u
    LEFT JOIN tasks t ON u.id = t.assigned_to
    LEFT JOIN projects p ON t.project_id = p.id
    WHERE p.assigned_manager = ?
        AND u.role IN ('employee', 'manager')
        AND u.is_active = 1
    GROUP BY u.id
    ORDER BY u.full_name
");
$stmt->execute([$currentUser['id'], $currentUser['id']]);
$teamMembers = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Team - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/ultra-premium.css">
    <?php include '../includes/quick-actions-assets.php'; ?>
</head>
<body>
    <div class="dashboard">
        <?php include '../includes/manager-sidebar.php'; ?>

        <main class="main-content">
            <div class="topbar">
                <h1>👥 My Team</h1>
                <div class="topbar-actions">
                    <?php include '../includes/global-search-assets.php'; ?>
                    <?php include '../includes/notifications-dropdown.php'; ?>
                </div>
            </div>

            <div class="content">
                <div class="card">
                    <div class="card-header">
                        <h3>Team Members (<?php echo count($teamMembers); ?>)</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($teamMembers)): ?>
                            <p style="text-align: center; color: var(--text-secondary); padding: var(--space-10);">
                                No team members assigned to your projects yet.
                            </p>
                        <?php else: ?>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Job Title</th>
                                        <th>Total Tasks</th>
                                        <th>Completed</th>
                                        <th>Active</th>
                                        <th>Hours Logged</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($teamMembers as $member): ?>
                                    <tr>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: var(--space-3);">
                                                <div style="width: 40px; height: 40px; border-radius: var(--radius-md); background: var(--primary-gradient); display: flex; align-items: center; justify-content: center; color: white; font-weight: 700;">
                                                    <?php echo strtoupper(substr($member['full_name'], 0, 1)); ?>
                                                </div>
                                                <strong><?php echo e($member['full_name']); ?></strong>
                                            </div>
                                        </td>
                                        <td><?php echo e($member['email']); ?></td>
                                        <td><?php echo e($member['job_title'] ?? 'N/A'); ?></td>
                                        <td><?php echo $member['total_tasks']; ?></td>
                                        <td>
                                            <span class="badge status-completed">
                                                <?php echo $member['completed_tasks']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge status-progress">
                                                <?php echo $member['active_tasks']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo formatHours($member['total_minutes'] ?? 0); ?>h</td>
                                        <td>
                                            <a href="../admin/user-profile.php?id=<?php echo $member['id']; ?>" class="btn btn-primary btn-sm">
                                                View Profile
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
