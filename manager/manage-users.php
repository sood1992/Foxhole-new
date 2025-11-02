<?php
// Start output buffering
ob_start();

require_once '../config/config.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !hasRole('manager')) {
    redirect('../login.php');
}

$db = getDBConnection();
$currentUser = getCurrentUser();
$error = '';
$success = '';

$action = $_GET['action'] ?? 'list';
$userId = $_GET['id'] ?? null;

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
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $insertStmt = $db->prepare("
                        INSERT INTO users (username, email, password, full_name, role, job_title, is_active, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                    ");

                    if ($insertStmt->execute([$username, $email, $hashedPassword, $full_name, $role, $job_title, $is_active])) {
                        ob_end_clean();
                        $_SESSION['success_message'] = 'User added successfully!';
                        header("Location: manage-users.php");
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
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            if (empty($username) || empty($email) || empty($full_name)) {
                $error = 'Please fill in all required fields.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Please enter a valid email address.';
            } else {
                // Update user
                if (!empty($password)) {
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
                            header("Location: manage-users.php");
                            exit();
                        } else {
                            $error = 'Failed to update user.';
                        }
                    }
                } else {
                    $updateStmt = $db->prepare("
                        UPDATE users
                        SET username = ?, email = ?, full_name = ?, role = ?,
                            job_title = ?, is_active = ?
                        WHERE id = ?
                    ");

                    if ($updateStmt->execute([$username, $email, $full_name, $role, $job_title, $is_active, $userId])) {
                        ob_end_clean();
                        $_SESSION['success_message'] = 'User updated successfully!';
                        header("Location: manage-users.php");
                        exit();
                    } else {
                        $error = 'Failed to update user.';
                    }
                }
            }
        }
    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
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
        redirect('manage-users.php');
    }
}

