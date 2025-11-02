<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !hasRole('employee')) {
    redirect('../login.php');
}

$db = getDBConnection();
$currentUser = getCurrentUser();
$success = '';
$error = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'update_profile') {
            $fullName = trim($_POST['full_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $jobTitle = trim($_POST['job_title'] ?? '');

            if (empty($fullName) || empty($email)) {
                throw new Exception('Name and email are required');
            }

            // Check if email already exists for another user
            $checkEmail = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $checkEmail->execute([$email, $currentUser['id']]);
            if ($checkEmail->fetch()) {
                throw new Exception('Email already in use by another user');
            }

            $stmt = $db->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, job_title = ? WHERE id = ?");
            $stmt->execute([$fullName, $email, $phone, $jobTitle, $currentUser['id']]);

            $success = 'Profile updated successfully!';
            $currentUser = getCurrentUser(); // Refresh user data
        }
        elseif ($_POST['action'] === 'change_password') {
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if (empty($currentPassword) || empty($newPassword)) {
                throw new Exception('All password fields are required');
            }

            // Verify current password
            $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$currentUser['id']]);
            $user = $stmt->fetch();

            if (!password_verify($currentPassword, $user['password'])) {
                throw new Exception('Current password is incorrect');
            }

            if (strlen($newPassword) < 6) {
                throw new Exception('New password must be at least 6 characters');
            }

            if ($newPassword !== $confirmPassword) {
                throw new Exception('New passwords do not match');
            }

            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashedPassword, $currentUser['id']]);

            $success = 'Password changed successfully!';
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get user stats
$statsStmt = $db->prepare("
    SELECT
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_tasks,
        COUNT(*) as total_tasks,
        COALESCE(SUM(actual_hours), 0) as total_hours
    FROM tasks
    WHERE assigned_to = ?
");
$statsStmt->execute([$currentUser['id']]);
$stats = $statsStmt->fetch();

// Get recent activity
$activityStmt = $db->prepare("
    SELECT
        t.task_name,
        t.status,
        t.updated_at,
        p.project_name
    FROM tasks t
    LEFT JOIN projects p ON t.project_id = p.id
    WHERE t.assigned_to = ?
    ORDER BY t.updated_at DESC
    LIMIT 10
");
$activityStmt->execute([$currentUser['id']]);
$recentActivity = $activityStmt->fetchAll();

// Get account created date
$createdStmt = $db->prepare("SELECT created_at FROM users WHERE id = ?");
$createdStmt->execute([$currentUser['id']]);
$accountCreated = $createdStmt->fetch()['created_at'];
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
        <?php include '../includes/v3-employee-sidebar.php'; ?>

        <div class="main-content">
            <?php include '../includes/v3-header.php'; ?>

            <div class="content-wrapper">
                <div class="page-header">
                    <div>
                        <h1 class="page-title"><i class="fas fa-user"></i> My Profile</h1>
                        <p class="page-description">Manage your personal information and account settings</p>
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
                    <!-- Left Column -->
                    <div class="col-lg-8">
                        <!-- Profile Information -->
                        <div class="dashboard-card" style="margin-bottom: 24px;">
                            <div class="card-header">
                                <h3><i class="fas fa-user-circle"></i> Profile Information</h3>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <input type="hidden" name="action" value="update_profile">

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
                                               value="<?php echo e($currentUser['phone'] ?? ''); ?>">
                                    </div>

                                    <div class="form-group">
                                        <label for="job_title">Job Title</label>
                                        <input type="text" id="job_title" name="job_title" class="form-control"
                                               value="<?php echo e($currentUser['job_title'] ?? ''); ?>">
                                    </div>

                                    <div class="form-group">
                                        <label>Role</label>
                                        <input type="text" class="form-control" value="<?php echo ucfirst($currentUser['role']); ?>" disabled>
                                    </div>

                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Save Changes
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
                                    <input type="hidden" name="action" value="change_password">

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

                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-key"></i> Change Password
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column -->
                    <div class="col-lg-4">
                        <!-- Profile Stats -->
                        <div class="dashboard-card" style="margin-bottom: 24px;">
                            <div class="card-header">
                                <h3><i class="fas fa-chart-bar"></i> My Stats</h3>
                            </div>
                            <div class="card-body">
                                <div style="margin-bottom: 20px;">
                                    <div style="font-size: 32px; font-weight: 700; color: var(--primary); margin-bottom: 4px;">
                                        <?php echo $stats['completed_tasks']; ?>
                                    </div>
                                    <div style="font-size: 13px; color: var(--text-secondary);">Tasks Completed</div>
                                </div>

                                <div style="margin-bottom: 20px;">
                                    <div style="font-size: 32px; font-weight: 700; color: var(--success); margin-bottom: 4px;">
                                        <?php echo $stats['total_tasks']; ?>
                                    </div>
                                    <div style="font-size: 13px; color: var(--text-secondary);">Total Tasks</div>
                                </div>

                                <div style="margin-bottom: 20px;">
                                    <div style="font-size: 32px; font-weight: 700; color: var(--warning); margin-bottom: 4px;">
                                        <?php echo number_format($stats['total_hours'], 1); ?>h
                                    </div>
                                    <div style="font-size: 13px; color: var(--text-secondary);">Hours Logged</div>
                                </div>

                                <div>
                                    <div style="font-size: 14px; color: var(--text-secondary); margin-bottom: 4px;">
                                        Member Since
                                    </div>
                                    <div style="font-weight: 600;">
                                        <?php echo date('F j, Y', strtotime($accountCreated)); ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Activity -->
                        <div class="dashboard-card">
                            <div class="card-header">
                                <h3><i class="fas fa-clock"></i> Recent Activity</h3>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recentActivity)): ?>
                                    <p style="text-align: center; color: var(--text-secondary); padding: 20px 0;">
                                        No recent activity
                                    </p>
                                <?php else: ?>
                                    <div style="display: flex; flex-direction: column; gap: 12px;">
                                        <?php foreach (array_slice($recentActivity, 0, 5) as $activity): ?>
                                            <div style="padding: 12px; background: var(--bg-tertiary); border-radius: var(--radius-md);">
                                                <div style="font-weight: 600; font-size: 13px; margin-bottom: 4px;">
                                                    <?php echo e($activity['task_name']); ?>
                                                </div>
                                                <div style="display: flex; justify-content: space-between; font-size: 11px; color: var(--text-secondary);">
                                                    <span><?php echo e($activity['project_name']); ?></span>
                                                    <span><?php echo timeAgo($activity['updated_at']); ?></span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
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
