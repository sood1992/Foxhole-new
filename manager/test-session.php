<!DOCTYPE html>
<html>
<head>
    <title>Session Test</title>
</head>
<body>
    <h1>Session Test Page</h1>
    <?php
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    echo "<p>Step 1: Before session_start()</p>";

    session_start();

    echo "<p>Step 2: After session_start() - Session ID: " . session_id() . "</p>";

    echo "<p>Step 3: Session Status: " . session_status() . "</p>";

    $_SESSION['test'] = 'Working!';

    echo "<p>Step 4: Set session variable</p>";

    echo "<pre>";
    print_r($_SESSION);
    echo "</pre>";
    ?>
    <p>If you can see all steps without refresh, sessions work fine.</p>
    <p>Current time: <?php echo date('Y-m-d H:i:s'); ?></p>
</body>
</html>