// Get all users for list view
if ($action === 'list') {
    $users = $db->query("
        SELECT u.*,
               (SELECT COUNT(*) FROM tasks WHERE assigned_to = u.id) as task_count
        FROM users u
        WHERE u.role IN ('employee', 'manager')
        ORDER BY u.is_active DESC, u.full_name
    ")->fetchAll();
}

$pageTitle = $action === 'add' ? 'Add User' : ($action === 'edit' ? 'Edit User' : 'Manage Users');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> V3 - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/vien-v3.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="app-container">
        <?php include '../includes/v3-manager-sidebar.php'; ?>

        <div class="main-content">
            <?php include '../includes/v3-header.php'; ?>
            <div class="content-wrapper">
                <?php if ($action === 'list'): ?>
                    <!-- User List View -->
                    <div class="page-header">
                        <div>
                            <h1><i class="fas fa-users"></i> <?php echo $pageTitle; ?></h1>
                            <p class="page-subtitle">Manage team members and their permissions</p>
                        </div>
                        <div class="page-actions">
                            <a href="manage-users.php?action=add" class="btn btn-primary"><i class="fas fa-plus"></i> Add User</a>
                        </div>
                    </div>

                    <?php if (isset($_SESSION['success_message'])): ?>
                        <div class="alert alert-success" style="margin-bottom: 24px;">
                            <?php
                            echo e($_SESSION['success_message']);
                            unset($_SESSION['success_message']);
                            ?>
                        </div>
                    <?php endif; ?>

                    <div class="dashboard-card">
                        <div class="card-body">
                            <div class="table-container">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Role</th>
                                            <th>Job Title</th>
                                            <th>Tasks</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($users as $member): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo e($member['full_name']); ?></strong>
                                            </td>
                                            <td><?php echo e($member['email']); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo $member['role'] === 'manager' ? 'primary' : 'secondary'; ?>">
                                                    <?php echo ucfirst($member['role']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo e($member['job_title'] ?? 'N/A'); ?></td>
                                            <td><?php echo $member['task_count']; ?></td>
                                            <td>
                                                <?php if ($member['is_active']): ?>
                                                    <span class="badge badge-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge badge-danger">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="manage-users.php?action=edit&id=<?php echo $member['id']; ?>"
                                                   class="btn btn-secondary btn-sm"><i class="fas fa-edit"></i> Edit</a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- Add/Edit Form -->
                    <div class="page-header">
                        <div>
                            <h1><i class="fas fa-user-<?php echo $action === 'add' ? 'plus' : 'edit'; ?>"></i> <?php echo $pageTitle; ?></h1>
                            <p class="page-subtitle"><?php echo $action === 'add' ? 'Add a new team member' : 'Update team member information'; ?></p>
                        </div>
                        <div class="page-actions">
                            <a href="manage-users.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Users</a>
                        </div>
                    </div>

                    <div class="dashboard-card" style="max-width: 800px; margin: 0 auto;">
                        <div class="card-body">
                            <?php if ($error): ?>
                                <div class="alert alert-error" style="margin-bottom: 24px;">
                                    <?php echo e($error); ?>
                                </div>
                            <?php endif; ?>

                            <form method="POST" action="" style="display: grid; gap: 24px;">
                                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                    <div class="form-group">
                                        <label for="full_name">Full Name <span style="color: var(--danger);">*</span></label>
                                        <input type="text" id="full_name" name="full_name" class="form-control" required
                                               value="<?php echo e($user['full_name'] ?? ''); ?>"
                                               placeholder="John Doe">
                                    </div>

                                    <div class="form-group">
                                        <label for="username">Username <span style="color: var(--danger);">*</span></label>
                                        <input type="text" id="username" name="username" class="form-control" required
                                               value="<?php echo e($user['username'] ?? ''); ?>"
                                               placeholder="johndoe">
                                    </div>
                                </div>

                                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                    <div class="form-group">
                                        <label for="email">Email <span style="color: var(--danger);">*</span></label>
                                        <input type="email" id="email" name="email" class="form-control" required
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
                                        <input type="password" id="password" name="password" class="form-control"
                                               <?php echo $action === 'add' ? 'required' : ''; ?>
                                               placeholder="<?php echo $action === 'add' ? 'Min. 6 characters' : 'Leave blank to keep current'; ?>">
                                    </div>
                                </div>

                                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                    <div class="form-group">
                                        <label for="role">Role <span style="color: var(--danger);">*</span></label>
                                        <select id="role" name="role" class="form-control" required>
                                            <option value="employee" <?php echo ($user['role'] ?? '') === 'employee' ? 'selected' : ''; ?>>
                                                Employee
                                            </option>
                                            <option value="manager" <?php echo ($user['role'] ?? '') === 'manager' ? 'selected' : ''; ?>>
                                                Manager
                                            </option>
                                        </select>
                                        <small style="color: var(--text-secondary); font-size: 12px;">Note: Only admins can create admin users</small>
                                    </div>

                                    <div class="form-group">
                                        <label for="job_title">Job Title</label>
                                        <input type="text" id="job_title" name="job_title" class="form-control"
                                               value="<?php echo e($user['job_title'] ?? ''); ?>"
                                               placeholder="e.g., Senior Developer">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label style="display: flex; align-items: center; gap: 12px; cursor: pointer;">
                                        <input type="checkbox" id="is_active" name="is_active"
                                               <?php echo ($user['is_active'] ?? 1) ? 'checked' : ''; ?>
                                               style="width: 20px; height: 20px;">
                                        <span>Active User</span>
                                    </label>
                                    <small style="color: var(--text-secondary); font-size: 12px;">Inactive users cannot log in</small>
                                </div>

                                <div style="border-top: 2px solid var(--border); padding-top: 24px; display: flex; gap: 12px; justify-content: flex-end;">
                                    <a href="manage-users.php" class="btn btn-secondary">Cancel</a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-<?php echo $action === 'add' ? 'plus' : 'save'; ?>"></i>
                                        <?php echo $action === 'add' ? 'Add User' : 'Save Changes'; ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="../assets/js/theme.js"></script>
</body>
</html>
