<?php
require_once 'config/config.php';

// If already logged in, redirect to appropriate dashboard
if (isLoggedIn()) {
    $role = $_SESSION['role'];
    if ($role === 'admin') {
        redirect('admin/index.php');
    } elseif ($role === 'manager') {
        redirect('manager/index.php');
    } else {
        redirect('employee/index.php');
    }
}

$error = '';
$success = '';
$validToken = false;
$userId = null;

$token = $_GET['token'] ?? '';

if (empty($token)) {
    $error = 'Invalid password reset link.';
} else {
    $db = getDBConnection();

    // Verify token
    $stmt = $db->prepare("
        SELECT pr.user_id, pr.expires_at, u.email, u.full_name
        FROM password_resets pr
        JOIN users u ON pr.user_id = u.id
        WHERE pr.token = ? AND pr.used = 0
    ");
    $stmt->execute([$token]);
    $resetData = $stmt->fetch();

    if (!$resetData) {
        $error = 'Invalid or already used password reset link.';
    } elseif (strtotime($resetData['expires_at']) < time()) {
        $error = 'This password reset link has expired. Please request a new one.';
    } else {
        $validToken = true;
        $userId = $resetData['user_id'];
    }
}

// Process password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($password)) {
        $error = 'Password is required.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        // Update password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashedPassword, $userId]);

        // Mark token as used
        $stmt = $db->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
        $stmt->execute([$token]);

        $success = 'Your password has been reset successfully. You can now log in with your new password.';
        $validToken = false; // Hide the form
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/vien-v3.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <img src="assets/images/neofox.png" alt="<?php echo SITE_NAME; ?>" class="auth-logo">
                <h2>Reset Password</h2>
                <p>Enter your new password below.</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?php echo e($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo $success; ?>
                    <div style="margin-top: 16px;">
                        <a href="login.php" class="btn btn-primary">
                            <i class="fas fa-sign-in-alt"></i> Go to Login
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($validToken && !$success): ?>
                <form method="POST" class="auth-form">
                    <div class="form-group">
                        <label for="password">New Password</label>
                        <input type="password"
                               id="password"
                               name="password"
                               class="form-control"
                               placeholder="Enter new password"
                               required
                               minlength="6"
                               autofocus>
                        <small class="form-text">Must be at least 6 characters long</small>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password"
                               id="confirm_password"
                               name="confirm_password"
                               class="form-control"
                               placeholder="Confirm new password"
                               required
                               minlength="6">
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-key"></i> Reset Password
                    </button>
                </form>
            <?php endif; ?>

            <?php if (!$validToken && !$success): ?>
                <div class="auth-footer">
                    <a href="forgot-password.php"><i class="fas fa-redo"></i> Request New Reset Link</a>
                    <br>
                    <a href="login.php"><i class="fas fa-arrow-left"></i> Back to Login</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="assets/js/theme.js"></script>
</body>
</html>
