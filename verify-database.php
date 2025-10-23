<?php
// Quick database verification and repair script
// Access this at: https://neofoxmedia.com/foxhole/tests/v1/verify-database.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Database Verification & Repair</h1>";
echo "<style>body{font-family:sans-serif;padding:20px;} .success{color:green;} .error{color:red;} .info{color:blue;} pre{background:#f5f5f5;padding:10px;border-radius:5px;}</style>";

// Database credentials
define('DB_HOST', 'localhost');
define('DB_NAME', 'sunburni_foxholev1');
define('DB_USER', 'sunburni_foxholev1');
define('DB_PASS', 'sunburni_foxholev1');

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<p class='success'>✓ Database connected successfully!</p>";

    // Check if users table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() == 0) {
        echo "<p class='error'>✗ Users table does NOT exist!</p>";
        echo "<p class='info'>Creating users table now...</p>";

        // Create users table
        $pdo->exec("
            CREATE TABLE users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) UNIQUE NOT NULL,
                email VARCHAR(100) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                full_name VARCHAR(100) NOT NULL,
                role ENUM('admin', 'manager', 'employee') NOT NULL DEFAULT 'employee',
                job_title VARCHAR(100),
                avatar VARCHAR(255),
                is_active TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_login TIMESTAMP NULL,
                INDEX idx_role (role),
                INDEX idx_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        echo "<p class='success'>✓ Users table created!</p>";
    } else {
        echo "<p class='success'>✓ Users table exists</p>";
    }

    // Check if default users exist
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $count = $stmt->fetch()['count'];

    echo "<p class='info'>Users in database: <strong>{$count}</strong></p>";

    if ($count == 0) {
        echo "<p class='info'>No users found. Creating default users...</p>";

        // Create default users with bcrypt hashed passwords
        $adminPass = password_hash('admin123', PASSWORD_DEFAULT);

        $pdo->exec("
            INSERT INTO users (username, email, password, full_name, role, job_title, is_active) VALUES
            ('admin', 'admin@neofox.com', '{$adminPass}', 'Admin User', 'admin', 'CEO', 1),
            ('john_manager', 'john@neofox.com', '{$adminPass}', 'John Doe', 'manager', 'Project Manager', 1),
            ('sarah_employee', 'sarah@neofox.com', '{$adminPass}', 'Sarah Smith', 'employee', 'Video Editor', 1)
        ");

        echo "<p class='success'>✓ Default users created!</p>";
    } else {
        echo "<p class='success'>✓ Users exist in database</p>";
    }

    // Display all users
    echo "<h2>Current Users:</h2>";
    $stmt = $pdo->query("SELECT username, email, full_name, role, is_active FROM users");
    $users = $stmt->fetchAll();

    echo "<table border='1' cellpadding='10' style='border-collapse:collapse;width:100%;'>";
    echo "<tr><th>Username</th><th>Email</th><th>Full Name</th><th>Role</th><th>Active</th></tr>";
    foreach ($users as $user) {
        echo "<tr>";
        echo "<td><strong>{$user['username']}</strong></td>";
        echo "<td>{$user['email']}</td>";
        echo "<td>{$user['full_name']}</td>";
        echo "<td>{$user['role']}</td>";
        echo "<td>" . ($user['is_active'] ? 'Yes' : 'No') . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    // Test password verification
    echo "<h2>Password Verification Test:</h2>";
    $stmt = $pdo->query("SELECT username, password FROM users WHERE username = 'admin'");
    $admin = $stmt->fetch();

    if ($admin) {
        $testPass = 'admin123';
        if (password_verify($testPass, $admin['password'])) {
            echo "<p class='success'>✓ Password verification works! You can login with admin/admin123</p>";
        } else {
            echo "<p class='error'>✗ Password verification failed. Resetting password...</p>";
            $newPass = password_hash('admin123', PASSWORD_DEFAULT);
            $pdo->exec("UPDATE users SET password = '{$newPass}' WHERE username = 'admin'");
            echo "<p class='success'>✓ Password reset! Try logging in again with admin/admin123</p>";
        }
    }

    echo "<h2>Next Steps:</h2>";
    echo "<ol>";
    echo "<li>Try logging in at: <a href='login.php'>login.php</a></li>";
    echo "<li>Username: <strong>admin</strong></li>";
    echo "<li>Password: <strong>admin123</strong></li>";
    echo "<li>After successful login, delete this file for security!</li>";
    echo "</ol>";

} catch (PDOException $e) {
    echo "<p class='error'>✗ Database Error: " . $e->getMessage() . "</p>";
    echo "<h3>Troubleshooting:</h3>";
    echo "<ul>";
    echo "<li>Verify database exists in cPanel MySQL Databases</li>";
    echo "<li>Check credentials in config/database.php match exactly</li>";
    echo "<li>Ensure database user has ALL PRIVILEGES</li>";
    echo "</ul>";
}
?>
