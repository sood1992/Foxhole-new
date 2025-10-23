<?php
// Enable error reporting for debugging (remove after setup)
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

                // Update last login
                $updateStmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $updateStmt->execute([$user['id']]);

                // Redirect based on role
                switch ($user['role']) {
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
    <title>Login - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/modern-style.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <h1>Neofox</h1>
                <p>Productivity Management Platform</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php echo e($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="login-form">
                <div class="form-group">
                    <label for="username">Username or Email</label>
                    <input type="text" id="username" name="username" required autofocus>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <button type="submit" class="btn btn-primary btn-block">Login</button>
            </form>

            <div class="login-demo">
                <h3>Demo Credentials:</h3>
                <div class="demo-accounts">
                    <div class="demo-account">
                        <strong>Admin:</strong> admin / admin123
                    </div>
                    <div class="demo-account">
                        <strong>Manager:</strong> john_manager / admin123
                    </div>
                    <div class="demo-account">
                        <strong>Employee:</strong> sarah_employee / admin123
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
