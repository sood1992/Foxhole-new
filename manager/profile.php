<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !hasRole('manager')) {
    redirect('../login.php');
}

$db = getDBConnection();
$currentUser = getCurrentUser();
$success = '';
$error = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    try {
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $jobTitle = trim($_POST['job_title'] ?? '');

        if (empty($fullName) || empty($email)) {
            throw new Exception('Name and email are required');
        }

        // Check if email is already taken by another user
        $emailCheck = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $emailCheck->execute([$email, $currentUser['id']]);
        if ($emailCheck->fetch()) {
            throw new Exception('Email is already taken by another user');
        }

        $stmt = $db->prepare("
            UPDATE users
            SET full_name = ?, email = ?, phone = ?, job_title = ?
            WHERE id = ?
        ");
        $stmt->execute([$fullName, $email, $phone, $jobTitle, $currentUser['id']]);

        $success = 'Profile updated successfully!';

        // Refresh current user data
        $_SESSION['user_id'] = $currentUser['id']; // Refresh session
        $currentUser = getCurrentUser();
    } catch (Exception $e) {
        $error = 'Failed to update profile: ' . $e->getMessage();
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    try {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            throw new Exception('All password fields are required');
        }

        // Verify current password
        if (!password_verify($currentPassword, $currentUser['password'])) {
            throw new Exception('Current password is incorrect');
        }

        if ($newPassword !== $confirmPassword) {
            throw new Exception('New passwords do not match');
        }

        if (strlen($newPassword) < 6) {
            throw new Exception('New password must be at least 6 characters');
        }

        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashedPassword, $currentUser['id']]);

        $success = 'Password changed successfully!';
    } catch (Exception $e) {
        $error = 'Failed to change password: ' . $e->getMessage();
    }
}

// Get manager stats
$stats = [
    'projects_managed' => 0,
    'active_projects' => 0,
    'total_tasks' => 0,
    'team_members' => 0
];

try {
    // Projects managed (where user is manager)
    $stmt = $db->prepare("SELECT COUNT(*) FROM projects WHERE manager_id = ?");
    $stmt->execute([$currentUser['id']]);
    $stats['projects_managed'] = $stmt->fetchColumn();

    // Active projects
    $stmt = $db->prepare("SELECT COUNT(*) FROM projects WHERE manager_id = ? AND status IN ('planning', 'in_progress')");
    $stmt->execute([$currentUser['id']]);
    $stats['active_projects'] = $stmt->fetchColumn();

    // Total tasks in managed projects
    $stmt = $db->prepare("
        SELECT COUNT(t.id)
        FROM tasks t
        JOIN projects p ON t.project_id = p.id
        WHERE p.manager_id = ?
    ");
    $stmt->execute([$currentUser['id']]);
    $stats['total_tasks'] = $stmt->fetchColumn();

    // Team members (unique users assigned to tasks in managed projects)
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT t.assigned_to)
        FROM tasks t
        JOIN projects p ON t.project_id = p.id
        WHERE p.manager_id = ? AND t.assigned_to IS NOT NULL
    ");
    $stmt->execute([$currentUser['id']]);
    $stats['team_members'] = $stmt->fetchColumn();
} catch (Exception $e) {
    // Stats calculation failed, keep defaults
}

