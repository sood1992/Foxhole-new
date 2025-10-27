<?php
ob_start(); // Start output buffering to prevent blank page issues

require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/email-functions.php';

if (!isLoggedIn() || !hasRole('admin')) {
    redirect('../login.php');
}

$db = getDBConnection();
$currentUser = getCurrentUser();

// Check for session messages
$error = $_SESSION['error_message'] ?? '';
$success = $_SESSION['success_message'] ?? '';
$testResult = $_SESSION['test_result'] ?? '';

// Clear session messages
unset($_SESSION['error_message'], $_SESSION['success_message'], $_SESSION['test_result']);

// Handle test email submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $testType = $_POST['test_type'] ?? '';
        $testEmail = trim($_POST['test_email'] ?? $currentUser['email']);

        switch ($testType) {
            case 'simple':
                // Simple test email
                $content = <<<HTML
<h2>✅ Email Test Successful!</h2>
<p>Hello from Foxhole!</p>
<p>If you're reading this, your email configuration is working correctly.</p>
<p><strong>Test Details:</strong></p>
<ul>
    <li>Sent to: {$testEmail}</li>
    <li>Time: {$_SERVER['REQUEST_TIME']}</li>
    <li>Server: {$_SERVER['HTTP_HOST']}</li>
</ul>
<p style="color: #16a34a; font-weight: 600;">🎉 Email system is configured properly!</p>
HTML;

                $emailHtml = getEmailTemplate($content, 'Email Test');
                $result = sendEmail($testEmail, '[' . SITE_NAME . '] Email Test', $emailHtml);

                ob_end_clean();
                if ($result) {
                    $_SESSION['success_message'] = "✅ Test email sent successfully to {$testEmail}! Check your inbox.";
                } else {
                    $_SESSION['error_message'] = "❌ Failed to send test email. Check your email configuration and error logs.";
                }
                header("Location: email-test.php");
                exit();
                break;

            case 'task_assignment':
                // Test task assignment notification
                $content = <<<HTML
<h2>New Task Assigned to You</h2>
<p>Hi {$currentUser['full_name']},</p>
<p>You have been assigned a new task by Admin (Test Notification).</p>

<div class="task-box">
    <h3 style="margin-top: 0;">Sample Task: Complete Project Documentation</h3>
    <p><strong>Project:</strong> Website Redesign Project</p>
    <p><strong>Priority:</strong> <span class='badge badge-high'>HIGH</span></p>
    <p><strong>Due Date:</strong> Dec 31, 2024</p>
    <p><strong>Description:</strong><br>This is a sample task notification to test the email system. In production, this would contain the actual task details.</p>
</div>

<a href="http://{$_SERVER['HTTP_HOST']}/employee/tasks.php" class="button">View Task</a>

<p style="color: #6c757d; font-size: 14px;">This is a test notification from {SITE_NAME}.</p>
HTML;

                $emailHtml = getEmailTemplate($content, 'New Task Assigned (Test)');
                $result = sendEmail($testEmail, '[' . SITE_NAME . '] Test: New Task Assigned', $emailHtml);

                ob_end_clean();
                if ($result) {
                    $_SESSION['success_message'] = "✅ Task assignment test email sent to {$testEmail}!";
                } else {
                    $_SESSION['error_message'] = "❌ Failed to send task assignment test email.";
                }
                header("Location: email-test.php");
                exit();
                break;

            case 'task_update':
                // Test task update notification
                $content = <<<HTML
<h2>Task Status Updated</h2>
<p>{$currentUser['full_name']} has updated a task status (Test Notification).</p>

<div class="task-box">
    <h3 style="margin-top: 0;">Sample Task: Complete Project Documentation</h3>
    <p><strong>Project:</strong> Website Redesign Project</p>
    <p><strong>Assigned to:</strong> John Doe</p>
    <p><strong>Status Change:</strong> 📋 todo → 🔄 in_progress</p>
</div>

<a href="http://{$_SERVER['HTTP_HOST']}/admin/projects.php" class="button">View Project</a>

<p style="color: #6c757d; font-size: 14px;">This is a test notification.</p>
HTML;

                $emailHtml = getEmailTemplate($content, 'Task Status Updated (Test)');
                $result = sendEmail($testEmail, '[' . SITE_NAME . '] Test: Task Updated', $emailHtml);

                ob_end_clean();
                if ($result) {
                    $_SESSION['success_message'] = "✅ Task update test email sent to {$testEmail}!";
                } else {
                    $_SESSION['error_message'] = "❌ Failed to send task update test email.";
                }
                header("Location: email-test.php");
                exit();
                break;

            case 'daily_report':
                // Test daily report
                $result = sendDailyProgressReport();

                ob_end_clean();
                if ($result) {
                    $_SESSION['success_message'] = "✅ Daily progress report sent to all admins and managers!";
                    $_SESSION['test_result'] = "Report includes today's statistics and was sent to all users with admin/manager roles who have email addresses configured.";
                } else {
                    $_SESSION['error_message'] = "❌ Failed to send daily report. Check error logs for details.";
                }
                header("Location: email-test.php");
                exit();
                break;

            case 'weekly_report':
                // Test weekly report
                $result = sendWeeklyProgressReport();

                ob_end_clean();
                if ($result) {
                    $_SESSION['success_message'] = "✅ Weekly progress report sent to all admins and managers!";
                    $_SESSION['test_result'] = "Report includes this week's statistics and was sent to all users with admin/manager roles.";
                } else {
                    $_SESSION['error_message'] = "❌ Failed to send weekly report. Check error logs for details.";
                }
                header("Location: email-test.php");
                exit();
                break;

            case 'monthly_report':
                // Test monthly report
                $result = sendMonthlyProgressReport();

                ob_end_clean();
                if ($result) {
                    $_SESSION['success_message'] = "✅ Monthly progress report sent to all admins and managers!";
                    $_SESSION['test_result'] = "Report includes this month's statistics and was sent to all users with admin/manager roles.";
                } else {
                    $_SESSION['error_message'] = "❌ Failed to send monthly report. Check error logs for details.";
                }
                header("Location: email-test.php");
                exit();
                break;

            default:
                ob_end_clean();
                $_SESSION['error_message'] = 'Invalid test type selected.';
                header("Location: email-test.php");
                exit();
        }
    } catch (Exception $e) {
        ob_end_clean();
        $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
        error_log("Email test error: " . $e->getMessage());
        header("Location: email-test.php");
        exit();
    }
}

