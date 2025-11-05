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
    <title>Email Configuration - <?php echo SITE_NAME; ?> V3</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/vien-v3.css">

    <!-- Synto Dashboard Template Design -->
    <link rel="stylesheet" href="../assets/css/synto-design.css">
    <style>
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
            font-size: 16px;
            transition: color 200ms ease;
        }
        .password-toggle:hover {
            color: var(--primary);
        }
        .smtp-settings {
            transition: opacity 0.3s;
        }
        .smtp-settings.disabled {
            opacity: 0.5;
            pointer-events: none;
        }
        .info-box {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            border: 1px solid rgba(102, 126, 234, 0.3);
            padding: 20px;
            border-radius: var(--radius-md);
            margin-bottom: 30px;
        }
        .info-box h4 {
            margin: 0 0 12px 0;
            color: #667eea;
            font-size: 16px;
        }
        .info-box ul {
            margin: 12px 0;
            padding-left: 20px;
            color: var(--text-secondary);
        }
        .providers-box {
            background: linear-gradient(135deg, rgba(23, 176, 107, 0.05) 0%, rgba(20, 212, 143, 0.05) 100%);
            border: 1px solid rgba(23, 176, 107, 0.2);
            padding: 20px;
            border-radius: var(--radius-md);
            margin-top: 24px;
        }
        .providers-box h4 {
            margin: 0 0 12px 0;
            color: var(--success);
            font-size: 14px;
            font-weight: 600;
        }
        .provider-list {
            display: grid;
            gap: 10px;
            font-size: 13px;
        }
        .provider-item {
            color: var(--text-secondary);
        }
        .provider-item strong {
            color: var(--heading-color);
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include '../includes/v3-admin-sidebar.php'; ?>

        <div class="main-content">
            <?php include '../includes/v3-header.php'; ?>

            <div class="content-wrapper">
                <!-- Page Title -->
                <div style="margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h1 style="margin-bottom: 8px;">Email Configuration</h1>
                        <p style="color: var(--text-secondary); font-size: 14px; margin: 0;">
                            Configure SMTP settings for email notifications
                        </p>
                    </div>
                    <div style="display: flex; gap: 12px;">
                        <a href="email-test.php" class="btn btn-primary btn-sm">
                            <i class="fas fa-flask"></i> Test Emails
                        </a>
                        <a href="index.php" class="btn btn-outline btn-sm">
                            <i class="fas fa-arrow-left"></i> Dashboard
                        </a>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error" style="margin-bottom: 24px;">
                        <i class="fas fa-exclamation-circle"></i> <?php echo e($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success" style="margin-bottom: 24px;">
                        <i class="fas ri-checkbox-circle-line"></i> <?php echo e($success); ?>
                    </div>
                <?php endif; ?>

                <!-- Email System Overview -->
                <div class="info-box">
                    <h4><i class="fas ri-mail-line"></i> Email System Overview</h4>
                    <p style="margin: 8px 0; color: var(--text-secondary); font-size: 14px;">
                        Configure how the system sends email notifications for task assignments, updates, and automated reports.
                    </p>
                    <ul style="font-size: 14px;">
                        <li><strong>PHP Mail (Default):</strong> Uses your server's built-in mail function - simple but may be less reliable</li>
                        <li><strong>SMTP (Recommended):</strong> More reliable, better deliverability, works with Gmail, Office 365, etc.</li>
                    </ul>
                </div>

                <form method="POST" action="">
                    <input type="hidden" name="action" value="save_smtp">

                    <!-- SMTP Toggle -->
                    <div class="card" style="margin-bottom: 30px;">
                        <div class="card-body">
                            <label class="custom-switch">
                                <input type="checkbox" name="use_smtp" id="use_smtp"
                                       <?php echo $config['use_smtp'] ? 'checked' : ''; ?>
                                       onchange="toggleSMTP()">
                                <span class="switch-slider"></span>
                                <span class="switch-label">
                                    <strong style="font-size: 16px;">Use SMTP for Email Delivery</strong>
                                    <span style="display: block; font-size: 13px; color: var(--text-secondary); margin-top: 4px;">
                                        Enable SMTP for better email deliverability (recommended for production)
                                    </span>
                                </span>
                            </label>
                        </div>
                    </div>

                    <!-- Sender Information -->
                    <div class="card" style="margin-bottom: 30px;">
                        <div class="card-header">
                            <div>
                                <h3 style="margin: 0;"><i class="fas fa-paper-plane"></i> Sender Information</h3>
                                <p style="font-size: 13px; color: var(--text-secondary); margin: 4px 0 0 0;">
                                    Configure the sender details for outgoing emails
                                </p>
                            </div>
                        </div>
                        <div class="card-body">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div class="form-group">
                                    <label>From Email Address <span class="required">*</span></label>
                                    <input type="email" name="from_email" class="form-control" required
                                           value="<?php echo e($config['from_email']); ?>"
                                           placeholder="noreply@yourdomain.com">
                                    <small class="form-text">The email address that notifications will be sent from</small>
                                </div>

                                <div class="form-group">
                                    <label>From Name <span class="required">*</span></label>
                                    <input type="text" name="from_name" class="form-control" required
                                           value="<?php echo e($config['from_name']); ?>"
                                           placeholder="<?php echo SITE_NAME; ?>">
                                    <small class="form-text">The name that will appear as the sender</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SMTP Settings -->
                    <div class="card smtp-settings <?php echo !$config['use_smtp'] ? 'disabled' : ''; ?>" id="smtpSettings">
                        <div class="card-header">
                            <div>
                                <h3 style="margin: 0;"><i class="fas ri-settings-3-line"></i> SMTP Server Settings</h3>
                                <p style="font-size: 13px; color: var(--text-secondary); margin: 4px 0 0 0;">
                                    Enter your SMTP server credentials
                                </p>
                            </div>
                        </div>
                        <div class="card-body">
                            <div style="display: grid; gap: 20px;">
                                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
                                    <div class="form-group">
                                        <label>SMTP Host</label>
                                        <input type="text" name="smtp_host" class="form-control"
                                               value="<?php echo e($config['smtp_host']); ?>"
                                               placeholder="smtp.gmail.com">
                                        <small class="form-text">Your SMTP server address</small>
                                    </div>

                                    <div class="form-group">
                                        <label>SMTP Port</label>
                                        <input type="number" name="smtp_port" class="form-control"
                                               value="<?php echo e($config['smtp_port']); ?>"
                                               placeholder="587">
                                        <small class="form-text">Usually 587 (TLS) or 465 (SSL)</small>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Encryption</label>
                                    <select name="smtp_encryption" class="form-control">
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
                                    <small class="form-text">Security protocol for the connection</small>
                                </div>

                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                    <div class="form-group">
                                        <label>SMTP Username</label>
                                        <input type="text" name="smtp_username" class="form-control"
                                               value="<?php echo e($config['smtp_username']); ?>"
                                               placeholder="your-email@gmail.com">
                                        <small class="form-text">Usually your email address</small>
                                    </div>

                                    <div class="form-group">
                                        <label>SMTP Password</label>
                                        <div class="password-field">
                                            <input type="password" id="smtp_password" name="smtp_password" class="form-control"
                                                   value=""
                                                   placeholder="<?php echo !empty($config['smtp_password']) ? '••••••••••••' : 'Enter password'; ?>">
                                            <span class="password-toggle" onclick="togglePassword()">
                                                <i class="fas ri-eye-line"></i>
                                            </span>
                                        </div>
                                        <small class="form-text">
                                            <?php if (!empty($config['smtp_password'])): ?>
                                                Leave blank to keep existing password
                                            <?php else: ?>
                                                For Gmail, use an <a href="https://support.google.com/accounts/answer/185833" target="_blank" style="color: var(--primary);">App Password</a>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                </div>
                            </div>

                            <!-- Common SMTP Providers -->
                            <div class="providers-box">
                                <h4><i class="fas ri-list-check"></i> Common SMTP Settings</h4>
                                <div class="provider-list">
                                    <div class="provider-item">
                                        <strong>Gmail:</strong> smtp.gmail.com | Port 587 | TLS | Use App Password
                                    </div>
                                    <div class="provider-item">
                                        <strong>Office 365:</strong> smtp.office365.com | Port 587 | TLS
                                    </div>
                                    <div class="provider-item">
                                        <strong>Outlook/Hotmail:</strong> smtp-mail.outlook.com | Port 587 | TLS
                                    </div>
                                    <div class="provider-item">
                                        <strong>Yahoo:</strong> smtp.mail.yahoo.com | Port 587 | TLS
                                    </div>
                                    <div class="provider-item">
                                        <strong>SendGrid:</strong> smtp.sendgrid.net | Port 587 | TLS
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Save Button -->
                    <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 30px;">
                        <a href="index.php" class="btn btn-outline">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Configuration
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>

    <script>
        function toggleSMTP() {
            const checkbox = document.getElementById('use_smtp');
            const settings = document.getElementById('smtpSettings');

            if (checkbox.checked) {
                settings.classList.remove('disabled');
            } else {
                settings.classList.add('disabled');
            }
        }

        function togglePassword() {
            const field = document.getElementById('smtp_password');
            const toggle = document.querySelector('.password-toggle i');

            if (field.type === 'password') {
                field.type = 'text';
                toggle.classList.remove('ri-eye-line');
                toggle.classList.add('ri-eye-line-slash');
            } else {
                field.type = 'password';
                toggle.classList.remove('ri-eye-line-slash');
                toggle.classList.add('ri-eye-line');
            }
        }

        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 300);
                }, 5000);
            });
        });
    </script>

    <!-- Synto Dashboard Interactions -->
    <script src="../assets/js/synto-interactions.js"></script>
</body>
</html>
