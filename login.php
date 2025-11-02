<?php
/**
 * Foxhole V3 Login Page
 * Vien Admin Panel Design
 */

// Disable error display for production
error_reporting(0);
ini_set('display_errors', 0);

require_once 'config/config.php';
require_once 'includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    switch ($_SESSION['role']) {
        case 'admin':
            redirect('admin/index.php');
            break;
        case 'manager':
            redirect('manager/index.php');
            break;
        case 'employee':
            redirect('employee/index.php');
            break;
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        try {
            $db = getDBConnection();
            $stmt = $db->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND is_active = 1");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];

                // Get all user roles for multi-role support
                $rolesStmt = $db->prepare("SELECT role, is_primary FROM user_roles WHERE user_id = ? ORDER BY is_primary DESC");
                $rolesStmt->execute([$user['id']]);
                $userRoles = $rolesStmt->fetchAll();

                // If user has roles in user_roles table, use those
                if (!empty($userRoles)) {
                    $primaryRole = $userRoles[0]['role'];
                    $_SESSION['role'] = $primaryRole;
                    $_SESSION['active_role'] = $primaryRole;
                    $_SESSION['all_roles'] = array_column($userRoles, 'role');
                } else {
                    // Fallback to users table role
                    $_SESSION['active_role'] = $user['role'];
                    $_SESSION['all_roles'] = [$user['role']];
                }

                // Update last login
                $updateStmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $updateStmt->execute([$user['id']]);

                // Redirect based on active role
                $activeRole = $_SESSION['active_role'];
                switch ($activeRole) {
                    case 'admin':
                        redirect('admin/index.php');
                        break;
                    case 'manager':
                        redirect('manager/index.php');
                        break;
                    case 'employee':
                        redirect('employee/index.php');
                        break;
                }
            } else {
                $error = 'Invalid username or password.';
            }
        } catch (PDOException $e) {
            $error = 'Login failed. Please try again.';
            error_log("Login error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?> V3</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/vien-v3.css">
</head>
<body>
    <div class="auth-page">
        <div class="auth-card">
            <div class="auth-logo">
                <img src="assets/images/neofox.png" alt="<?php echo SITE_NAME; ?>">
            </div>

            <h2>Welcome Back!</h2>
            <p>Sign in to continue to your dashboard</p>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <div class="alert-icon">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <div class="alert-content">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">Username or Email <span class="required">*</span></label>
                    <input type="text" id="username" name="username" class="form-control"
                           placeholder="Enter your username or email" required autofocus>
                </div>

                <div class="form-group">
                    <label for="password">Password <span class="required">*</span></label>
                    <input type="password" id="password" name="password" class="form-control"
                           placeholder="Enter your password" required>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i> Sign In
                    </button>
                </div>
            </form>

            <div class="auth-links">
                <a href="forgot-password.php">Forgot your password?</a>
            </div>
        </div>

        <!-- Version Badge -->
        <div style="position: fixed; bottom: 20px; right: 20px; background: rgba(255,255,255,0.9); padding: 8px 16px; border-radius: 20px; font-size: 12px; font-weight: 600; color: #667eea; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
            <i class="fas fa-sparkles"></i> Foxhole V3
        </div>
    </div>

    <script>
    // Add subtle animation to form on load
    document.addEventListener('DOMContentLoaded', function() {
        const card = document.querySelector('.auth-card');
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';

        setTimeout(() => {
            card.style.transition = 'all 0.5s ease-out';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, 100);
    });

    // Add enter key handling
    document.getElementById('password').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            this.form.submit();
        }
    });
    </script>
</body>
</html>
