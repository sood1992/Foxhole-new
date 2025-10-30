<?php
/**
 * Version Check - Verify file deployment
 * Access: https://neofoxmedia.com/foxhole/tests/v1/version-check.php
 */
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Version Check</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .box { background: white; padding: 20px; margin: 10px 0; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .success { color: #28a745; font-weight: bold; }
        .warning { color: #ffc107; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        h1 { color: #333; }
        code { background: #f0f0f0; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
    <h1>🔍 Foxhole Version Check</h1>

    <div class="box">
        <h2>Deployment Status</h2>
        <p class="success">✓ This file was deployed successfully!</p>
        <p><strong>Deployed:</strong> 2025-10-30</p>
        <p><strong>Version:</strong> 2.0 (Redirect Loop Fix)</p>
    </div>

    <div class="box">
        <h2>File Modification Times</h2>
        <table border="1" cellpadding="8" style="border-collapse: collapse; width: 100%;">
            <tr style="background: #f8f9fa;">
                <th>File</th>
                <th>Last Modified</th>
                <th>Status</th>
            </tr>
            <?php
            $files = [
                'manager/index.php' => 'Manager Dashboard',
                'employee/index.php' => 'Employee Dashboard',
                'admin/index.php' => 'Admin Dashboard',
                'login.php' => 'Login Page',
                'config/config.php' => 'Config File',
                'manager/debug-session.php' => 'Debug Script'
            ];

            foreach ($files as $file => $name) {
                $fullPath = __DIR__ . '/' . $file;
                $exists = file_exists($fullPath);
                $modTime = $exists ? date('Y-m-d H:i:s', filemtime($fullPath)) : 'N/A';
                $status = $exists ? '<span class="success">✓ Exists</span>' : '<span class="error">✗ Missing</span>';

                // Check if modified today (2025-10-30 or later)
                $isRecent = $exists && filemtime($fullPath) >= strtotime('2025-10-30');
                if ($isRecent) {
                    $status = '<span class="success">✓ Updated</span>';
                } elseif ($exists) {
                    $status = '<span class="warning">⚠ Old Version</span>';
                }

                echo "<tr>";
                echo "<td><strong>$name</strong><br><code>$file</code></td>";
                echo "<td>$modTime</td>";
                echo "<td>$status</td>";
                echo "</tr>";
            }
            ?>
        </table>
    </div>

    <div class="box">
        <h2>What to Do Next</h2>
        <?php
        $managerFile = __DIR__ . '/manager/index.php';
        $isUpdated = file_exists($managerFile) && filemtime($managerFile) >= strtotime('2025-10-30');
        ?>

        <?php if (!$isUpdated): ?>
            <p class="error">⚠ <strong>Files NOT updated!</strong></p>
            <ol>
                <li>Make sure you pulled the latest changes from GitHub</li>
                <li>Run: <code>git pull origin claude/fix-sql-syntax-error-011CUd4t57fQejtAHQDmw3Vs</code></li>
                <li>If using caching, clear PHP opcode cache:
                    <ul>
                        <li>OPcache: Restart PHP-FPM or Apache</li>
                        <li>Or add to php.ini: <code>opcache.revalidate_freq=0</code> for development</li>
                    </ul>
                </li>
                <li>Clear browser cache (Ctrl+Shift+R or Cmd+Shift+R)</li>
                <li>Refresh this page to verify</li>
            </ol>
        <?php else: ?>
            <p class="success">✓ <strong>Files are updated!</strong></p>
            <ol>
                <li>First, access the debug script: <a href="manager/debug-session.php">manager/debug-session.php</a></li>
                <li>The debug script will show you exactly what's happening with your session</li>
                <li>Make sure you're logged in with a manager account (john_manager / admin123)</li>
                <li>Then try: <a href="manager/index.php">manager/index.php</a></li>
            </ol>
        <?php endif; ?>
    </div>

    <div class="box">
        <h2>Useful Links</h2>
        <ul>
            <li><a href="manager/debug-session.php"><strong>Debug Script (Start Here!)</strong></a> - Diagnose the issue</li>
            <li><a href="login.php">Login Page</a> - Login as manager</li>
            <li><a href="manager/index.php">Manager Dashboard</a> - The page with the issue</li>
            <li><a href="version-check.php">Refresh This Page</a> - Re-check versions</li>
        </ul>
    </div>

</body>
</html>
