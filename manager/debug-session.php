<?php
/**
 * Debug script to diagnose redirect loop
 * Access this at: https://neofoxmedia.com/foxhole/tests/v1/manager/debug-session.php
 */

// Start session first
session_start();

// Output headers
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Session Debug</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .section { background: white; padding: 20px; margin: 10px 0; border-radius: 5px; border: 2px solid #333; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .info { color: blue; }
        h2 { margin-top: 0; border-bottom: 2px solid #333; padding-bottom: 10px; }
        pre { background: #f0f0f0; padding: 10px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>🔍 Session & Redirect Debug Information</h1>

    <div class="section">
        <h2>1. Session Status</h2>
        <?php if (session_status() === PHP_SESSION_ACTIVE): ?>
            <p class="success">✓ Session is ACTIVE</p>
            <p>Session ID: <?php echo session_id(); ?></p>
        <?php else: ?>
            <p class="error">✗ Session is NOT active</p>
        <?php endif; ?>
    </div>

    <div class="section">
        <h2>2. Session Data</h2>
        <?php if (!empty($_SESSION)): ?>
            <p class="success">✓ Session contains data</p>
            <pre><?php print_r($_SESSION); ?></pre>
        <?php else: ?>
            <p class="error">✗ Session is EMPTY - You are not logged in!</p>
            <p class="info">This is likely why you're getting the redirect loop.</p>
            <p class="info">Try logging in at: <a href="../login.php">../login.php</a></p>
        <?php endif; ?>
    </div>

    <div class="section">
        <h2>3. Required Session Variables</h2>
        <table border="1" cellpadding="5" style="border-collapse: collapse;">
            <tr>
                <th>Variable</th>
                <th>Status</th>
                <th>Value</th>
            </tr>
            <tr>
                <td>user_id</td>
                <td><?php echo isset($_SESSION['user_id']) ? '<span class="success">✓ SET</span>' : '<span class="error">✗ MISSING</span>'; ?></td>
                <td><?php echo $_SESSION['user_id'] ?? 'N/A'; ?></td>
            </tr>
            <tr>
                <td>role</td>
                <td><?php echo isset($_SESSION['role']) ? '<span class="success">✓ SET</span>' : '<span class="error">✗ MISSING</span>'; ?></td>
                <td><?php echo $_SESSION['role'] ?? 'N/A'; ?></td>
            </tr>
            <tr>
                <td>username</td>
                <td><?php echo isset($_SESSION['username']) ? '<span class="success">✓ SET</span>' : '<span class="error">✗ MISSING</span>'; ?></td>
                <td><?php echo $_SESSION['username'] ?? 'N/A'; ?></td>
            </tr>
            <tr>
                <td>full_name</td>
                <td><?php echo isset($_SESSION['full_name']) ? '<span class="success">✓ SET</span>' : '<span class="error">✗ MISSING</span>'; ?></td>
                <td><?php echo $_SESSION['full_name'] ?? 'N/A'; ?></td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h2>4. Authentication Check</h2>
        <?php
        $isLoggedIn = isset($_SESSION['user_id']) && isset($_SESSION['role']);
        $hasManagerRole = isset($_SESSION['role']) && $_SESSION['role'] === 'manager';
        ?>
        <p><strong>isLoggedIn():</strong> <?php echo $isLoggedIn ? '<span class="success">TRUE</span>' : '<span class="error">FALSE</span>'; ?></p>
        <p><strong>hasRole(\'manager\'):</strong> <?php echo $hasManagerRole ? '<span class="success">TRUE</span>' : '<span class="error">FALSE</span>'; ?></p>

        <?php if (!$isLoggedIn): ?>
            <p class="error">⚠ You are NOT logged in. The page will redirect to login.php</p>
        <?php elseif (!$hasManagerRole): ?>
            <p class="error">⚠ You are logged in but don't have the 'manager' role.</p>
            <p class="info">Your role is: <strong><?php echo $_SESSION['role'] ?? 'undefined'; ?></strong></p>
            <p class="info">You should be redirected to:
                <?php
                switch ($_SESSION['role'] ?? '') {
                    case 'admin':
                        echo '<strong>../admin/index.php</strong>';
                        break;
                    case 'employee':
                        echo '<strong>../employee/index.php</strong>';
                        break;
                    default:
                        echo '<strong>../login.php (after session destroy)</strong>';
                        break;
                }
                ?>
            </p>
        <?php else: ?>
            <p class="success">✓ All checks passed! You should be able to access manager pages.</p>
        <?php endif; ?>
    </div>

    <div class="section">
        <h2>5. File System Check</h2>
        <?php
        $indexFile = __DIR__ . '/index.php';
        $configFile = dirname(__DIR__) . '/config/config.php';
        $loginFile = dirname(__DIR__) . '/login.php';
        ?>
        <table border="1" cellpadding="5" style="border-collapse: collapse;">
            <tr>
                <th>File</th>
                <th>Status</th>
                <th>Last Modified</th>
            </tr>
            <tr>
                <td>manager/index.php</td>
                <td><?php echo file_exists($indexFile) ? '<span class="success">✓ EXISTS</span>' : '<span class="error">✗ MISSING</span>'; ?></td>
                <td><?php echo file_exists($indexFile) ? date('Y-m-d H:i:s', filemtime($indexFile)) : 'N/A'; ?></td>
            </tr>
            <tr>
                <td>config/config.php</td>
                <td><?php echo file_exists($configFile) ? '<span class="success">✓ EXISTS</span>' : '<span class="error">✗ MISSING</span>'; ?></td>
                <td><?php echo file_exists($configFile) ? date('Y-m-d H:i:s', filemtime($configFile)) : 'N/A'; ?></td>
            </tr>
            <tr>
                <td>login.php</td>
                <td><?php echo file_exists($loginFile) ? '<span class="success">✓ EXISTS</span>' : '<span class="error">✗ MISSING</span>'; ?></td>
                <td><?php echo file_exists($loginFile) ? date('Y-m-d H:i:s', filemtime($loginFile)) : 'N/A'; ?></td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h2>6. PHP Configuration</h2>
        <table border="1" cellpadding="5" style="border-collapse: collapse;">
            <tr>
                <th>Setting</th>
                <th>Value</th>
            </tr>
            <tr>
                <td>PHP Version</td>
                <td><?php echo PHP_VERSION; ?></td>
            </tr>
            <tr>
                <td>Session Save Path</td>
                <td><?php echo session_save_path(); ?></td>
            </tr>
            <tr>
                <td>Session Name</td>
                <td><?php echo session_name(); ?></td>
            </tr>
            <tr>
                <td>Session Cookie Lifetime</td>
                <td><?php echo ini_get('session.cookie_lifetime'); ?></td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h2>7. Recommended Actions</h2>
        <?php if (empty($_SESSION)): ?>
            <ol>
                <li><strong>Login first:</strong> Go to <a href="../login.php">login.php</a> and login with your credentials</li>
                <li>Use demo credentials: <code>john_manager</code> / <code>admin123</code></li>
                <li>After logging in, try accessing manager/index.php again</li>
            </ol>
        <?php elseif (!$hasManagerRole): ?>
            <ol>
                <li><strong>Wrong role:</strong> You're logged in as "<?php echo $_SESSION['role']; ?>" but need "manager" role</li>
                <li>Either login with a manager account (john_manager / admin123)</li>
                <li>Or go to your correct dashboard:
                    <a href="../<?php echo $_SESSION['role']; ?>/index.php">
                        <?php echo $_SESSION['role']; ?>/index.php
                    </a>
                </li>
            </ol>
        <?php else: ?>
            <p class="success">✓ Everything looks good! Try accessing <a href="index.php">manager/index.php</a> now.</p>
        <?php endif; ?>
    </div>

    <div class="section">
        <h2>8. Test Links</h2>
        <ul>
            <li><a href="index.php">Try manager/index.php</a></li>
            <li><a href="../login.php">Go to login.php</a></li>
            <li><a href="../admin/index.php">Try admin/index.php</a></li>
            <li><a href="../employee/index.php">Try employee/index.php</a></li>
            <li><a href="debug-session.php">Refresh this debug page</a></li>
        </ul>
    </div>

</body>
</html>
