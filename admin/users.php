<?php
// Start output buffering to prevent header issues
ob_start();

require_once '../config/config.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !hasRole('admin')) {
    redirect('../login.php');
}

$db = getDBConnection();
$currentUser = getCurrentUser();

$action = $_GET['action'] ?? 'list';
$userId = $_GET['id'] ?? null;
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($action === 'add') {
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $full_name = trim($_POST['full_name'] ?? '');
            $role = $_POST['role'] ?? 'employee';
            $job_title = trim($_POST['job_title'] ?? '');
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            // Validation
            if (empty($username) || empty($email) || empty($password) || empty($full_name)) {
                $error = 'Please fill in all required fields.';
            } elseif (strlen($password) < 6) {
                $error = 'Password must be at least 6 characters long.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Please enter a valid email address.';
            } else {
                // Check if username or email already exists
                $checkStmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                $checkStmt->execute([$username, $email]);

                if ($checkStmt->fetch()) {
                    $error = 'Username or email already exists.';
                } else {
                    // Insert new user
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $insertStmt = $db->prepare("
                        INSERT INTO users (username, email, password, full_name, role, job_title, is_active, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                    ");

                    $result = $insertStmt->execute([$username, $email, $hashedPassword, $full_name, $role, $job_title, $is_active]);

                    if ($result) {
                        // Clear output buffer
                        ob_end_clean();
                        // Set success message in session and redirect
                        $_SESSION['success_message'] = 'User added successfully!';
                        header("Location: team.php");
                        exit();
                    } else {
                        $errorInfo = $insertStmt->errorInfo();
                        $error = 'Failed to add user: ' . ($errorInfo[2] ?? 'Unknown database error');
                        error_log("User insert failed: " . print_r($errorInfo, true));
                    }
                }
            }
        } elseif ($action === 'edit' && $userId) {
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $full_name = trim($_POST['full_name'] ?? '');
            $role = $_POST['role'] ?? 'employee';
            $job_title = trim($_POST['job_title'] ?? '');
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            // Validation
            if (empty($username) || empty($email) || empty($full_name)) {
                $error = 'Please fill in all required fields.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Please enter a valid email address.';
            } else {
                // Check if username or email already exists for other users
                $checkStmt = $db->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
                $checkStmt->execute([$username, $email, $userId]);

                if ($checkStmt->fetch()) {
                    $error = 'Username or email already exists.';
                } else {
                    // Update user
                    if (!empty($password)) {
                        // Update with new password
                        if (strlen($password) < 6) {
                            $error = 'Password must be at least 6 characters long.';
                        } else {
                            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                            $updateStmt = $db->prepare("
                                UPDATE users
                                SET username = ?, email = ?, password = ?, full_name = ?, role = ?,
                                    job_title = ?, is_active = ?
                                WHERE id = ?
                            ");

                            if ($updateStmt->execute([$username, $email, $hashedPassword, $full_name, $role, $job_title, $is_active, $userId])) {
                                ob_end_clean();
                                $_SESSION['success_message'] = 'User updated successfully!';
                                header("Location: team.php");
                                exit();
                            } else {
                                $error = 'Failed to update user. Please try again.';
                            }
                        }
                    } else {
                        // Update without changing password
                        $updateStmt = $db->prepare("
                            UPDATE users
                            SET username = ?, email = ?, full_name = ?, role = ?,
                                job_title = ?, is_active = ?
                            WHERE id = ?
                        ");

                        if ($updateStmt->execute([$username, $email, $full_name, $role, $job_title, $is_active, $userId])) {
                            ob_end_clean();
                            $_SESSION['success_message'] = 'User updated successfully!';
                            header("Location: team.php");
                            exit();
                        } else {
                            $error = 'Failed to update user. Please try again.';
                        }
                    }
                }
            }
        } elseif ($action === 'delete' && $userId) {
            // Prevent deleting yourself
            if ($userId == $currentUser['id']) {
                $error = 'You cannot delete your own account.';
            } else {
                $deleteStmt = $db->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
                if ($deleteStmt->execute([$userId])) {
                    ob_end_clean();
                    $_SESSION['success_message'] = 'User deactivated successfully!';
                    header("Location: team.php");
                    exit();
                } else {
                    $error = 'Failed to deactivate user.';
                }
            }
        }
    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
        error_log("User management error: " . $e->getMessage());
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
        error_log("User management error: " . $e->getMessage());
    }
}

// Get user data for edit mode
$user = null;
if ($action === 'edit' && $userId) {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        redirect('team.php');
    }
}

$pageTitle = $action === 'add' ? 'Add Team Member' : 'Edit Team Member';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - <?php echo SITE_NAME; ?> V3</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/vien-v3.css">
</head>
<body>
    <div class="app-container">
        <?php include '../includes/v3-admin-sidebar.php'; ?>

        <div class="main-content">
            <?php include '../includes/v3-header.php'; ?>

            <div class="content-wrapper">
                <!-- Page Title -->
                <div style="margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h1 style="margin-bottom: 8px;"><?php echo $pageTitle; ?></h1>
                        <p style="color: var(--text-secondary); font-size: 14px; margin: 0;">
                            <?php echo $action === 'add' ? 'Add a new team member to the system' : 'Update user information and permissions'; ?>
                        </p>
                    </div>
                    <a href="team.php" class="btn btn-outline btn-sm">
                        <i class="fas fa-arrow-left"></i> Back to Team
                    </a>
                </div>

                <!-- User Form -->
                <div class="card" style="max-width: 900px; margin: 0 auto;">
                    <div class="card-header">
                        <div>
                            <h3 style="margin: 0;"><?php echo $action === 'add' ? 'New User Information' : 'Update User Information'; ?></h3>
                            <p style="font-size: 13px; color: var(--text-secondary); margin: 4px 0 0 0;">
                                Fill in the user details below
                            </p>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-error" style="margin-bottom: 24px;">
                                <i class="fas fa-exclamation-circle"></i> <?php echo e($error); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success" style="margin-bottom: 24px;">
                                <i class="fas fa-check-circle"></i> <?php echo e($success); ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="" style="display: grid; gap: 20px;">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div class="form-group">
                                    <label>Full Name <span class="required">*</span></label>
                                    <input type="text" name="full_name" class="form-control" required
                                           value="<?php echo e($user['full_name'] ?? ''); ?>"
                                           placeholder="John Doe">
                                </div>

                                <div class="form-group">
                                    <label>Username <span class="required">*</span></label>
                                    <input type="text" name="username" class="form-control" required
                                           value="<?php echo e($user['username'] ?? ''); ?>"
                                           placeholder="johndoe">
                                </div>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div class="form-group">
                                    <label>Email <span class="required">*</span></label>
                                    <input type="email" name="email" class="form-control" required
                                           value="<?php echo e($user['email'] ?? ''); ?>"
                                           placeholder="john@example.com">
                                </div>

                                <div class="form-group">
                                    <label>
                                        Password
                                        <?php if ($action === 'add'): ?>
                                            <span class="required">*</span>
                                        <?php else: ?>
                                            <span style="color: var(--text-secondary); font-weight: 400; font-size: 12px;">(leave blank to keep current)</span>
                                        <?php endif; ?>
                                    </label>
                                    <input type="password" name="password" class="form-control"
                                           <?php echo $action === 'add' ? 'required' : ''; ?>
                                           placeholder="<?php echo $action === 'add' ? 'Min. 6 characters' : 'Leave blank to keep current'; ?>">
                                </div>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div class="form-group">
                                    <label>Role <span class="required">*</span></label>
                                    <select name="role" class="form-control" required>
                                        <option value="employee" <?php echo ($user['role'] ?? '') === 'employee' ? 'selected' : ''; ?>>
                                            Employee
                                        </option>
                                        <option value="manager" <?php echo ($user['role'] ?? '') === 'manager' ? 'selected' : ''; ?>>
                                            Manager
                                        </option>
                                        <option value="admin" <?php echo ($user['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>
                                            Admin
                                        </option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>Job Title</label>
                                    <input type="text" name="job_title" class="form-control"
                                           value="<?php echo e($user['job_title'] ?? ''); ?>"
                                           placeholder="e.g., Senior Developer">
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="custom-checkbox">
                                    <input type="checkbox" name="is_active"
                                           <?php echo ($user['is_active'] ?? 1) ? 'checked' : ''; ?>>
                                    <span class="checkmark"></span>
                                    <span class="checkbox-label">Active User</span>
                                </label>
                                <small class="form-text">Inactive users cannot log in</small>
                            </div>

                            <div style="border-top: 2px solid var(--border-light); padding-top: 24px; display: flex; gap: 12px; justify-content: flex-end;">
                                <a href="team.php" class="btn btn-outline">Cancel</a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-<?php echo $action === 'add' ? 'plus' : 'save'; ?>"></i>
                                    <?php echo $action === 'add' ? 'Add User' : 'Save Changes'; ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if ($action === 'edit' && $user): ?>
                <!-- User Statistics -->
                <div class="card" style="max-width: 900px; margin: 30px auto 0;">
                    <div class="card-header">
                        <div>
                            <h3 style="margin: 0;">User Performance Statistics</h3>
                            <p style="font-size: 13px; color: var(--text-secondary); margin: 4px 0 0 0;">
                                Overview of user activity and performance metrics
                            </p>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php
                        $stats = $db->prepare("
                            SELECT
                                COUNT(DISTINCT t.project_id) as projects,
                                COUNT(DISTINCT t.id) as total_tasks,
                                SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
                                (SELECT SUM(duration_minutes) FROM time_logs WHERE user_id = ?) as total_minutes,
                                (SELECT COUNT(*) FROM time_logs WHERE user_id = ?) as total_sessions
                            FROM tasks t
                            WHERE t.assigned_to = ?
                        ");
                        $stats->execute([$userId, $userId, $userId]);
                        $userStats = $stats->fetch();
                        ?>
                        <div class="row">
                            <div class="col-lg-3 col-md-6">
                                <div class="dashboard-card">
                                    <div class="card-icon primary">
                                        <i class="fas fa-folder"></i>
                                    </div>
                                    <div class="card-value"><?php echo $userStats['projects'] ?? 0; ?></div>
                                    <div class="card-label">Projects</div>
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-6">
                                <div class="dashboard-card">
                                    <div class="card-icon info">
                                        <i class="fas fa-tasks"></i>
                                    </div>
                                    <div class="card-value"><?php echo $userStats['total_tasks'] ?? 0; ?></div>
                                    <div class="card-label">Total Tasks</div>
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-6">
                                <div class="dashboard-card">
                                    <div class="card-icon success">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                    <div class="card-value"><?php echo $userStats['completed_tasks'] ?? 0; ?></div>
                                    <div class="card-label">Completed</div>
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-6">
                                <div class="dashboard-card">
                                    <div class="card-icon warning">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                    <div class="card-value"><?php echo formatHours($userStats['total_minutes'] ?? 0); ?>h</div>
                                    <div class="card-label">Total Hours</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
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
</body>
</html>
