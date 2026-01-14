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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $error = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } else {
        $db = getDBConnection();

        // Check if email exists
        $stmt = $db->prepare("SELECT id, full_name, email FROM users WHERE email = ? AND is_active = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Generate reset token
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Store token in database
            $stmt = $db->prepare("
                INSERT INTO password_resets (user_id, token, expires_at, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$user['id'], $token, $expiry]);

            // Create reset link
            $resetLink = BASE_URL . "/reset-password.php?token=" . $token;

            // Send email (if email system is configured)
            // For now, display the reset link
            $success = "Password reset link has been sent to your email. <br><br>
                       <small style='color: var(--text-secondary);'>For development:
                       <a href='reset-password.php?token={$token}'>Click here to reset password</a></small>";
        } else {
            // For security, show success message even if email doesn't exist
            $success = "If an account with that email exists, a password reset link has been sent.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/vien-v3.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <img src="assets/images/neofox.png" alt="<?php echo SITE_NAME; ?>" class="auth-logo">
                <h2>Forgot Password</h2>
                <p>Enter your email address and we'll send you a link to reset your password.</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?php echo e($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo $success; ?>
                </div>
            <?php else: ?>
                <form method="POST" class="auth-form">
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email"
                               id="email"
                               name="email"
                               class="form-control"
                               placeholder="your.email@example.com"
                               required
                               autofocus
                               value="<?php echo e($_POST['email'] ?? ''); ?>">
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-paper-plane"></i> Send Reset Link
                    </button>
                </form>
            <?php endif; ?>

            <div class="auth-footer">
                <a href="login.php"><i class="fas fa-arrow-left"></i> Back to Login</a>
            </div>
        </div>
    </div>

    <script src="assets/js/theme.js"></script>
</body>
</html>