// Get recent projects
$recentProjects = [];
try {
    $stmt = $db->prepare("
        SELECT id, name, status, created_at
        FROM projects
        WHERE manager_id = ?
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$currentUser['id']]);
    $recentProjects = $stmt->fetchAll();
} catch (Exception $e) {
    // No recent projects
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/vien-v3.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="app-container">
        <?php include '../includes/v3-manager-sidebar.php'; ?>

        <div class="main-content">
            <?php include '../includes/v3-header.php'; ?>

            <div class="content-wrapper">
                <div class="page-header">
                    <div>
                        <h1 class="page-title"><i class="fas fa-user"></i> My Profile</h1>
                        <p class="page-description">Manage your account information and settings</p>
                    </div>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success" style="margin-bottom: 24px;">
                        <i class="fas fa-check-circle"></i> <?php echo e($success); ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-error" style="margin-bottom: 24px;">
                        <i class="fas fa-exclamation-circle"></i> <?php echo e($error); ?>
                    </div>
                <?php endif; ?>

                <div class="row" style="gap: 24px;">
                    <!-- Left Column - Profile Forms -->
                    <div class="col-lg-8">
                        <!-- Profile Information -->
                        <div class="dashboard-card" style="margin-bottom: 24px;">
                            <div class="card-header">
                                <h3><i class="fas fa-user-edit"></i> Profile Information</h3>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <div class="form-group">
                                        <label for="full_name">Full Name *</label>
                                        <input type="text" id="full_name" name="full_name" class="form-control"
                                               value="<?php echo e($currentUser['full_name']); ?>" required>
                                    </div>

                                    <div class="form-group">
                                        <label for="email">Email Address *</label>
                                        <input type="email" id="email" name="email" class="form-control"
                                               value="<?php echo e($currentUser['email']); ?>" required>
                                    </div>

                                    <div class="form-group">
                                        <label for="phone">Phone Number</label>
                                        <input type="tel" id="phone" name="phone" class="form-control"
                                               value="<?php echo e($currentUser['phone'] ?? ''); ?>"
                                               placeholder="+1 (555) 123-4567">
                                    </div>

                                    <div class="form-group">
                                        <label for="job_title">Job Title</label>
                                        <input type="text" id="job_title" name="job_title" class="form-control"
                                               value="<?php echo e($currentUser['job_title'] ?? ''); ?>"
                                               placeholder="e.g., Project Manager">
                                    </div>

                                    <button type="submit" name="update_profile" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Update Profile
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Change Password -->
                        <div class="dashboard-card">
                            <div class="card-header">
                                <h3><i class="fas fa-lock"></i> Change Password</h3>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <div class="form-group">
                                        <label for="current_password">Current Password *</label>
                                        <input type="password" id="current_password" name="current_password" class="form-control" required>
                                    </div>

                                    <div class="form-group">
                                        <label for="new_password">New Password *</label>
                                        <input type="password" id="new_password" name="new_password" class="form-control"
                                               minlength="6" required>
                                        <small style="color: var(--text-secondary);">Minimum 6 characters</small>
                                    </div>

                                    <div class="form-group">
                                        <label for="confirm_password">Confirm New Password *</label>
                                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                                    </div>

                                    <button type="submit" name="change_password" class="btn btn-primary">
                                        <i class="fas fa-key"></i> Change Password
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column - Stats & Activity -->
                    <div class="col-lg-4">
                        <!-- Account Stats -->
                        <div class="dashboard-card" style="margin-bottom: 24px;">
                            <div class="card-header">
                                <h3><i class="fas fa-chart-line"></i> Management Stats</h3>
                            </div>
                            <div class="card-body">
                                <div style="margin-bottom: 16px;">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                        <span style="color: var(--text-secondary); font-size: 13px;">Projects Managed</span>
                                        <span style="font-weight: 600; font-size: 18px; color: var(--primary);"><?php echo $stats['projects_managed']; ?></span>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                        <span style="color: var(--text-secondary); font-size: 13px;">Active Projects</span>
                                        <span style="font-weight: 600; font-size: 18px; color: var(--success);"><?php echo $stats['active_projects']; ?></span>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                        <span style="color: var(--text-secondary); font-size: 13px;">Total Tasks</span>
                                        <span style="font-weight: 600; font-size: 18px; color: var(--info);"><?php echo $stats['total_tasks']; ?></span>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                        <span style="color: var(--text-secondary); font-size: 13px;">Team Members</span>
                                        <span style="font-weight: 600; font-size: 18px; color: var(--warning);"><?php echo $stats['team_members']; ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Projects -->
                        <div class="dashboard-card" style="margin-bottom: 24px;">
                            <div class="card-header">
                                <h3><i class="fas fa-folder-open"></i> Recent Projects</h3>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recentProjects)): ?>
                                    <p style="color: var(--text-secondary); font-size: 13px; text-align: center; padding: 20px 0;">
                                        No projects yet
                                    </p>
                                <?php else: ?>
                                    <div style="font-size: 13px;">
                                        <?php foreach ($recentProjects as $project): ?>
                                            <div style="padding: 10px 0; border-bottom: 1px solid var(--border-light);">
                                                <a href="project-details.php?id=<?php echo $project['id']; ?>"
                                                   style="color: var(--text-primary); text-decoration: none; font-weight: 500;">
                                                    <?php echo e($project['name']); ?>
                                                </a>
                                                <div style="display: flex; justify-content: space-between; margin-top: 4px;">
                                                    <span class="status-badge <?php echo getStatusClass($project['status']); ?>">
                                                        <?php echo ucfirst($project['status']); ?>
                                                    </span>
                                                    <span style="color: var(--text-secondary); font-size: 11px;">
                                                        <?php echo date('M d, Y', strtotime($project['created_at'])); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Account Info -->
                        <div class="dashboard-card">
                            <div class="card-header">
                                <h3><i class="fas fa-info-circle"></i> Account Info</h3>
                            </div>
                            <div class="card-body">
                                <div style="font-size: 13px; line-height: 1.8;">
                                    <div style="margin-bottom: 12px;">
                                        <span style="color: var(--text-secondary);">Role:</span>
                                        <span style="font-weight: 600; color: var(--success); float: right;">
                                            <i class="fas fa-user-tie"></i> Manager
                                        </span>
                                    </div>
                                    <div style="margin-bottom: 12px;">
                                        <span style="color: var(--text-secondary);">Member Since:</span>
                                        <span style="font-weight: 500; float: right;">
                                            <?php echo date('M d, Y', strtotime($currentUser['created_at'])); ?>
                                        </span>
                                    </div>
                                    <div>
                                        <span style="color: var(--text-secondary);">User ID:</span>
                                        <span style="font-weight: 500; float: right; font-family: monospace;">
                                            #<?php echo $currentUser['id']; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/theme.js"></script>
</body>
</html>
