<?php
// Start output buffering
ob_start();

require_once '../config/config.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !hasRole('admin')) {
    redirect('../login.php');
}

$db = getDBConnection();
$currentUser = getCurrentUser();
$error = '';
$success = '';

// Path to email config file
$emailConfigFile = __DIR__ . '/../config/email-config.json';

// Load existing config
$emailConfig = [];
if (file_exists($emailConfigFile)) {
    $emailConfig = json_decode(file_get_contents($emailConfigFile), true) ?: [];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';

        if ($action === 'save_smtp') {
            // Save SMTP settings
            $newConfig = [
                'use_smtp' => isset($_POST['use_smtp']) ? 1 : 0,
                'smtp_host' => trim($_POST['smtp_host'] ?? ''),
                'smtp_port' => intval($_POST['smtp_port'] ?? 587),
                'smtp_encryption' => $_POST['smtp_encryption'] ?? 'tls',
                'smtp_username' => trim($_POST['smtp_username'] ?? ''),
                'smtp_password' => trim($_POST['smtp_password'] ?? ''),
                'from_email' => trim($_POST['from_email'] ?? ''),
                'from_name' => trim($_POST['from_name'] ?? SITE_NAME),
            ];

            // Only update password if provided
            if (empty($newConfig['smtp_password']) && !empty($emailConfig['smtp_password'])) {
                $newConfig['smtp_password'] = $emailConfig['smtp_password'];
            }

            // Validate
            if ($newConfig['use_smtp']) {
                if (empty($newConfig['smtp_host'])) {
                    $error = 'SMTP host is required when SMTP is enabled.';
                } elseif (empty($newConfig['smtp_username'])) {
                    $error = 'SMTP username is required when SMTP is enabled.';
                } elseif (empty($newConfig['smtp_password'])) {
                    $error = 'SMTP password is required when SMTP is enabled.';
                }
            }

            if (empty($error)) {
                // Save to file
                $result = file_put_contents($emailConfigFile, json_encode($newConfig, JSON_PRETTY_PRINT));

                if ($result !== false) {
                    $emailConfig = $newConfig;
                    $success = 'Email configuration saved successfully!';
                } else {
                    $error = 'Failed to save configuration file. Check directory permissions.';
                }
            }
        }
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
        error_log("Email config error: " . $e->getMessage());
    }
}

