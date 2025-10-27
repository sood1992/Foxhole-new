<?php
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
    if ($action === 'add') {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $full_name = trim($_POST['full_name'] ?? '');
        $role = $_POST['role'] ?? 'employee';
        $job_title = trim($_POST['job_title'] ?? '');
        $hourly_rate = floatval($_POST['hourly_rate'] ?? 0);
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
                    INSERT INTO users (username, email, password, full_name, role, job_title, hourly_rate, is_active, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");

                if ($insertStmt->execute([$username, $email, $hashedPassword, $full_name, $role, $job_title, $hourly_rate, $is_active])) {
                    // Set success message in session and redirect
                    $_SESSION['success_message'] = 'User added successfully!';
                    header("Location: team.php");
                    exit();
                } else {
                    $error = 'Failed to add user. Please try again.';
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
        $hourly_rate = floatval($_POST['hourly_rate'] ?? 0);
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
                                job_title = ?, hourly_rate = ?, is_active = ?
                            WHERE id = ?
                        ");

                        if ($updateStmt->execute([$username, $email, $hashedPassword, $full_name, $role, $job_title, $hourly_rate, $is_active, $userId])) {
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
                            job_title = ?, hourly_rate = ?, is_active = ?
                        WHERE id = ?
                    ");

                    if ($updateStmt->execute([$username, $email, $full_name, $role, $job_title, $hourly_rate, $is_active, $userId])) {
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
                $_SESSION['success_message'] = 'User deactivated successfully!';
                header("Location: team.php");
                exit();
            } else {
                $error = 'Failed to deactivate user.';
            }
        }
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
    <title><?php echo $pageTitle; ?> - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/ultra-premium.css">
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <?php include '../includes/admin-sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <div class="topbar">
                <h1><?php echo $pageTitle; ?></h1>
                <div class="topbar-actions">
                    <a href="team.php" class="btn btn-secondary btn-sm">← Back to Team</a>
                </div>
            </div>

            <div class="content">
                <div class="card" style="max-width: 800px; margin: 0 auto;">
                    <div class="card-header">
                        <h3><?php echo $action === 'add' ? 'New User Information' : 'Update User Information'; ?></h3>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-error" style="margin-bottom: 24px;">
                                <?php echo e($error); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success" style="margin-bottom: 24px;">
                                <?php echo e($success); ?>
                                <br><small>Redirecting to team page...</small>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="" style="display: grid; gap: 24px;">
                            <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div class="form-group">
                                    <label for="full_name">Full Name <span style="color: var(--danger);">*</span></label>
                                    <input type="text" id="full_name" name="full_name" required
                                           value="<?php echo e($user['full_name'] ?? ''); ?>"
                                           placeholder="John Doe">
                                </div>

                                <div class="form-group">
                                    <label for="username">Username <span style="color: var(--danger);">*</span></label>
                                    <input type="text" id="username" name="username" required
                                           value="<?php echo e($user['username'] ?? ''); ?>"
                                           placeholder="johndoe">
                                </div>
                            </div>

                            <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div class="form-group">
                                    <label for="email">Email <span style="color: var(--danger);">*</span></label>
                                    <input type="email" id="email" name="email" required
                                           value="<?php echo e($user['email'] ?? ''); ?>"
                                           placeholder="john@example.com">
                                </div>

                                <div class="form-group">
                                    <label for="password">
                                        Password
                                        <?php if ($action === 'add'): ?>
                                            <span style="color: var(--danger);">*</span>
                                        <?php else: ?>
                                            <span style="color: var(--text-secondary); font-weight: 400;">(leave blank to keep current)</span>
                                        <?php endif; ?>
                                    </label>
                                    <input type="password" id="password" name="password"
                                           <?php echo $action === 'add' ? 'required' : ''; ?>
                                           placeholder="<?php echo $action === 'add' ? 'Min. 6 characters' : 'Leave blank to keep current'; ?>">
                                </div>
                            </div>

                            <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div class="form-group">
                                    <label for="role">Role <span style="color: var(--danger);">*</span></label>
                                    <select id="role" name="role" required>
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
                                    <label for="job_title">Job Title</label>
                                    <input type="text" id="job_title" name="job_title"
                                           value="<?php echo e($user['job_title'] ?? ''); ?>"
                                           placeholder="e.g., Senior Developer">
                                </div>
                            </div>

                            <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div class="form-group">
                                    <label for="hourly_rate">Hourly Rate (₹)</label>
                                    <input type="number" id="hourly_rate" name="hourly_rate" step="0.01" min="0"
                                           value="<?php echo e($user['hourly_rate'] ?? ''); ?>"
                                           placeholder="50.00">
                                    <small style="color: var(--text-secondary); font-size: 12px;">Used for budget calculations</small>
                                </div>

                                <div class="form-group">
                                    <label style="display: flex; align-items: center; gap: 12px; cursor: pointer; margin-top: 32px;">
                                        <input type="checkbox" id="is_active" name="is_active"
                                               <?php echo ($user['is_active'] ?? 1) ? 'checked' : ''; ?>
                                               style="width: 20px; height: 20px;">
                                        <span>Active User</span>
                                    </label>
                                    <small style="color: var(--text-secondary); font-size: 12px;">Inactive users cannot log in</small>
                                </div>
                            </div>

                            <div style="border-top: 2px solid var(--border); padding-top: 24px; display: flex; gap: 12px; justify-content: flex-end;">
                                <a href="team.php" class="btn btn-secondary">Cancel</a>
                                <button type="submit" class="btn btn-primary">
                                    <?php echo $action === 'add' ? '+ Add User' : 'Save Changes'; ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if ($action === 'edit' && $user): ?>
                <!-- User Statistics -->
                <div class="card" style="max-width: 800px; margin: 24px auto 0;">
                    <div class="card-header">
                        <h3>User Statistics</h3>
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
                        <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
                            <div class="stat-card blue">
                                <div class="stat-label">Projects</div>
                                <div class="stat-value"><?php echo $userStats['projects'] ?? 0; ?></div>
                            </div>
                            <div class="stat-card green">
                                <div class="stat-label">Total Tasks</div>
                                <div class="stat-value"><?php echo $userStats['total_tasks'] ?? 0; ?></div>
                            </div>
                            <div class="stat-card orange">
                                <div class="stat-label">Completed</div>
                                <div class="stat-value"><?php echo $userStats['completed_tasks'] ?? 0; ?></div>
                            </div>
                            <div class="stat-card purple">
                                <div class="stat-label">Total Hours</div>
                                <div class="stat-value"><?php echo formatHours($userStats['total_minutes'] ?? 0); ?>h</div>
                            </div>
                            <div class="stat-card blue">
                                <div class="stat-label">Time Sessions</div>
                                <div class="stat-value"><?php echo $userStats['total_sessions'] ?? 0; ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/theme.js"></script>
</body>
</html>
