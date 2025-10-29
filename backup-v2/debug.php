<?php
// Debug script - Place this at neofoxmedia.com/foxhole/tests/v1/debug.php
// This will help identify the issue

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Debug Information</h1>";

// Check PHP version
echo "<h2>1. PHP Version</h2>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Required: 7.4+<br>";

// Check if files exist
echo "<h2>2. File Check</h2>";
$files = [
    'login.php',
    'config/database.php',
    'config/config.php',
    'includes/functions.php'
];

foreach ($files as $file) {
    if (file_exists(__DIR__ . '/' . $file)) {
        echo "✓ {$file} exists<br>";
    } else {
        echo "✗ {$file} <strong>MISSING</strong><br>";
    }
}

// Check directory permissions
echo "<h2>3. Directory Permissions</h2>";
echo "Current directory: " . __DIR__ . "<br>";
echo "Is readable: " . (is_readable(__DIR__) ? 'Yes' : 'No') . "<br>";
echo "Is writable: " . (is_writable(__DIR__) ? 'Yes' : 'No') . "<br>";

// Test database connection
echo "<h2>4. Database Connection Test</h2>";
if (file_exists(__DIR__ . '/config/database.php')) {
    try {
        require_once __DIR__ . '/config/database.php';
        echo "✓ database.php loaded<br>";

        // Check if constants are defined
        if (defined('DB_HOST')) {
            echo "DB_HOST: " . DB_HOST . "<br>";
        } else {
            echo "✗ DB_HOST not defined<br>";
        }

        if (defined('DB_NAME')) {
            echo "DB_NAME: " . DB_NAME . "<br>";
        } else {
            echo "✗ DB_NAME not defined<br>";
        }

        // Try to connect
        if (function_exists('getDBConnection')) {
            echo "Attempting database connection...<br>";
            $db = getDBConnection();
            echo "✓ Database connected successfully!<br>";

            // Check if users table exists
            $stmt = $db->query("SHOW TABLES LIKE 'users'");
            if ($stmt->rowCount() > 0) {
                echo "✓ 'users' table exists<br>";

                // Count users
                $stmt = $db->query("SELECT COUNT(*) as count FROM users");
                $count = $stmt->fetch();
                echo "Users in database: " . $count['count'] . "<br>";
            } else {
                echo "✗ 'users' table does NOT exist - Database not imported!<br>";
            }
        } else {
            echo "✗ getDBConnection() function not found<br>";
        }
    } catch (Exception $e) {
        echo "✗ Database Error: " . $e->getMessage() . "<br>";
    }
} else {
    echo "✗ config/database.php not found<br>";
}

// Check session support
echo "<h2>5. Session Support</h2>";
echo "Session support enabled: " . (function_exists('session_start') ? 'Yes' : 'No') . "<br>";

// Check PDO support
echo "<h2>6. PDO Support</h2>";
echo "PDO available: " . (class_exists('PDO') ? 'Yes' : 'No') . "<br>";
if (class_exists('PDO')) {
    echo "PDO MySQL driver: " . (in_array('mysql', PDO::getAvailableDrivers()) ? 'Yes' : 'No') . "<br>";
}

echo "<h2>7. Test login.php directly</h2>";
echo "Now try to load login.php with errors visible...<br>";
echo '<a href="login-debug.php">Click here to test login.php</a>';
?>