// Default values
$config = array_merge([
    'use_smtp' => 0,
    'smtp_host' => '',
    'smtp_port' => 587,
    'smtp_encryption' => 'tls',
    'smtp_username' => '',
    'smtp_password' => '',
    'from_email' => 'noreply@' . ($_SERVER['HTTP_HOST'] ?? 'foxhole.com'),
    'from_name' => SITE_NAME,
], $emailConfig);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Configuration - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/ultra-premium.css">
    <style>
        .config-section {
            background: var(--bg-secondary);
            padding: 24px;
            border-radius: var(--radius-lg);
            margin-bottom: 24px;
            border: 2px solid var(--border);
        }

        .config-section h3 {
            margin-top: 0;
            margin-bottom: 16px;
            color: var(--text-primary);
        }

        .form-grid {
            display: grid;
            gap: 20px;
        }

        .form-grid-2col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .help-text {
            font-size: 13px;
            color: var(--text-secondary);
            margin-top: 4px;
        }

        .toggle-container {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px;
            background: var(--bg-tertiary);
            border-radius: var(--radius-md);
            margin-bottom: 24px;
        }

        .toggle-switch {
            position: relative;
            width: 50px;
            height: 28px;
            background: #ccc;
            border-radius: 14px;
            cursor: pointer;
            transition: background 0.3s;
        }

        .toggle-switch.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .toggle-switch::after {
            content: '';
            position: absolute;
            top: 2px;
            left: 2px;
            width: 24px;
            height: 24px;
            background: white;
            border-radius: 50%;
            transition: transform 0.3s;
        }

        .toggle-switch.active::after {
            transform: translateX(22px);
        }

        .smtp-settings {
            transition: opacity 0.3s, max-height 0.3s;
        }

        .smtp-settings.disabled {
            opacity: 0.5;
            pointer-events: none;
        }

        .info-box {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            border: 1px solid rgba(102, 126, 234, 0.3);
            padding: 16px;
            border-radius: var(--radius-md);
            margin-bottom: 24px;
        }

        .info-box h4 {
            margin: 0 0 8px 0;
            color: #667eea;
        }

        .info-box ul {
            margin: 8px 0;
            padding-left: 20px;
        }

        .password-field {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--text-secondary);
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <?php include '../includes/admin-sidebar.php'; ?>

        <main class="main-content">
            <div class="topbar">
                <h1>📧 Email Configuration</h1>
                <div class="topbar-actions">
                    <a href="email-test.php" class="btn btn-primary btn-sm">🧪 Test Emails</a>
                    <a href="index.php" class="btn btn-secondary btn-sm">← Dashboard</a>
                </div>
            </div>

            <div class="content">
                <?php if ($error): ?>
                    <div class="alert alert-error" style="margin-bottom: 24px;">
                        <?php echo e($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success" style="margin-bottom: 24px;">
                        <?php echo e($success); ?>
                    </div>
                <?php endif; ?>

                <div class="info-box">
                    <h4>📬 Email System Overview</h4>
                    <p style="margin: 8px 0; color: var(--text-secondary);">
                        Configure how Foxhole sends email notifications for task assignments, updates, and automated reports.
                    </p>
                    <ul style="color: var(--text-secondary); font-size: 14px; margin: 8px 0;">
                        <li><strong>PHP Mail (Default):</strong> Uses your server's built-in mail function - simple but may be less reliable</li>
                        <li><strong>SMTP (Recommended):</strong> More reliable, better deliverability, works with Gmail, Office 365, etc.</li>
                    </ul>
                </div>

                <form method="POST" action="">
                    <input type="hidden" name="action" value="save_smtp">

                    <!-- SMTP Toggle -->
                    <div class="config-section">
                        <div class="toggle-container">
                            <div class="toggle-switch <?php echo $config['use_smtp'] ? 'active' : ''; ?>"
                                 id="smtpToggle" onclick="toggleSMTP()">
                            </div>
                            <input type="checkbox" name="use_smtp" id="use_smtp"
                                   <?php echo $config['use_smtp'] ? 'checked' : ''; ?> style="display: none;">
                            <div>
                                <strong style="font-size: 16px;">Use SMTP for Email Delivery</strong>
                                <p class="help-text" style="margin: 4px 0 0 0;">
                                    Enable SMTP for better email deliverability (recommended for production)
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Sender Information -->
                    <div class="config-section">
                        <h3>📤 Sender Information</h3>
                        <div class="form-grid-2col">
                            <div class="form-group">
                                <label for="from_email">From Email Address</label>
                                <input type="email" id="from_email" name="from_email" required
                                       value="<?php echo e($config['from_email']); ?>"
                                       placeholder="noreply@yourdomain.com">
                                <p class="help-text">The email address that notifications will be sent from</p>
                            </div>

                            <div class="form-group">
                                <label for="from_name">From Name</label>
                                <input type="text" id="from_name" name="from_name" required
                                       value="<?php echo e($config['from_name']); ?>"
                                       placeholder="<?php echo SITE_NAME; ?>">
                                <p class="help-text">The name that will appear as the sender</p>
                            </div>
                        </div>
                    </div>

                    <!-- SMTP Settings -->
                    <div class="config-section smtp-settings <?php echo !$config['use_smtp'] ? 'disabled' : ''; ?>" id="smtpSettings">
                        <h3>⚙️ SMTP Server Settings</h3>

                        <div class="form-grid">
                            <div class="form-grid-2col">
                                <div class="form-group">
                                    <label for="smtp_host">SMTP Host</label>
                                    <input type="text" id="smtp_host" name="smtp_host"
                                           value="<?php echo e($config['smtp_host']); ?>"
                                           placeholder="smtp.gmail.com">
                                    <p class="help-text">Your SMTP server address</p>
                                </div>

                                <div class="form-group">
                                    <label for="smtp_port">SMTP Port</label>
                                    <input type="number" id="smtp_port" name="smtp_port"
                                           value="<?php echo e($config['smtp_port']); ?>"
                                           placeholder="587">
                                    <p class="help-text">Usually 587 (TLS) or 465 (SSL)</p>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="smtp_encryption">Encryption</label>
                                <select id="smtp_encryption" name="smtp_encryption">
                                    <option value="tls" <?php echo $config['smtp_encryption'] === 'tls' ? 'selected' : ''; ?>>
                                        TLS (Recommended)
                                    </option>
                                    <option value="ssl" <?php echo $config['smtp_encryption'] === 'ssl' ? 'selected' : ''; ?>>
                                        SSL
                                    </option>
                                    <option value="none" <?php echo $config['smtp_encryption'] === 'none' ? 'selected' : ''; ?>>
                                        None (Not Recommended)
                                    </option>
                                </select>
                                <p class="help-text">Security protocol for the connection</p>
                            </div>

                            <div class="form-grid-2col">
                                <div class="form-group">
                                    <label for="smtp_username">SMTP Username</label>
                                    <input type="text" id="smtp_username" name="smtp_username"
                                           value="<?php echo e($config['smtp_username']); ?>"
                                           placeholder="your-email@gmail.com">
                                    <p class="help-text">Usually your email address</p>
                                </div>

                                <div class="form-group">
                                    <label for="smtp_password">SMTP Password</label>
                                    <div class="password-field">
                                        <input type="password" id="smtp_password" name="smtp_password"
                                               value=""
                                               placeholder="<?php echo !empty($config['smtp_password']) ? '••••••••••••' : 'Enter password'; ?>">
                                        <span class="password-toggle" onclick="togglePassword()">👁️</span>
                                    </div>
                                    <p class="help-text">
                                        <?php if (!empty($config['smtp_password'])): ?>
                                            Leave blank to keep existing password
                                        <?php else: ?>
                                            For Gmail, use an <a href="https://support.google.com/accounts/answer/185833" target="_blank">App Password</a>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Common SMTP Providers -->
                        <div style="margin-top: 24px; padding: 16px; background: var(--bg-tertiary); border-radius: var(--radius-md);">
                            <h4 style="margin-top: 0; margin-bottom: 12px;">📋 Common SMTP Settings</h4>
                            <div style="display: grid; gap: 12px; font-size: 13px;">
                                <div>
                                    <strong>Gmail:</strong> smtp.gmail.com | Port 587 | TLS | Use App Password
                                </div>
                                <div>
                                    <strong>Office 365:</strong> smtp.office365.com | Port 587 | TLS
                                </div>
                                <div>
                                    <strong>Outlook/Hotmail:</strong> smtp-mail.outlook.com | Port 587 | TLS
                                </div>
                                <div>
                                    <strong>Yahoo:</strong> smtp.mail.yahoo.com | Port 587 | TLS
                                </div>
                                <div>
                                    <strong>SendGrid:</strong> smtp.sendgrid.net | Port 587 | TLS
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Save Button -->
                    <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 24px;">
                        <a href="index.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            💾 Save Configuration
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        function toggleSMTP() {
            const toggle = document.getElementById('smtpToggle');
            const checkbox = document.getElementById('use_smtp');
            const settings = document.getElementById('smtpSettings');

            checkbox.checked = !checkbox.checked;

            if (checkbox.checked) {
                toggle.classList.add('active');
                settings.classList.remove('disabled');
            } else {
                toggle.classList.remove('active');
                settings.classList.add('disabled');
            }
        }

        function togglePassword() {
            const field = document.getElementById('smtp_password');
            const toggle = document.querySelector('.password-toggle');

            if (field.type === 'password') {
                field.type = 'text';
                toggle.textContent = '🙈';
            } else {
                field.type = 'password';
                toggle.textContent = '👁️';
            }
        }
    </script>
    <script src="../assets/js/theme.js"></script>
</body>
</html>