// Get email configuration status
$emailConfigFile = __DIR__ . '/../config/email-config.json';
$emailConfig = [];
if (file_exists($emailConfigFile)) {
    $emailConfig = json_decode(file_get_contents($emailConfigFile), true) ?: [];
}

$useSmtp = $emailConfig['use_smtp'] ?? false;
$smtpConfigured = $useSmtp && !empty($emailConfig['smtp_host']) && !empty($emailConfig['smtp_username']);

// Get list of users with emails for reference
$usersWithEmail = $db->query("
    SELECT full_name, email, role
    FROM users
    WHERE is_active = 1 AND email IS NOT NULL AND email != ''
    ORDER BY role, full_name
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Testing - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/ultra-premium.css">
    <style>
        .test-card {
            background: var(--bg-secondary);
            padding: 24px;
            border-radius: var(--radius-lg);
            border: 2px solid var(--border);
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }

        .test-card:hover {
            border-color: #667eea;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.1);
        }

        .test-card h3 {
            margin-top: 0;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .test-card p {
            color: var(--text-secondary);
            margin-bottom: 16px;
            font-size: 14px;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-configured {
            background: #d1fae5;
            color: #065f46;
        }

        .status-not-configured {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-default {
            background: #dbeafe;
            color: #1e40af;
        }

        .config-status {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            border: 1px solid rgba(102, 126, 234, 0.3);
            padding: 20px;
            border-radius: var(--radius-lg);
            margin-bottom: 24px;
        }

        .users-list {
            background: var(--bg-tertiary);
            padding: 16px;
            border-radius: var(--radius-md);
            margin-top: 24px;
            max-height: 300px;
            overflow-y: auto;
        }

        .user-item {
            padding: 8px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 14px;
        }

        .user-item:last-child {
            border-bottom: none;
        }

        .preview-box {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            padding: 16px;
            border-radius: 8px;
            margin-top: 12px;
            font-size: 13px;
            color: #495057;
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <?php include '../includes/admin-sidebar.php'; ?>

        <main class="main-content">
            <div class="topbar">
                <h1>🧪 Email Testing</h1>
                <div class="topbar-actions">
                    <a href="email-config.php" class="btn btn-secondary btn-sm">⚙️ Email Config</a>
                    <a href="index.php" class="btn btn-secondary btn-sm">← Dashboard</a>
                </div>
            </div>

            <div class="content">
                <?php if ($error): ?>
                    <div class="alert alert-error" style="margin-bottom: 24px;">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success" style="margin-bottom: 24px;">
                        <?php echo $success; ?>
                        <?php if ($testResult): ?>
                            <br><small><?php echo $testResult; ?></small>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Email Configuration Status -->
                <div class="config-status">
                    <h3 style="margin-top: 0; margin-bottom: 16px;">📊 Email System Status</h3>
                    <div style="display: grid; gap: 12px;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span><strong>Email Method:</strong></span>
                            <span class="status-badge <?php echo $useSmtp ? 'status-configured' : 'status-default'; ?>">
                                <?php echo $useSmtp ? '✉️ SMTP' : '📮 PHP Mail'; ?>
                            </span>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span><strong>SMTP Configuration:</strong></span>
                            <span class="status-badge <?php echo $smtpConfigured ? 'status-configured' : 'status-not-configured'; ?>">
                                <?php echo $smtpConfigured ? '✅ Configured' : '⚠️ Not Configured'; ?>
                            </span>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span><strong>Users with Email:</strong></span>
                            <span class="status-badge status-configured">
                                <?php echo count($usersWithEmail); ?> users
                            </span>
                        </div>
                        <?php if ($useSmtp && $smtpConfigured): ?>
                        <div style="font-size: 13px; color: var(--text-secondary); margin-top: 8px;">
                            <strong>SMTP Server:</strong> <?php echo e($emailConfig['smtp_host']); ?>:<?php echo e($emailConfig['smtp_port']); ?>
                            (<?php echo strtoupper($emailConfig['smtp_encryption'] ?? 'TLS'); ?>)
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!$smtpConfigured && !$useSmtp): ?>
                        <div style="margin-top: 16px; padding: 12px; background: rgba(245, 158, 11, 0.1); border-radius: 8px; font-size: 14px;">
                            ℹ️ Using PHP's default mail() function. For better deliverability, <a href="email-config.php">configure SMTP</a>.
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Test Email Form -->
                <div class="card">
                    <div class="card-header">
                        <h3>✉️ Send Test Email</h3>
                        <p style="font-size: 14px; color: var(--text-secondary); margin: 8px 0 0 0;">
                            Enter an email address to receive test notifications
                        </p>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="form-group" style="margin-bottom: 24px;">
                                <label for="test_email">Recipient Email Address</label>
                                <input type="email" id="test_email" name="test_email" required
                                       value="<?php echo e($currentUser['email'] ?? ''); ?>"
                                       placeholder="your-email@example.com"
                                       style="width: 100%; max-width: 400px;">
                                <small style="color: var(--text-secondary); font-size: 12px; display: block; margin-top: 4px;">
                                    Defaults to your email: <?php echo e($currentUser['email'] ?? 'Not set'); ?>
                                </small>
                            </div>

                            <div style="display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));">
                                <!-- Simple Test -->
                                <div class="test-card">
                                    <h3>📬 Simple Test</h3>
                                    <p>Send a basic test email to verify your email configuration is working.</p>
                                    <button type="submit" name="test_type" value="simple" class="btn btn-primary btn-sm">
                                        Send Simple Test
                                    </button>
                                </div>

                                <!-- Task Assignment Test -->
                                <div class="test-card">
                                    <h3>📋 Task Assignment</h3>
                                    <p>Preview how task assignment notifications look when employees receive them.</p>
                                    <button type="submit" name="test_type" value="task_assignment" class="btn btn-primary btn-sm">
                                        Send Task Assignment
                                    </button>
                                    <div class="preview-box">
                                        <strong>Includes:</strong> Task details, priority badge, due date, project info, view button
                                    </div>
                                </div>

                                <!-- Task Update Test -->
                                <div class="test-card">
                                    <h3>🔄 Task Update</h3>
                                    <p>See how admins and managers are notified when task statuses change.</p>
                                    <button type="submit" name="test_type" value="task_update" class="btn btn-primary btn-sm">
                                        Send Task Update
                                    </button>
                                    <div class="preview-box">
                                        <strong>Includes:</strong> Status change, updater name, task details, project link
                                    </div>
                                </div>

                                <!-- Daily Report Test -->
                                <div class="test-card">
                                    <h3>📊 Daily Report</h3>
                                    <p>Test the automated daily progress report sent to all admins and managers.</p>
                                    <button type="submit" name="test_type" value="daily_report" class="btn btn-primary btn-sm">
                                        Send Daily Report
                                    </button>
                                    <div class="preview-box">
                                        <strong>Sent to:</strong> All admins & managers<br>
                                        <strong>Includes:</strong> Tasks completed today, hours logged, top performers
                                    </div>
                                </div>

                                <!-- Weekly Report Test -->
                                <div class="test-card">
                                    <h3>📈 Weekly Report</h3>
                                    <p>Test the weekly summary report with team performance metrics.</p>
                                    <button type="submit" name="test_type" value="weekly_report" class="btn btn-primary btn-sm">
                                        Send Weekly Report
                                    </button>
                                    <div class="preview-box">
                                        <strong>Sent to:</strong> All admins & managers<br>
                                        <strong>Includes:</strong> Week stats, top 5 performers with medals
                                    </div>
                                </div>

                                <!-- Monthly Report Test -->
                                <div class="test-card">
                                    <h3>📅 Monthly Report</h3>
                                    <p>Test the comprehensive monthly progress report with detailed analytics.</p>
                                    <button type="submit" name="test_type" value="monthly_report" class="btn btn-primary btn-sm">
                                        Send Monthly Report
                                    </button>
                                    <div class="preview-box">
                                        <strong>Sent to:</strong> All admins & managers<br>
                                        <strong>Includes:</strong> Month stats, completion rate, project completions
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Users with Email -->
                <div class="card" style="margin-top: 24px;">
                    <div class="card-header">
                        <h3>👥 Users with Email Configured (<?php echo count($usersWithEmail); ?>)</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($usersWithEmail)): ?>
                            <div class="alert alert-warning">
                                ⚠️ No users have email addresses configured. Add email addresses in <a href="team.php">Team Management</a>.
                            </div>
                        <?php else: ?>
                            <div class="users-list">
                                <?php foreach ($usersWithEmail as $user): ?>
                                    <div class="user-item">
                                        <div>
                                            <strong><?php echo e($user['full_name']); ?></strong>
                                            <span style="margin-left: 8px; padding: 2px 8px; background: var(--bg-tertiary); border-radius: 4px; font-size: 11px;">
                                                <?php echo strtoupper($user['role']); ?>
                                            </span>
                                        </div>
                                        <div style="color: var(--text-secondary); font-size: 13px;">
                                            <?php echo e($user['email']); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Help -->
                <div class="card" style="margin-top: 24px; background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);">
                    <div class="card-header">
                        <h3>💡 Testing Tips</h3>
                    </div>
                    <div class="card-body">
                        <ul style="margin: 0; padding-left: 20px; line-height: 1.8;">
                            <li>Start with a <strong>Simple Test</strong> to verify basic email functionality</li>
                            <li>Check your spam/junk folder if you don't see test emails</li>
                            <li>Daily/Weekly/Monthly reports are sent to <strong>all admins and managers</strong>, not just the test email</li>
                            <li>View email logs in <code>logs/cron-errors.log</code> for debugging</li>
                            <li>For Gmail users: Use an <a href="https://support.google.com/accounts/answer/185833" target="_blank">App Password</a> instead of your regular password</li>
                            <li>If SMTP fails, check your firewall allows outbound connections on port 587/465</li>
                        </ul>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/theme.js"></script>
</body>
</html>
