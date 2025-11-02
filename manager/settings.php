<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !hasRole('manager')) {
    redirect('../login.php');
}

$db = getDBConnection();
$currentUser = getCurrentUser();
$success = '';
$error = '';

// Get current user preferences (create table if needed)
try {
    $prefsStmt = $db->prepare("
        SELECT * FROM user_preferences WHERE user_id = ?
    ");
    $prefsStmt->execute([$currentUser['id']]);
    $prefs = $prefsStmt->fetch();
} catch (Exception $e) {
    // Table might not exist, use defaults
    $prefs = [
        'theme' => 'light',
        'notifications_email' => 1,
        'notifications_browser' => 1,
        'email_digest' => 'daily',
        'timezone' => 'Asia/Kolkata',
        'language' => 'en'
    ];
}

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $theme = $_POST['theme'] ?? 'light';
        $notificationsEmail = isset($_POST['notifications_email']) ? 1 : 0;
        $notificationsBrowser = isset($_POST['notifications_browser']) ? 1 : 0;
        $emailDigest = $_POST['email_digest'] ?? 'daily';
        $timezone = $_POST['timezone'] ?? 'Asia/Kolkata';
        $language = $_POST['language'] ?? 'en';

        // Create preferences table if it doesn't exist
        try {
            $db->exec("
                CREATE TABLE IF NOT EXISTS user_preferences (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    theme VARCHAR(20) DEFAULT 'light',
                    notifications_email TINYINT DEFAULT 1,
                    notifications_browser TINYINT DEFAULT 1,
                    email_digest VARCHAR(20) DEFAULT 'daily',
                    timezone VARCHAR(50) DEFAULT 'Asia/Kolkata',
                    language VARCHAR(10) DEFAULT 'en',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_user (user_id)
                )
            ");
        } catch (Exception $e) {
            // Table already exists
        }

        // Insert or update preferences
        $stmt = $db->prepare("
            INSERT INTO user_preferences
            (user_id, theme, notifications_email, notifications_browser, email_digest, timezone, language)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            theme = VALUES(theme),
            notifications_email = VALUES(notifications_email),
            notifications_browser = VALUES(notifications_browser),
            email_digest = VALUES(email_digest),
            timezone = VALUES(timezone),
            language = VALUES(language),
            updated_at = NOW()
        ");

        $stmt->execute([
            $currentUser['id'],
            $theme,
            $notificationsEmail,
            $notificationsBrowser,
            $emailDigest,
            $timezone,
            $language
        ]);

        $success = 'Settings saved successfully!';

        // Refresh preferences
        $prefsStmt = $db->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
        $prefsStmt->execute([$currentUser['id']]);
        $prefs = $prefsStmt->fetch();
    } catch (Exception $e) {
        $error = 'Failed to save settings: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/vien-v3.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="app-container">
        <?php include '../includes/v3-manager-sidebar.php'; ?>

        <div class="main-content">
            <?php include '../includes/v3-header.php'; ?>

            <div class="content-wrapper">
                <div class="page-header">
                    <div>
                        <h1 class="page-title"><i class="fas fa-cog"></i> Settings</h1>
                        <p class="page-description">Configure your preferences and account settings</p>
                    </div>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success" style="margin-bottom: 24px;">
                        <i class="fas fa-check-circle"></i> <?php echo e($success); ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-error" style="margin-bottom: 24px;">
                        <i class="fas fa-exclamation-circle"></i> <?php echo e($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="row" style="gap: 24px;">
                        <!-- Left Column -->
                        <div class="col-lg-8">
                            <!-- Appearance Settings -->
                            <div class="dashboard-card" style="margin-bottom: 24px;">
                                <div class="card-header">
                                    <h3><i class="fas fa-palette"></i> Appearance</h3>
                                </div>
                                <div class="card-body">
                                    <div class="form-group">
                                        <label>Theme</label>
                                        <div style="display: flex; gap: 12px; margin-top: 8px;">
                                            <label style="flex: 1; cursor: pointer;">
                                                <input type="radio" name="theme" value="light"
                                                       <?php echo ($prefs['theme'] ?? 'light') === 'light' ? 'checked' : ''; ?>>
                                                <div style="padding: 20px; background: white; border: 2px solid <?php echo ($prefs['theme'] ?? 'light') === 'light' ? 'var(--primary)' : 'var(--border)'; ?>; border-radius: var(--radius-md); text-align: center;">
                                                    <i class="fas fa-sun" style="font-size: 32px; color: #f59e0b; margin-bottom: 8px;"></i>
                                                    <div style="font-weight: 600;">Light</div>
                                                </div>
                                            </label>
                                            <label style="flex: 1; cursor: pointer;">
                                                <input type="radio" name="theme" value="dark"
                                                       <?php echo ($prefs['theme'] ?? 'light') === 'dark' ? 'checked' : ''; ?>>
                                                <div style="padding: 20px; background: #1a1a1a; color: white; border: 2px solid <?php echo ($prefs['theme'] ?? 'light') === 'dark' ? 'var(--primary)' : 'var(--border)'; ?>; border-radius: var(--radius-md); text-align: center;">
                                                    <i class="fas fa-moon" style="font-size: 32px; color: #8b5cf6; margin-bottom: 8px;"></i>
                                                    <div style="font-weight: 600;">Dark</div>
                                                </div>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Notification Settings -->
                            <div class="dashboard-card" style="margin-bottom: 24px;">
                                <div class="card-header">
                                    <h3><i class="fas fa-bell"></i> Notifications</h3>
                                </div>
                                <div class="card-body">
                                    <div class="form-group">
                                        <label style="display: flex; align-items: center; cursor: pointer; padding: 12px; background: var(--bg-tertiary); border-radius: var(--radius-md); margin-bottom: 12px;">
                                            <input type="checkbox" name="notifications_email" value="1"
                                                   <?php echo ($prefs['notifications_email'] ?? 1) ? 'checked' : ''; ?>
                                                   style="margin-right: 12px;">
                                            <div style="flex: 1;">
                                                <div style="font-weight: 600; margin-bottom: 4px;">Email Notifications</div>
                                                <div style="font-size: 12px; color: var(--text-secondary);">Receive notifications via email</div>
                                            </div>
                                        </label>

                                        <label style="display: flex; align-items: center; cursor: pointer; padding: 12px; background: var(--bg-tertiary); border-radius: var(--radius-md);">
                                            <input type="checkbox" name="notifications_browser" value="1"
                                                   <?php echo ($prefs['notifications_browser'] ?? 1) ? 'checked' : ''; ?>
                                                   style="margin-right: 12px;">
                                            <div style="flex: 1;">
                                                <div style="font-weight: 600; margin-bottom: 4px;">Browser Notifications</div>
                                                <div style="font-size: 12px; color: var(--text-secondary);">Show notifications in browser</div>
                                            </div>
                                        </label>
                                    </div>

                                    <div class="form-group">
                                        <label for="email_digest">Email Digest Frequency</label>
                                        <select id="email_digest" name="email_digest" class="form-control">
                                            <option value="never" <?php echo ($prefs['email_digest'] ?? 'daily') === 'never' ? 'selected' : ''; ?>>Never</option>
                                            <option value="daily" <?php echo ($prefs['email_digest'] ?? 'daily') === 'daily' ? 'selected' : ''; ?>>Daily</option>
                                            <option value="weekly" <?php echo ($prefs['email_digest'] ?? 'daily') === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                                            <option value="monthly" <?php echo ($prefs['email_digest'] ?? 'daily') === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Regional Settings -->
                            <div class="dashboard-card">
                                <div class="card-header">
                                    <h3><i class="fas fa-globe"></i> Regional Settings</h3>
                                </div>
                                <div class="card-body">
                                    <div class="form-group">
                                        <label for="timezone">Timezone</label>
                                        <select id="timezone" name="timezone" class="form-control">
                                            <option value="Asia/Kolkata" <?php echo ($prefs['timezone'] ?? 'Asia/Kolkata') === 'Asia/Kolkata' ? 'selected' : ''; ?>>India (Asia/Kolkata)</option>
                                            <option value="America/New_York" <?php echo ($prefs['timezone'] ?? 'Asia/Kolkata') === 'America/New_York' ? 'selected' : ''; ?>>New York (America/New_York)</option>
                                            <option value="America/Los_Angeles" <?php echo ($prefs['timezone'] ?? 'Asia/Kolkata') === 'America/Los_Angeles' ? 'selected' : ''; ?>>Los Angeles (America/Los_Angeles)</option>
                                            <option value="Europe/London" <?php echo ($prefs['timezone'] ?? 'Asia/Kolkata') === 'Europe/London' ? 'selected' : ''; ?>>London (Europe/London)</option>
                                            <option value="Europe/Paris" <?php echo ($prefs['timezone'] ?? 'Asia/Kolkata') === 'Europe/Paris' ? 'selected' : ''; ?>>Paris (Europe/Paris)</option>
                                            <option value="Asia/Tokyo" <?php echo ($prefs['timezone'] ?? 'Asia/Kolkata') === 'Asia/Tokyo' ? 'selected' : ''; ?>>Tokyo (Asia/Tokyo)</option>
                                            <option value="Asia/Dubai" <?php echo ($prefs['timezone'] ?? 'Asia/Kolkata') === 'Asia/Dubai' ? 'selected' : ''; ?>>Dubai (Asia/Dubai)</option>
                                            <option value="Australia/Sydney" <?php echo ($prefs['timezone'] ?? 'Asia/Kolkata') === 'Australia/Sydney' ? 'selected' : ''; ?>>Sydney (Australia/Sydney)</option>
                                        </select>
                                    </div>

                                    <div class="form-group">
                                        <label for="language">Language</label>
                                        <select id="language" name="language" class="form-control">
                                            <option value="en" <?php echo ($prefs['language'] ?? 'en') === 'en' ? 'selected' : ''; ?>>English</option>
                                            <option value="es" <?php echo ($prefs['language'] ?? 'en') === 'es' ? 'selected' : ''; ?>>Español</option>
                                            <option value="fr" <?php echo ($prefs['language'] ?? 'en') === 'fr' ? 'selected' : ''; ?>>Français</option>
                                            <option value="de" <?php echo ($prefs['language'] ?? 'en') === 'de' ? 'selected' : ''; ?>>Deutsch</option>
                                            <option value="hi" <?php echo ($prefs['language'] ?? 'en') === 'hi' ? 'selected' : ''; ?>>हिन्दी</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column - Quick Info -->
                        <div class="col-lg-4">
                            <div class="dashboard-card">
                                <div class="card-header">
                                    <h3><i class="fas fa-info-circle"></i> About Settings</h3>
                                </div>
                                <div class="card-body">
                                    <div style="font-size: 13px; line-height: 1.6; color: var(--text-secondary);">
                                        <p><strong>Theme:</strong> Choose between light and dark mode for comfortable viewing.</p>
                                        <p><strong>Notifications:</strong> Control how you receive updates about tasks and projects.</p>
                                        <p><strong>Email Digest:</strong> Get a summary of your activity at your preferred frequency.</p>
                                        <p><strong>Timezone:</strong> All dates and times will be displayed in your selected timezone.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="dashboard-card" style="margin-top: 24px;">
                                <div class="card-header">
                                    <h3><i class="fas fa-shield-alt"></i> Privacy</h3>
                                </div>
                                <div class="card-body">
                                    <div style="font-size: 13px; line-height: 1.6; color: var(--text-secondary);">
                                        <p>Your data is stored securely and never shared with third parties.</p>
                                        <p style="margin-top: 12px;">
                                            <a href="#" style="color: var(--primary); text-decoration: none;">
                                                <i class="fas fa-download"></i> Export My Data
                                            </a>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div style="margin-top: 24px; padding: 20px; background: var(--bg-tertiary); border-radius: var(--radius-md); text-align: center;">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save"></i> Save All Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../assets/js/theme.js"></script>
    <script>
        // Apply theme immediately
        const themeInputs = document.querySelectorAll('input[name="theme"]');
        themeInputs.forEach(input => {
            input.addEventListener('change', function() {
                document.documentElement.setAttribute('data-theme', this.value);
            });
        });

        // Set current theme
        const currentTheme = '<?php echo $prefs['theme'] ?? 'light'; ?>';
        document.documentElement.setAttribute('data-theme', currentTheme);
    </script>
</body>
</html>
