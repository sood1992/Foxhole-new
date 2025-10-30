<!DOCTYPE html>
<html>
<head>
    <title>Permissions Test</title>
    <style>
        body { font-family: Arial; padding: 20px; }
        .error { color: red; font-weight: bold; }
        .success { color: green; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        pre { background: #f0f0f0; padding: 10px; }
    </style>
</head>
<body>
    <h1>File Permissions Test</h1>
    <?php
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    echo "<h2>1. Sessions Directory Check</h2>";
    $sessionsDir = dirname(__DIR__) . '/sessions';
    echo "<p>Sessions directory: <code>$sessionsDir</code></p>";

    if (file_exists($sessionsDir)) {
        echo "<p class='success'>✓ Directory exists</p>";

        $perms = fileperms($sessionsDir);
        $permsOctal = substr(sprintf('%o', $perms), -4);
        echo "<p>Permissions: <code>$permsOctal</code></p>";

        $owner = posix_getpwuid(fileowner($sessionsDir));
        echo "<p>Owner: <code>" . $owner['name'] . "</code></p>";

        if (is_writable($sessionsDir)) {
            echo "<p class='success'>✓ Directory is writable by current user</p>";
        } else {
            echo "<p class='error'>✗ Directory is NOT writable by current user!</p>";
            echo "<p class='warning'>⚠ This will cause session_start() to fail!</p>";
        }
    } else {
        echo "<p class='error'>✗ Directory does not exist</p>";
    }

    echo "<h2>2. Current PHP User</h2>";
    $currentUser = posix_getpwuid(posix_geteuid());
    echo "<p>PHP is running as: <code>" . $currentUser['name'] . "</code></p>";
    echo "<p>UID: <code>" . posix_geteuid() . "</code></p>";
    echo "<p>GID: <code>" . posix_getegid() . "</code></p>";

    echo "<h2>3. Session Configuration</h2>";
    echo "<p>session.save_path: <code>" . ini_get('session.save_path') . "</code></p>";
    echo "<p>session.save_handler: <code>" . ini_get('session.save_handler') . "</code></p>";

    echo "<h2>4. Test Session Start</h2>";
    try {
        echo "<p>Attempting session_start()...</p>";
        session_start();
        echo "<p class='success'>✓ session_start() succeeded!</p>";
        echo "<p>Session ID: <code>" . session_id() . "</code></p>";
        echo "<p>Session Status: <code>" . session_status() . "</code> (1=disabled, 2=active)</p>";

        $_SESSION['test'] = time();
        echo "<p class='success'>✓ Can write to session</p>";

    } catch (Exception $e) {
        echo "<p class='error'>✗ session_start() failed: " . $e->getMessage() . "</p>";
    }

    echo "<h2>5. Fix Instructions</h2>";
    if (!is_writable($sessionsDir)) {
        echo "<div style='background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107;'>";
        echo "<p><strong>FOUND THE PROBLEM!</strong></p>";
        echo "<p>The sessions directory is not writable. Run this command:</p>";
        echo "<pre>chmod 777 $sessionsDir\n# OR\nsudo chown -R www-data:www-data $sessionsDir\nsudo chmod 755 $sessionsDir</pre>";
        echo "<p>Replace 'www-data' with your web server user (might be 'apache', 'nginx', etc.)</p>";
        echo "</div>";
    } else {
        echo "<p class='success'>✓ Everything looks good!</p>";
    }
    ?>
</body>
</html>
