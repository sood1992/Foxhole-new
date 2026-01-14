<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$db = getDBConnection();
$currentUser = getCurrentUser();
$error = '';
$success = '';

// Handle avatar upload
if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['avatar'];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 2 * 1024 * 1024; // 2MB

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedTypes)) {
        $error = 'Invalid file type. Only JPG, PNG, GIF, and WebP images are allowed.';
    } elseif ($file['size'] > $maxSize) {
        $error = 'File size must be less than 2MB.';
    } else {
        // Create uploads directory if it doesn't exist
        $uploadDir = 'uploads/avatars/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'avatar_' . $currentUser['id'] . '_' . time() . '.' . $extension;
        $filepath = $uploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            // Delete old avatar if exists
            if (!empty($currentUser['avatar']) && file_exists($currentUser['avatar'])) {
                unlink($currentUser['avatar']);
            }

            // Update database
            $stmt = $db->prepare("UPDATE users SET avatar = ? WHERE id = ?");
            $stmt->execute([$filepath, $currentUser['id']]);

            $success = 'Avatar updated successfully!';
            // Refresh current user data
            $currentUser = getCurrentUser();
        } else {
            $error = 'Failed to upload avatar. Please try again.';
        }
    }
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $jobTitle = trim($_POST['job_title'] ?? '');

    if (empty($fullName)) {
        $error = 'Full name is required.';
    } elseif (empty($email)) {
        $error = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } else {
        // Check if email is already taken by another user
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $currentUser['id']]);
        if ($stmt->fetch()) {
            $error = 'This email is already in use by another account.';
        } else {
            // Update profile
            $stmt = $db->prepare("UPDATE users SET full_name = ?, email = ?, job_title = ? WHERE id = ?");
            $stmt->execute([$fullName, $email, $jobTitle, $currentUser['id']]);

            $success = 'Profile updated successfully!';
            // Refresh current user data
            $currentUser = getCurrentUser();
            $_SESSION['full_name'] = $fullName;
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $error = 'All password fields are required.';
    } elseif (!password_verify($currentPassword, $currentUser['password'])) {
        $error = 'Current password is incorrect.';
    } elseif (strlen($newPassword) < 6) {
        $error = 'New password must be at least 6 characters long.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'New passwords do not match.';
    } else {
        // Update password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashedPassword, $currentUser['id']]);

        $success = 'Password changed successfully!';
    }
}

