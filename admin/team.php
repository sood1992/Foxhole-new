<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !hasRole('admin')) {
    redirect('../login.php');
}

$db = getDBConnection();
$currentUser = getCurrentUser();

// Get all team members with their stats
$teamMembers = $db->query("
    SELECT
        u.*,
        COUNT(DISTINCT t.project_id) as projects,
        COUNT(DISTINCT t.id) as tasks,
        SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
        (SELECT SUM(duration_minutes) FROM time_logs WHERE user_id = u.id) as total_minutes
    FROM users u
    LEFT JOIN tasks t ON u.id = t.assigned_to
    WHERE u.role IN ('manager', 'employee')
    GROUP BY u.id
    ORDER BY u.full_name
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Management - <?php echo SITE_NAME; ?> V3</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/vien-v3.css">

    <!-- Synto Dashboard Template Design -->
    <link rel="stylesheet" href="../assets/css/synto-design.css">
</head>
<body>
    <div class="app-container">
        <?php include '../includes/v3-admin-sidebar.php'; ?>

        <div class="main-content">
            <?php include '../includes/v3-header.php'; ?>

            <div class="content-wrapper">
                <!-- Page Title -->
                <div style="margin-bottom: 30px;">
                    <h1 style="margin-bottom: 8px;">Team Management</h1>
                    <p style="color: var(--text-secondary); font-size: 14px; margin: 0;">
                        View and manage all team members and their performance
                    </p>
                </div>

                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success" style="margin-bottom: 20px;">
                        <i class="fas ri-checkbox-circle-line"></i>
                        <?php
                        echo e($_SESSION['success_message']);
                        unset($_SESSION['success_message']);
                        ?>
                    </div>
                <?php endif; ?>

                <!-- Team Statistics -->
                <div class="row" style="margin-bottom: 30px;">
                    <div class="col-lg-3 col-md-6">
                        <div class="dashboard-card">
                            <div class="card-icon primary">
                                <i class="fas ri-team-line"></i>
                            </div>
                            <div class="card-value"><?php echo count($teamMembers); ?></div>
                            <div class="card-label">Total Members</div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="dashboard-card">
                            <div class="card-icon success">
                                <i class="fas ri-user-line-check"></i>
                            </div>
                            <div class="card-value">
                                <?php
                                $activeCount = array_filter($teamMembers, function($m) {
                                    return $m['is_active'];
                                });
                                echo count($activeCount);
                                ?>
                            </div>
                            <div class="card-label">Active Members</div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="dashboard-card">
                            <div class="card-icon info">
                                <i class="fas ri-task-line"></i>
                            </div>
                            <div class="card-value">
                                <?php
                                $totalTasks = array_sum(array_column($teamMembers, 'tasks'));
                                echo $totalTasks;
                                ?>
                            </div>
                            <div class="card-label">Total Tasks</div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="dashboard-card">
                            <div class="card-icon warning">
                                <i class="fas ri-time-line"></i>
                            </div>
                            <div class="card-value">
                                <?php
                                $totalMinutes = array_sum(array_column($teamMembers, 'total_minutes'));
                                echo formatHours($totalMinutes);
                                ?>
                            </div>
                            <div class="card-label">Total Hours</div>
                        </div>
                    </div>
                </div>

                <!-- Team Members Table -->
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3 style="margin: 0;">Team Overview</h3>
                            <p style="font-size: 13px; color: var(--text-secondary); margin: 4px 0 0 0;">
                                All team members with their performance metrics
                            </p>
                        </div>
                        <div style="display: flex; gap: 12px; align-items: center;">
                            <div style="position: relative;">
                                <i class="fas ri-search-line" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-tertiary);"></i>
                                <input type="text" id="searchInput" placeholder="Search team members..."
                                       class="form-control" style="padding-left: 36px; width: 250px;">
                            </div>
                            <a href="users.php?action=add" class="btn btn-primary btn-sm">
                                <i class="fas ri-add-line"></i> Add Member
                            </a>
                        </div>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <div class="data-table-container" style="border: none; box-shadow: none;">
                            <table class="data-table" id="teamTable">
                                <thead>
                                    <tr>
                                        <th class="sortable">Name</th>
                                        <th class="sortable">Email</th>
                                        <th>Role</th>
                                        <th>Job Title</th>
                                        <th class="sortable">Projects</th>
                                        <th class="sortable">Tasks</th>
                                        <th class="sortable">Completed</th>
                                        <th class="sortable">Total Hours</th>
                                        <th>Status</th>
                                        <th class="sortable">Last Login</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($teamMembers as $member): ?>
                                    <tr>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 12px;">
                                                <div style="width: 36px; height: 36px; border-radius: 50%;
                                                            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                                                            display: flex; align-items: center; justify-content: center;
                                                            color: white; font-weight: 600; font-size: 14px;">
                                                    <?php echo strtoupper(substr($member['full_name'], 0, 1)); ?>
                                                </div>
                                                <strong style="color: var(--heading-color);"><?php echo e($member['full_name']); ?></strong>
                                            </div>
                                        </td>
                                        <td><?php echo e($member['email']); ?></td>
                                        <td>
                                            <span class="badge <?php echo $member['role'] === 'manager' ? 'badge-warning' : 'badge-primary'; ?>">
                                                <?php echo ucfirst($member['role']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo e($member['job_title'] ?? 'N/A'); ?></td>
                                        <td><strong><?php echo $member['projects']; ?></strong></td>
                                        <td><strong><?php echo $member['tasks']; ?></strong></td>
                                        <td>
                                            <span class="badge badge-success">
                                                <?php echo $member['completed_tasks']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong style="color: var(--primary);">
                                                <?php echo formatHours($member['total_minutes'] ?? 0); ?>h
                                            </strong>
                                        </td>
                                        <td>
                                            <?php if ($member['is_active']): ?>
                                                <span class="badge badge-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge badge-danger">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            if ($member['last_login']) {
                                                echo '<span style="color: var(--text-secondary); font-size: 13px;">' . timeAgo($member['last_login']) . '</span>';
                                            } else {
                                                echo '<span style="color: var(--text-tertiary); font-size: 13px;">Never</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <div style="display: flex; gap: 6px;">
                                                <a href="users.php?action=edit&id=<?php echo $member['id']; ?>"
                                                   class="btn btn-outline btn-sm btn-icon" title="Edit">
                                                    <i class="fas ri-edit-line"></i>
                                                </a>
                                                <a href="reports.php?employee=<?php echo $member['id']; ?>&type=monthly"
                                                   class="btn btn-outline btn-sm btn-icon" title="Reports">
                                                    <i class="fas ri-bar-chart-box-line"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Search functionality
        const searchInput = document.getElementById('searchInput');
        searchInput.addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            const rows = document.querySelectorAll('#teamTable tbody tr');

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        });

        // Auto-hide alerts after 5 seconds
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            }, 5000);
        });
    });
    </script>

    <!-- Synto Dashboard Interactions -->
    <script src="../assets/js/synto-interactions.js"></script>
</body>
</html>
