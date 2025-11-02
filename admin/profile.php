<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !hasRole('admin')) {
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

// Get system-wide stats
$stats = [
    'total_users' => 0,
    'total_projects' => 0,
    'total_tasks' => 0,
    'active_users' => 0
];

try {
    // Total users
    $stmt = $db->query("SELECT COUNT(*) FROM users");
    $stats['total_users'] = $stmt->fetchColumn();

    // Total projects
    $stmt = $db->query("SELECT COUNT(*) FROM projects");
    $stats['total_projects'] = $stmt->fetchColumn();

    // Total tasks
    $stmt = $db->query("SELECT COUNT(*) FROM tasks");
    $stats['total_tasks'] = $stmt->fetchColumn();

    // Active users (logged in within last 7 days)
    $stmt = $db->query("SELECT COUNT(*) FROM users WHERE last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stats['active_users'] = $stmt->fetchColumn();
} catch (Exception $e) {
    // Stats calculation failed, keep defaults
}

// Get recent system activity
$recentActivity = [];
try {
    // Get recent users joined
    $stmt = $db->query("
        SELECT 'user_joined' as type, full_name as name, created_at
        FROM users
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $recentActivity = $stmt->fetchAll();
} catch (Exception $e) {
    // No recent activity
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
        <?php include '../includes/v3-admin-sidebar.php'; ?>

        <div class="main-content">
            <?php include '../includes/v3-header.php'; ?>

            <div class="content-wrapper">
                <div class="page-header">
                    <div>
                        <h1 class="page-title"><i class="fas fa-user-shield"></i> My Profile</h1>
                        <p class="page-description">Manage your administrator account information</p>
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
                                               placeholder="e.g., System Administrator">
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
                        <!-- System Stats -->
                        <div class="dashboard-card" style="margin-bottom: 24px;">
                            <div class="card-header">
                                <h3><i class="fas fa-server"></i> System Overview</h3>
                            </div>
                            <div class="card-body">
                                <div style="margin-bottom: 16px;">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                        <span style="color: var(--text-secondary); font-size: 13px;">Total Users</span>
                                        <span style="font-weight: 600; font-size: 18px; color: var(--primary);"><?php echo $stats['total_users']; ?></span>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                        <span style="color: var(--text-secondary); font-size: 13px;">Active Users (7d)</span>
                                        <span style="font-weight: 600; font-size: 18px; color: var(--success);"><?php echo $stats['active_users']; ?></span>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                        <span style="color: var(--text-secondary); font-size: 13px;">Total Projects</span>
                                        <span style="font-weight: 600; font-size: 18px; color: var(--info);"><?php echo $stats['total_projects']; ?></span>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                        <span style="color: var(--text-secondary); font-size: 13px;">Total Tasks</span>
                                        <span style="font-weight: 600; font-size: 18px; color: var(--warning);"><?php echo $stats['total_tasks']; ?></span>
                                    </div>
                                </div>
                                <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--border-light);">
                                    <a href="master-dashboard.php" class="btn btn-sm btn-primary" style="width: 100%; text-align: center;">
                                        <i class="fas fa-chart-line"></i> View Full Dashboard
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Recent System Activity -->
                        <div class="dashboard-card" style="margin-bottom: 24px;">
                            <div class="card-header">
                                <h3><i class="fas fa-history"></i> Recent Activity</h3>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recentActivity)): ?>
                                    <p style="color: var(--text-secondary); font-size: 13px; text-align: center; padding: 20px 0;">
                                        No recent activity
                                    </p>
                                <?php else: ?>
                                    <div style="font-size: 13px;">
                                        <?php foreach ($recentActivity as $activity): ?>
                                            <div style="padding: 10px 0; border-bottom: 1px solid var(--border-light);">
                                                <div style="display: flex; align-items: center; margin-bottom: 4px;">
                                                    <i class="fas fa-user-plus" style="color: var(--success); margin-right: 8px;"></i>
                                                    <span style="font-weight: 500;">
                                                        <?php echo e($activity['name']); ?>
                                                    </span>
                                                </div>
                                                <div style="color: var(--text-secondary); font-size: 11px; margin-left: 24px;">
                                                    Joined <?php echo date('M d, Y', strtotime($activity['created_at'])); ?>
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
                                        <span style="font-weight: 600; color: var(--danger); float: right;">
                                            <i class="fas fa-user-shield"></i> Administrator
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