$pageTitle = 'Profile & Settings';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/vien-v3.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="app-container">
        <!-- Sidebar based on role -->
        <?php
        if (hasRole('admin')) {
            include 'includes/v3-admin-sidebar.php';
        } elseif (hasRole('manager')) {
            include 'includes/v3-manager-sidebar.php';
        } else {
            include 'includes/v3-employee-sidebar.php';
        }
        ?>

        <div class="main-content">
            <?php include 'includes/v3-header.php'; ?>

            <div class="content-wrapper">
                <div class="page-header">
                    <h1 class="page-title"><i class="fas fa-user-circle"></i> Profile & Settings</h1>
                    <p class="page-description">Manage your account information and preferences</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger" style="margin-bottom: var(--space-6);">
                        <?php echo e($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success" style="margin-bottom: var(--space-6);">
                        <?php echo e($success); ?>
                    </div>
                <?php endif; ?>

                <!-- Avatar Section -->
                <div class="dashboard-card" style="margin-bottom: var(--space-6);">
                    <div class="card-header">
                        <h3>Profile Picture</h3>
                    </div>
                    <div class="card-body">
                        <div style="display: flex; align-items: center; gap: var(--space-6);">
                            <div class="user-avatar-large" style="width: 120px; height: 120px; font-size: 48px; border-radius: var(--radius-lg);">
                                <?php if (!empty($currentUser['avatar']) && file_exists($currentUser['avatar'])): ?>
                                    <img src="<?php echo e($currentUser['avatar']); ?>" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover; border-radius: var(--radius-lg);">
                                <?php else: ?>
                                    <?php echo strtoupper(substr($currentUser['full_name'], 0, 1)); ?>
                                <?php endif; ?>
                            </div>
                            <div style="flex: 1;">
                                <h3 style="margin-bottom: var(--space-2);"><?php echo e($currentUser['full_name']); ?></h3>
                                <p style="color: var(--text-secondary); margin-bottom: var(--space-4);">
                                    <?php echo ucfirst($currentUser['role']); ?>
                                    <?php if ($currentUser['job_title']): ?>
                                        • <?php echo e($currentUser['job_title']); ?>
                                    <?php endif; ?>
                                </p>
                                <form method="POST" enctype="multipart/form-data" style="display: flex; align-items: center; gap: var(--space-3);">
                                    <input type="file" name="avatar" id="avatar" accept="image/*" style="display: none;" onchange="this.form.submit()">
                                    <button type="button" onclick="document.getElementById('avatar').click()" class="btn btn-primary btn-sm">
                                        <i class="fas fa-upload"></i> Upload New Picture
                                    </button>
                                    <?php if (!empty($currentUser['avatar'])): ?>
                                        <small style="color: var(--text-secondary);">Max 2MB • JPG, PNG, GIF, WebP</small>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Profile Information -->
                <div class="dashboard-card" style="margin-bottom: var(--space-6);">
                    <div class="card-header">
                        <h3>Profile Information</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_profile">

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4); margin-bottom: var(--space-4);">
                                <div>
                                    <label style="display: block; margin-bottom: var(--space-2); font-weight: 500;">Full Name *</label>
                                    <input type="text" name="full_name" value="<?php echo e($currentUser['full_name']); ?>" required style="width: 100%; padding: var(--space-2); border: 1px solid var(--border-color); border-radius: var(--radius-md); background: var(--bg-secondary);">
                                </div>

                                <div>
                                    <label style="display: block; margin-bottom: var(--space-2); font-weight: 500;">Email Address *</label>
                                    <input type="email" name="email" value="<?php echo e($currentUser['email']); ?>" required style="width: 100%; padding: var(--space-2); border: 1px solid var(--border-color); border-radius: var(--radius-md); background: var(--bg-secondary);">
                                </div>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4); margin-bottom: var(--space-4);">
                                <div>
                                    <label style="display: block; margin-bottom: var(--space-2); font-weight: 500;">Username</label>
                                    <input type="text" value="<?php echo e($currentUser['username']); ?>" disabled style="width: 100%; padding: var(--space-2); border: 1px solid var(--border-color); border-radius: var(--radius-md); background: var(--bg-tertiary); opacity: 0.6;">
                                    <small style="color: var(--text-secondary); font-size: var(--font-xs);">Username cannot be changed</small>
                                </div>

                                <div>
                                    <label style="display: block; margin-bottom: var(--space-2); font-weight: 500;">Job Title</label>
                                    <input type="text" name="job_title" value="<?php echo e($currentUser['job_title'] ?? ''); ?>" placeholder="e.g., Senior Developer" style="width: 100%; padding: var(--space-2); border: 1px solid var(--border-color); border-radius: var(--radius-md); background: var(--bg-secondary);">
                                </div>
                            </div>

                            <div style="display: flex; justify-content: flex-end;">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Change Password -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3>Change Password</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="change_password">

                            <div style="max-width: 500px;">
                                <div style="margin-bottom: var(--space-4);">
                                    <label style="display: block; margin-bottom: var(--space-2); font-weight: 500;">Current Password *</label>
                                    <input type="password" name="current_password" required style="width: 100%; padding: var(--space-2); border: 1px solid var(--border-color); border-radius: var(--radius-md); background: var(--bg-secondary);">
                                </div>

                                <div style="margin-bottom: var(--space-4);">
                                    <label style="display: block; margin-bottom: var(--space-2); font-weight: 500;">New Password *</label>
                                    <input type="password" name="new_password" required minlength="6" style="width: 100%; padding: var(--space-2); border: 1px solid var(--border-color); border-radius: var(--radius-md); background: var(--bg-secondary);">
                                    <small style="color: var(--text-secondary); font-size: var(--font-xs);">Must be at least 6 characters long</small>
                                </div>

                                <div style="margin-bottom: var(--space-4);">
                                    <label style="display: block; margin-bottom: var(--space-2); font-weight: 500;">Confirm New Password *</label>
                                    <input type="password" name="confirm_password" required minlength="6" style="width: 100%; padding: var(--space-2); border: 1px solid var(--border-color); border-radius: var(--radius-md); background: var(--bg-secondary);">
                                </div>

                                <div style="display: flex; justify-content: flex-end;">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-key"></i> Change Password
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/theme.js"></script>
</body>
</html>
