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
    <title>Team Management - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/premium-theme.css">
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>Neofox</h2>
                <div class="user-role">Admin Panel</div>
            </div>

            <nav class="sidebar-nav">
                <a href="index.php">
                    <span class="icon">📊</span>
                    Dashboard
                </a>
                <a href="reports.php">
                    <span class="icon">📈</span>
                    Reports
                </a>
                <a href="projects.php">
                    <span class="icon">📁</span>
                    Projects
                </a>
                <a href="team.php" class="active">
                    <span class="icon">👥</span>
                    Team Management
                </a>
                <a href="users.php">
                    <span class="icon">⚙️</span>
                    User Settings
                </a>
            </nav>

            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($currentUser['full_name'], 0, 1)); ?>
                    </div>
                    <div class="user-details">
                        <h4><?php echo e($currentUser['full_name']); ?></h4>
                        <p><?php echo e($currentUser['job_title'] ?? 'Administrator'); ?></p>
                    </div>
                </div>
                <a href="../logout.php" class="btn btn-secondary btn-block btn-sm">Logout</a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="topbar">
                <h1>Team Management</h1>
                <div class="topbar-actions">
                    <a href="users.php?action=add" class="btn btn-primary btn-sm">+ Add Team Member</a>
                </div>
            </div>

            <div class="content">
                <div class="card">
                    <div class="card-header">
                        <h3>Team Overview</h3>
                        <div>
                            <input type="text" id="searchInput" placeholder="Search team members..."
                                   style="padding: 8px 16px; border: 2px solid var(--border); border-radius: var(--radius-sm);">
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-container">
                            <table id="teamTable">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Job Title</th>
                                        <th>Projects</th>
                                        <th>Tasks</th>
                                        <th>Completed</th>
                                        <th>Total Hours</th>
                                        <th>Status</th>
                                        <th>Last Login</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($teamMembers as $member): ?>
                                    <tr>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 12px;">
                                                <div class="user-avatar" style="width: 36px; height: 36px; font-size: 14px;">
                                                    <?php echo strtoupper(substr($member['full_name'], 0, 1)); ?>
                                                </div>
                                                <strong><?php echo e($member['full_name']); ?></strong>
                                            </div>
                                        </td>
                                        <td><?php echo e($member['email']); ?></td>
                                        <td>
                                            <span class="badge <?php echo $member['role'] === 'manager' ? 'priority-high' : 'priority-medium'; ?>">
                                                <?php echo ucfirst($member['role']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo e($member['job_title'] ?? 'N/A'); ?></td>
                                        <td><?php echo $member['projects']; ?></td>
                                        <td><?php echo $member['tasks']; ?></td>
                                        <td>
                                            <span class="badge status-completed">
                                                <?php echo $member['completed_tasks']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong><?php echo formatHours($member['total_minutes'] ?? 0); ?>h</strong>
                                        </td>
                                        <td>
                                            <?php if ($member['is_active']): ?>
                                                <span class="badge status-completed">Active</span>
                                            <?php else: ?>
                                                <span class="badge status-blocked">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            if ($member['last_login']) {
                                                echo timeAgo($member['last_login']);
                                            } else {
                                                echo '<span style="color: var(--text-secondary);">Never</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <div style="display: flex; gap: 8px;">
                                                <a href="users.php?action=edit&id=<?php echo $member['id']; ?>"
                                                   class="btn btn-secondary btn-sm">Edit</a>
                                                <a href="reports.php?employee=<?php echo $member['id']; ?>&type=monthly"
                                                   class="btn btn-secondary btn-sm">View Reports</a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Team Statistics -->
                <div class="stats-grid" style="margin-top: 24px;">
                    <div class="stat-card blue">
                        <div class="stat-card-header">
                            <div>
                                <div class="stat-label">Total Members</div>
                                <div class="stat-value"><?php echo count($teamMembers); ?></div>
                                <div class="stat-change">Team Size</div>
                            </div>
                            <div class="stat-icon">👥</div>
                        </div>
                    </div>

                    <div class="stat-card green">
                        <div class="stat-card-header">
                            <div>
                                <div class="stat-label">Active Members</div>
                                <div class="stat-value">
                                    <?php
                                    $activeCount = array_filter($teamMembers, function($m) {
                                        return $m['is_active'];
                                    });
                                    echo count($activeCount);
                                    ?>
                                </div>
                                <div class="stat-change">Working</div>
                            </div>
                            <div class="stat-icon">✅</div>
                        </div>
                    </div>

                    <div class="stat-card orange">
                        <div class="stat-card-header">
                            <div>
                                <div class="stat-label">Total Tasks</div>
                                <div class="stat-value">
                                    <?php
                                    $totalTasks = array_sum(array_column($teamMembers, 'tasks'));
                                    echo $totalTasks;
                                    ?>
                                </div>
                                <div class="stat-change">Assigned</div>
                            </div>
                            <div class="stat-icon">📋</div>
                        </div>
                    </div>

                    <div class="stat-card blue">
                        <div class="stat-card-header">
                            <div>
                                <div class="stat-label">Total Hours</div>
                                <div class="stat-value">
                                    <?php
                                    $totalMinutes = array_sum(array_column($teamMembers, 'total_minutes'));
                                    echo formatHours($totalMinutes);
                                    ?>
                                </div>
                                <div class="stat-change">All Time</div>
                            </div>
                            <div class="stat-icon">⏱️</div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/main.js"></script>
    <script>
        // Search functionality
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            const rows = document.querySelectorAll('#teamTable tbody tr');

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        });
    </script>
    <script src="../assets/js/theme.js"></script>
</body>
</html>
