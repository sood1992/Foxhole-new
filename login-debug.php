<?php
// Debug version of login.php - Place at neofoxmedia.com/foxhole/tests/v1/login-debug.php
// This shows all errors

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Step 1: Error reporting enabled<br>";

try {
    echo "Step 2: Loading config files...<br>";

    if (!file_exists(__DIR__ . '/config/config.php')) {
        die("ERROR: config/config.php not found at " . __DIR__ . '/config/config.php');
    }
    require_once __DIR__ . '/config/config.php';
    echo "✓ config.php loaded<br>";

    if (!file_exists(__DIR__ . '/includes/functions.php')) {
        die("ERROR: includes/functions.php not found");
    }
    require_once __DIR__ . '/includes/functions.php';
    echo "✓ functions.php loaded<br>";

    echo "Step 3: Files loaded successfully<br>";
    echo "Step 4: Checking if already logged in...<br>";

    // Redirect if already logged in
    if (isLoggedIn()) {
        echo "User is logged in, would redirect to dashboard<br>";
        // Comment out redirect for testing
        // redirect('admin/index.php');
    }

    echo "Step 5: Processing form...<br>";

    $error = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        echo "POST request received<br>";

        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        echo "Username: " . htmlspecialchars($username) . "<br>";
        echo "Password length: " . strlen($password) . "<br>";

        if (empty($username) || empty($password)) {
            $error = 'Please enter both username and password.';
        } else {
            try {
                $db = getDBConnection();
                echo "✓ Database connected<br>";

                $stmt = $db->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND is_active = 1");
                $stmt->execute([$username, $username]);
                $user = $stmt->fetch();

                if ($user) {
                    echo "User found: " . $user['full_name'] . "<br>";

                    if (password_verify($password, $user['password'])) {
                        echo "✓ Password verified!<br>";
                        echo "Setting session variables...<br>";

                        // Set session variables
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['full_name'] = $user['full_name'];
                        $_SESSION['role'] = $user['role'];

                        echo "Session set. Would redirect to: ";
                        switch ($user['role']) {
                            case 'admin':
                                echo "admin/index.php<br>";
                                break;
                            case 'manager':
                                echo "manager/index.php<br>";
                                break;
                            case 'employee':
                                echo "employee/index.php<br>";
                                break;
                        }

                        echo '<a href="' . $user['role'] . '/index.php">Go to Dashboard</a>';
                    } else {
                        $error = 'Invalid password';
                        echo "✗ Password verification failed<br>";
                    }
                } else {
                    $error = 'User not found or inactive';
                    echo "✗ User not found<br>";
                }
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
                echo "✗ Database error: " . $e->getMessage() . "<br>";
            }
        }
    }

} catch (Exception $e) {
    die("FATAL ERROR: " . $e->getMessage() . "<br>File: " . $e->getFile() . "<br>Line: " . $e->getLine());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Debug - Neofox</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <h1>Neofox</h1>
                <p>Productivity Management Platform (DEBUG MODE)</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error); ?>
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
