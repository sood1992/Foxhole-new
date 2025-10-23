<?php
// Complete Employee Login Fix - Run this once
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<style>
body { font-family: sans-serif; padding: 20px; max-width: 800px; margin: 0 auto; }
h2 { color: #3b82f6; }
.success { color: #10b981; font-weight: bold; }
.error { color: #ef4444; font-weight: bold; }
.info { color: #64748b; }
pre { background: #f1f5f9; padding: 15px; border-radius: 8px; overflow-x: auto; }
table { width: 100%; border-collapse: collapse; margin: 20px 0; }
th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e2e8f0; }
th { background: #f8fafc; font-weight: 600; }
.btn { display: inline-block; padding: 10px 20px; background: #3b82f6; color: white; text-decoration: none; border-radius: 6px; margin: 10px 5px; }
</style>";

echo "<h1>🔧 Employee Login Fix Tool</h1>";

define('DB_HOST', 'localhost');
define('DB_NAME', 'sunburni_foxholev1');
define('DB_USER', 'sunburni_foxholev1');
define('DB_PASS', 'sunburni_foxholev1');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<p class='success'>✓ Database connected successfully!</p>";

    // Step 1: Check all users
    echo "<h2>Step 1: Current Users in Database</h2>";
    $stmt = $pdo->query("SELECT id, username, email, full_name, role, is_active FROM users ORDER BY role, username");
    $users = $stmt->fetchAll();

    if (empty($users)) {
        echo "<p class='error'>✗ No users found in database! This is the problem.</p>";
        echo "<p class='info'>Creating all three users now...</p>";
    } else {
        echo "<table>";
        echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Full Name</th><th>Role</th><th>Active</th></tr>";
        foreach ($users as $user) {
            $activeStatus = $user['is_active'] ? '<span class="success">Yes</span>' : '<span class="error">No</span>';
            echo "<tr>";
            echo "<td>{$user['id']}</td>";
            echo "<td><strong>{$user['username']}</strong></td>";
            echo "<td>{$user['email']}</td>";
            echo "<td>{$user['full_name']}</td>";
            echo "<td>{$user['role']}</td>";
            echo "<td>{$activeStatus}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    // Step 2: Create/Reset all users
    echo "<h2>Step 2: Creating/Resetting User Accounts</h2>";

    $defaultUsers = [
        ['admin', 'admin@neofox.com', 'Admin User', 'admin', 'CEO'],
        ['john_manager', 'john@neofox.com', 'John Doe', 'manager', 'Project Manager'],
        ['sarah_employee', 'sarah@neofox.com', 'Sarah Smith', 'employee', 'Video Editor']
    ];

    foreach ($defaultUsers as $userData) {
        list($username, $email, $fullName, $role, $jobTitle) = $userData;

        echo "<h3>Processing: {$fullName} ({$username})</h3>";

        // Check if user exists
        $stmt = $pdo->prepare("SELECT id, password FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $existingUser = $stmt->fetch();

        // Generate new password hash
        $newPasswordHash = password_hash('admin123', PASSWORD_DEFAULT);

        if ($existingUser) {
            echo "<p class='info'>User exists. Updating password and ensuring active...</p>";

            // Update user
            $stmt = $pdo->prepare("
                UPDATE users
                SET password = ?,
                    email = ?,
                    full_name = ?,
                    role = ?,
                    job_title = ?,
                    is_active = 1
                WHERE username = ?
            ");
            $stmt->execute([$newPasswordHash, $email, $fullName, $role, $jobTitle, $username]);

            echo "<p class='success'>✓ Updated {$username}</p>";
        } else {
            echo "<p class='info'>User doesn't exist. Creating new...</p>";

            // Insert new user
            $stmt = $pdo->prepare("
                INSERT INTO users (username, email, password, full_name, role, job_title, is_active, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 1, NOW())
            ");
            $stmt->execute([$username, $email, $newPasswordHash, $fullName, $role, $jobTitle]);

            echo "<p class='success'>✓ Created {$username}</p>";
        }

        // Verify the password works
        $stmt = $pdo->prepare("SELECT password FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (password_verify('admin123', $user['password'])) {
            echo "<p class='success'>✓ Password verification TEST PASSED for {$username}</p>";
        } else {
            echo "<p class='error'>✗ Password verification FAILED for {$username}</p>";
        }
    }

    // Step 3: Final verification
    echo "<h2>Step 3: Final Verification</h2>";
    echo "<p class='info'>Testing all login credentials...</p>";

    $testResults = [];
    foreach ($defaultUsers as $userData) {
        $username = $userData[0];

        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user) {
            $passwordWorks = password_verify('admin123', $user['password']);
            $testResults[] = [
                'username' => $username,
                'exists' => true,
                'active' => $user['is_active'],
                'password_works' => $passwordWorks,
                'status' => $passwordWorks ? 'READY' : 'FAILED'
            ];
        } else {
            $testResults[] = [
                'username' => $username,
                'exists' => false,
                'active' => false,
                'password_works' => false,
                'status' => 'NOT FOUND'
            ];
        }
    }

    echo "<table>";
    echo "<tr><th>Username</th><th>Exists</th><th>Active</th><th>Password Works</th><th>Status</th></tr>";
    foreach ($testResults as $result) {
        $statusClass = $result['status'] === 'READY' ? 'success' : 'error';
        echo "<tr>";
        echo "<td><strong>{$result['username']}</strong></td>";
        echo "<td>" . ($result['exists'] ? '✓' : '✗') . "</td>";
        echo "<td>" . ($result['active'] ? '✓' : '✗') . "</td>";
        echo "<td>" . ($result['password_works'] ? '✓' : '✗') . "</td>";
        echo "<td class='{$statusClass}'>{$result['status']}</td>";
        echo "</tr>";
    }
    echo "</table>";

    // Step 4: Summary
    echo "<h2>✅ Summary</h2>";

    $allWorking = true;
    foreach ($testResults as $result) {
        if ($result['status'] !== 'READY') {
            $allWorking = false;
            break;
        }
    }

    if ($allWorking) {
        echo "<div style='background: #d1fae5; padding: 20px; border-radius: 8px; border-left: 4px solid #10b981;'>";
        echo "<p class='success' style='font-size: 18px;'>🎉 ALL ACCOUNTS ARE WORKING!</p>";
        echo "<p>You can now login with any of these credentials:</p>";
        echo "<ul>";
        echo "<li><strong>Admin:</strong> admin / admin123</li>";
        echo "<li><strong>Manager:</strong> john_manager / admin123</li>";
        echo "<li><strong>Employee:</strong> sarah_employee / admin123</li>";
        echo "</ul>";
        echo "</div>";

        echo "<h3>Next Steps:</h3>";
        echo "<ol>";
        echo "<li>Try logging in at: <a href='login.php' class='btn'>Go to Login Page</a></li>";
        echo "<li>After confirming all logins work, delete this file for security</li>";
        echo "<li>Change all passwords from the admin panel</li>";
        echo "</ol>";
    } else {
        echo "<div style='background: #fee2e2; padding: 20px; border-radius: 8px; border-left: 4px solid #ef4444;'>";
        echo "<p class='error' style='font-size: 18px;'>❌ Some accounts have issues</p>";
        echo "<p>Please contact support or check the database manually.</p>";
        echo "</div>";
    }

    // Debug info
    echo "<h2>Debug Information</h2>";
    echo "<details>";
    echo "<summary>Click to view database details</summary>";
    echo "<pre>";
    echo "Database: " . DB_NAME . "\n";
    echo "Host: " . DB_HOST . "\n";
    echo "User: " . DB_USER . "\n\n";

    echo "PHP Version: " . phpversion() . "\n";
    echo "PDO Available: " . (extension_loaded('pdo') ? 'Yes' : 'No') . "\n";
    echo "Password Hash Default: " . PASSWORD_DEFAULT . "\n";

    // Show actual password hashes (for debugging)
    echo "\nPassword Hashes in Database:\n";
    $stmt = $pdo->query("SELECT username, password FROM users ORDER BY username");
    while ($row = $stmt->fetch()) {
        echo $row['username'] . ": " . substr($row['password'], 0, 60) . "...\n";
    }
    echo "</pre>";
    echo "</details>";

} catch (PDOException $e) {
    echo "<p class='error'>✗ Database Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<h3>Troubleshooting:</h3>";
    echo "<ul>";
    echo "<li>Verify database name is: <strong>" . DB_NAME . "</strong></li>";
    echo "<li>Check database credentials in config/database.php</li>";
    echo "<li>Ensure database exists in cPanel MySQL Databases</li>";
    echo "<li>Verify database user has ALL PRIVILEGES</li>";
    echo "</ul>";
}
?>

<hr>
<p style="text-align: center; color: #64748b; font-size: 12px;">
    Neofox Productivity Platform - Employee Login Fix Tool v2.0<br>
    After all accounts work, delete this file for security.
</p>
