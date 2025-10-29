<?php
// Database Upgrade Runner
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<style>
body { font-family: 'Inter', sans-serif; padding: 20px; max-width: 1000px; margin: 0 auto; background: #f8fafc; }
h1, h2 { color: #3b82f6; }
.success { color: #10b981; font-weight: 600; }
.error { color: #ef4444; font-weight: 600; }
.info { color: #64748b; }
.box { background: white; padding: 20px; border-radius: 12px; margin: 20px 0; border: 1px solid #e2e8f0; }
pre { background: #f1f5f9; padding: 15px; border-radius: 8px; overflow-x: auto; }
.btn { display: inline-block; padding: 10px 20px; background: #3b82f6; color: white; text-decoration: none; border-radius: 6px; margin: 10px 5px; }
</style>";

echo "<h1>🔧 Neofox Platform Database Upgrade</h1>";

define('DB_HOST', 'localhost');
define('DB_NAME', 'sunburni_foxholev1');
define('DB_USER', 'sunburni_foxholev1');
define('DB_PASS', 'sunburni_foxholev1');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<div class='box'><p class='success'>✓ Database connected successfully!</p></div>";

    // Read upgrade SQL file
    if (!file_exists('upgrade-schema.sql')) {
        throw new Exception("upgrade-schema.sql file not found!");
    }

    $sql = file_get_contents('upgrade-schema.sql');

    echo "<div class='box'>";
    echo "<h2>Executing Database Upgrades...</h2>";

    // Split by semicolons and execute each statement
    $statements = array_filter(array_map('trim', explode(';', $sql)));

    $executed = 0;
    $errors = [];

    foreach ($statements as $statement) {
        // Skip comments and empty statements
        if (empty($statement) || strpos($statement, '--') === 0 || strpos($statement, 'SELECT') === 0) {
            continue;
        }

        try {
            $pdo->exec($statement);
            $executed++;

            // Show what was executed
            $firstLine = strtok($statement, "\n");
            echo "<p class='success'>✓ " . htmlspecialchars(substr($firstLine, 0, 80)) . "...</p>";

        } catch (PDOException $e) {
            // Some errors are OK (like table already exists)
            if (strpos($e->getMessage(), 'already exists') === false &&
                strpos($e->getMessage(), 'Duplicate column') === false) {
                $errors[] = $e->getMessage();
                echo "<p class='error'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
            } else {
                echo "<p class='info'>⊘ Skipped (already exists)</p>";
            }
        }
    }

    echo "</div>";

    // Summary
    echo "<div class='box'>";
    echo "<h2>✅ Upgrade Complete!</h2>";
    echo "<p>Successfully executed <strong>{$executed}</strong> database statements.</p>";

    if (!empty($errors)) {
        echo "<p class='error'>Encountered " . count($errors) . " errors (some may be OK).</p>";
    }

    echo "<h3>New Features Enabled:</h3>";
    echo "<ul style='line-height: 2;'>";
    echo "<li>📎 <strong>File Uploads</strong> - Attach files to tasks and projects</li>";
    echo "<li>🔔 <strong>Notifications</strong> - Real-time alerts and updates</li>";
    echo "<li>💬 <strong>Team Chat</strong> - Built-in messaging system</li>";
    echo "<li>🔗 <strong>Task Dependencies</strong> - Link related tasks</li>";
    echo "<li>💰 <strong>Budget Tracking</strong> - Monitor project costs</li>";
    echo "<li>📊 <strong>Activity Log</strong> - Track all system actions</li>";
    echo "<li>🎯 <strong>Milestones</strong> - Project milestone tracking</li>";
    echo "<li>💵 <strong>Expenses</strong> - Track project expenses</li>";
    echo "</ul>";

    echo "<h3>Next Steps:</h3>";
    echo "<ol style='line-height: 2;'>";
    echo "<li>Test the new features in the admin panel</li>";
    echo "<li>Try bulk import at <a href='admin/bulk-import.php'>Bulk Import</a></li>";
    echo "<li>Explore new features as they're released</li>";
    echo "<li><strong>Delete this file</strong> after upgrade (security)</li>";
    echo "</ol>";

    echo "<a href='login.php' class='btn'>Go to Login Page</a>";
    echo "<a href='admin/bulk-import.php' class='btn'>Try Bulk Import</a>";

    echo "</div>";

    // Show created tables
    echo "<div class='box'>";
    echo "<h3>📋 Database Tables</h3>";
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "<p>Total tables: <strong>" . count($tables) . "</strong></p>";
    echo "<details><summary>Click to view all tables</summary>";
    echo "<pre>" . implode("\n", $tables) . "</pre>";
    echo "</details>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='box'>";
    echo "<p class='error'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<h3>Troubleshooting:</h3>";
    echo "<ul>";
    echo "<li>Verify database credentials are correct</li>";
    echo "<li>Check if upgrade-schema.sql file exists</li>";
    echo "<li>Ensure database user has CREATE TABLE privileges</li>";
    echo "</ul>";
    echo "</div>";
}
?>

<hr>
<p style="text-align: center; color: #64748b; font-size: 12px; margin-top: 40px;">
    Neofox Productivity Platform - Database Upgrade Tool<br>
    <strong>Security:</strong> Delete this file after successful upgrade.
</p>
