<?php
// Fix employee user - run this once
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('DB_HOST', 'localhost');
define('DB_NAME', 'sunburni_foxholev1');
define('DB_USER', 'sunburni_foxholev1');
define('DB_PASS', 'sunburni_foxholev1');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<h2>Checking sarah_employee account...</h2>";

    // Check if sarah_employee exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute(['sarah_employee']);
    $user = $stmt->fetch();

    if ($user) {
        echo "<p>User exists. Testing password...</p>";

        if (password_verify('admin123', $user['password'])) {
            echo "<p style='color:green;'>✓ Password works! Login should succeed.</p>";
        } else {
            echo "<p style='color:red;'>✗ Password doesn't match. Resetting...</p>";

            $newPass = password_hash('admin123', PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password = ? WHERE username = 'sarah_employee'")->execute([$newPass]);

            echo "<p style='color:green;'>✓ Password reset! Try logging in now.</p>";
        }
    } else {
        echo "<p style='color:red;'>User doesn't exist. Creating...</p>";

        $newPass = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->prepare("
            INSERT INTO users (username, email, password, full_name, role, job_title, is_active)
            VALUES (?, ?, ?, ?, ?, ?, 1)
        ")->execute(['sarah_employee', 'sarah@neofox.com', $newPass, 'Sarah Smith', 'employee', 'Video Editor']);

        echo "<p style='color:green;'>✓ User created! Try logging in now.</p>";
    }

    // Also check john_manager
    echo "<h2>Checking john_manager account...</h2>";
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute(['john_manager']);
    $user = $stmt->fetch();

    if ($user) {
        if (password_verify('admin123', $user['password'])) {
            echo "<p style='color:green;'>✓ Manager password works!</p>";
        } else {
            $newPass = password_hash('admin123', PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password = ? WHERE username = 'john_manager'")->execute([$newPass]);
            echo "<p style='color:green;'>✓ Manager password reset!</p>";
        }
    } else {
        $newPass = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->prepare("
            INSERT INTO users (username, email, password, full_name, role, job_title, is_active)
            VALUES (?, ?, ?, ?, ?, ?, 1)
        ")->execute(['john_manager', 'john@neofox.com', $newPass, 'John Doe', 'manager', 'Project Manager']);
        echo "<p style='color:green;'>✓ Manager created!</p>";
    }

    echo "<h2>All Login Credentials:</h2>";
    echo "<ul>";
    echo "<li><strong>Admin:</strong> admin / admin123</li>";
    echo "<li><strong>Manager:</strong> john_manager / admin123</li>";
    echo "<li><strong>Employee:</strong> sarah_employee / admin123</li>";
    echo "</ul>";

} catch (Exception $e) {
    echo "<p style='color:red;'>Error: " . $e->getMessage() . "</p>";
}
?>
