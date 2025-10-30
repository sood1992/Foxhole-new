<!DOCTYPE html>
<html>
<head>
    <title>Config Test</title>
</head>
<body>
    <h1>Config Test Page</h1>
    <?php
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    echo "<p>Step 1: Before loading config.php</p>";

    require_once '../config/config.php';

    echo "<p>Step 2: After loading config.php</p>";

    echo "<p>Step 3: isLoggedIn() = " . (isLoggedIn() ? 'TRUE' : 'FALSE') . "</p>";

    if (isset($_SESSION['role'])) {
        echo "<p>Step 4: Your role is: " . $_SESSION['role'] . "</p>";
    } else {
        echo "<p>Step 4: No role in session (not logged in)</p>";
    }

    echo "<h2>Session Data:</h2>";
    echo "<pre>";
    print_r($_SESSION);
    echo "</pre>";
    ?>
    <p>Current time: <?php echo date('Y-m-d H:i:s'); ?></p>
    <p><strong>If this page reloads endlessly, the issue is in config.php or session handling.</strong></p>
    <p><strong>If you see this message, config.php loads fine!</strong></p>
    <p><a href="../login.php">Go to Login</a> | <a href="test-config.php">Refresh</a></p>
</body>
</html>
