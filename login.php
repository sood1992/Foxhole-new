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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        /* Animated background shapes */
        body::before,
        body::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.05);
            animation: float 20s infinite;
        }

        body::before {
            width: 500px;
            height: 500px;
            top: -250px;
            left: -250px;
        }

        body::after {
            width: 300px;
            height: 300px;
            bottom: -150px;
            right: -150px;
            animation-delay: -10s;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            33% { transform: translate(30px, -50px) rotate(120deg); }
            66% { transform: translate(-20px, 20px) rotate(240deg); }
        }

        .login-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 480px;
        }

        .login-box {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3), 0 0 0 1px rgba(255, 255, 255, 0.2);
            padding: 48px;
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .logo {
            width: 180px;
            height: auto;
            margin: 0 auto 24px;
            display: block;
            filter: drop-shadow(0 4px 12px rgba(0, 0, 0, 0.1));
        }

        .login-header h1 {
            font-size: 32px;
            font-weight: 900;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 8px;
        }

        .login-header p {
            color: #64748b;
            font-size: 15px;
            font-weight: 500;
        }

        .alert {
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-size: 14px;
            font-weight: 500;
            animation: shake 0.5s ease-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 8px;
        }

        .form-group input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 15px;
            font-family: inherit;
            transition: all 0.2s ease;
            background: #f8fafc;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }

        .btn {
            width: 100%;
            padding: 16px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 16px rgba(102, 126, 234, 0.4);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 24px rgba(102, 126, 234, 0.5);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .login-demo {
            margin-top: 40px;
            padding-top: 32px;
            border-top: 1px solid #e2e8f0;
        }

        .login-demo h3 {
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            margin-bottom: 16px;
            text-align: center;
        }

        .demo-accounts {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .demo-account {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            padding: 14px 18px;
            border-radius: 10px;
            font-size: 14px;
            color: #475569;
            border: 1px solid #e2e8f0;
            transition: all 0.2s ease;
        }

        .demo-account:hover {
            transform: translateX(4px);
            border-color: #cbd5e1;
            background: white;
        }

        .demo-account strong {
            color: #1e293b;
            font-weight: 700;
            display: inline-block;
            min-width: 90px;
        }

        .powered-by {
            text-align: center;
            margin-top: 32px;
            font-size: 13px;
            color: rgba(255, 255, 255, 0.8);
            font-weight: 500;
        }

        .powered-by a {
            color: white;
            text-decoration: none;
            font-weight: 700;
        }

        @media (max-width: 640px) {
            .login-box {
                padding: 32px 24px;
            }

            .login-header h1 {
                font-size: 28px;
            }

            .logo {
                width: 140px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <img src="assets/images/neofox.png" alt="<?php echo SITE_NAME; ?>" class="logo">
                <h1><?php echo SITE_NAME; ?></h1>
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

                <button type="submit" class="btn btn-primary">Sign In</button>
            </form>

            <div class="login-demo">
                <h3>Demo Credentials</h3>
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

        <div class="powered-by">
            Powered by <a href="#"><?php echo SITE_NAME; ?></a>
        </div>
    </div>
</body>
</html>
