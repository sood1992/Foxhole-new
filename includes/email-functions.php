<?php
/**
 * EMAIL NOTIFICATION SYSTEM
 * Handles all email notifications for task assignments, updates, and reports
 */

// Email configuration
if (!defined('EMAIL_FROM')) {
    define('EMAIL_FROM', 'noreply@' . ($_SERVER['HTTP_HOST'] ?? 'foxhole.com'));
}
if (!defined('EMAIL_FROM_NAME')) {
    define('EMAIL_FROM_NAME', SITE_NAME);
}

/**
 * Send an email using SMTP or PHP mail()
 *
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $htmlBody HTML email body
 * @param string $textBody Plain text email body (fallback)
 * @return bool Success status
 */
function sendEmail($to, $subject, $htmlBody, $textBody = '') {
    try {
        // Validate email address
        if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            error_log("Invalid email address: {$to}");
            return ['success' => false, 'error' => "Invalid email address: {$to}"];
        }

        // Load email configuration
        $emailConfigFile = __DIR__ . '/../config/email-config.json';
        $emailConfig = [];
        if (file_exists($emailConfigFile)) {
            $emailConfig = json_decode(file_get_contents($emailConfigFile), true) ?: [];
        }

        $useSmtp = $emailConfig['use_smtp'] ?? false;
        $fromEmail = $emailConfig['from_email'] ?? (defined('EMAIL_FROM') ? EMAIL_FROM : 'noreply@' . ($_SERVER['HTTP_HOST'] ?? 'foxhole.com'));
        $fromName = $emailConfig['from_name'] ?? (defined('EMAIL_FROM_NAME') ? EMAIL_FROM_NAME : SITE_NAME);

        if ($useSmtp && !empty($emailConfig['smtp_host'])) {
            // Use SMTP
            return sendEmailSMTP($to, $subject, $htmlBody, $emailConfig, $fromEmail, $fromName);
        } else {
            // Use PHP mail()
            if (!function_exists('mail')) {
                $error = "PHP mail() function is not available. Please configure SMTP in Email Configuration.";
                error_log($error);
                return ['success' => false, 'error' => $error];
            }

            $headers = [];
            $headers[] = 'MIME-Version: 1.0';
            $headers[] = 'Content-type: text/html; charset=utf-8';
            $headers[] = 'From: ' . $fromName . ' <' . $fromEmail . '>';
            $headers[] = 'Reply-To: ' . $fromEmail;
            $headers[] = 'X-Mailer: PHP/' . phpversion();

            $success = @mail($to, $subject, $htmlBody, implode("\r\n", $headers));

            if (!$success) {
                $error = "PHP mail() failed. This usually means your server's mail function is not configured. Please configure SMTP for reliable email delivery.";
                error_log("Failed to send email to {$to}: {$subject}");
                return ['success' => false, 'error' => $error];
            }

            return ['success' => true, 'error' => null];
        }
    } catch (Exception $e) {
        $error = "Email error: " . $e->getMessage();
        error_log($error);
        return ['success' => false, 'error' => $error];
    }
}

/**
 * Send email using SMTP with fsockopen
 *
 * @param string $to Recipient email
 * @param string $subject Subject
 * @param string $htmlBody HTML body
 * @param array $config SMTP configuration
 * @param string $fromEmail From email
 * @param string $fromName From name
 * @return bool Success status
 */
function sendEmailSMTP($to, $subject, $htmlBody, $config, $fromEmail, $fromName) {
    try {
        $host = $config['smtp_host'];
        $port = $config['smtp_port'] ?? 587;
        $username = $config['smtp_username'];
        $password = $config['smtp_password'];
        $encryption = $config['smtp_encryption'] ?? 'tls';

        // Create socket connection
        $timeout = 30;
        $errno = 0;
        $errstr = '';

        if ($encryption === 'ssl') {
            $socket = @fsockopen('ssl://' . $host, $port, $errno, $errstr, $timeout);
        } else {
            $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
        }

        if (!$socket) {
            $error = "SMTP connection failed: {$errstr} ({$errno})";
            error_log($error);
            return ['success' => false, 'error' => $error];
        }

        // Read server response
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '220') {
            $error = "SMTP server error: {$response}";
            error_log($error);
            fclose($socket);
            return ['success' => false, 'error' => $error];
        }

        // Say EHLO
        fputs($socket, "EHLO {$_SERVER['HTTP_HOST']}\r\n");
        $response = fgets($socket, 515);

        // Start TLS if needed
        if ($encryption === 'tls') {
            fputs($socket, "STARTTLS\r\n");
            $response = fgets($socket, 515);
            if (substr($response, 0, 3) != '220') {
                $error = "STARTTLS failed: {$response}";
                error_log($error);
                fclose($socket);
                return ['success' => false, 'error' => $error];
            }

            // Enable crypto
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);

            // Say EHLO again after STARTTLS
            fputs($socket, "EHLO {$_SERVER['HTTP_HOST']}\r\n");
            $response = fgets($socket, 515);
        }

        // Authenticate
        fputs($socket, "AUTH LOGIN\r\n");
        $response = fgets($socket, 515);

        fputs($socket, base64_encode($username) . "\r\n");
        $response = fgets($socket, 515);

        fputs($socket, base64_encode($password) . "\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '235') {
            $error = "SMTP authentication failed. Please check your username and password.";
            error_log($error . " - Server response: {$response}");
            fclose($socket);
            return ['success' => false, 'error' => $error];
        }

        // Send MAIL FROM
        fputs($socket, "MAIL FROM: <{$fromEmail}>\r\n");
        $response = fgets($socket, 515);

        // Send RCPT TO
        fputs($socket, "RCPT TO: <{$to}>\r\n");
        $response = fgets($socket, 515);

        // Send DATA
        fputs($socket, "DATA\r\n");
        $response = fgets($socket, 515);

        // Build message
        $message = "From: {$fromName} <{$fromEmail}>\r\n";
        $message .= "To: {$to}\r\n";
        $message .= "Subject: {$subject}\r\n";
        $message .= "MIME-Version: 1.0\r\n";
        $message .= "Content-Type: text/html; charset=utf-8\r\n";
        $message .= "\r\n";
        $message .= $htmlBody;
        $message .= "\r\n.\r\n";

        // Send message
        fputs($socket, $message);
        $response = fgets($socket, 515);

        // Quit
        fputs($socket, "QUIT\r\n");
        fclose($socket);

        return ['success' => true, 'error' => null];
    } catch (Exception $e) {
        $error = "SMTP error: " . $e->getMessage();
        error_log($error);
        return ['success' => false, 'error' => $error];
    }
}

/**
 * Get email template wrapper
 */
function getEmailTemplate($content, $title = '') {
    $siteUrl = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $siteName = SITE_NAME;

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background: #f5f5f5; }
        .container { max-width: 600px; margin: 20px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 32px 24px; text-align: center; }
        .header h1 { margin: 0; font-size: 28px; font-weight: 700; }
        .content { padding: 32px 24px; }
        .footer { background: #f8f9fa; padding: 20px 24px; text-align: center; font-size: 14px; color: #6c757d; border-top: 1px solid #e9ecef; }
        .button { display: inline-block; padding: 12px 24px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 8px; font-weight: 600; margin: 16px 0; }
        .task-box { background: #f8f9fa; padding: 16px; border-radius: 8px; margin: 16px 0; border-left: 4px solid #667eea; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .badge-urgent { background: #fee2e2; color: #991b1b; }
        .badge-high { background: #fed7aa; color: #9a3412; }
        .badge-medium { background: #dbeafe; color: #1e3a8a; }
        .badge-low { background: #e0e7ff; color: #3730a3; }
        .metric { background: #f8f9fa; padding: 16px; margin: 8px 0; border-radius: 8px; }
        .metric-label { font-size: 14px; color: #6c757d; }
        .metric-value { font-size: 24px; font-weight: 700; color: #333; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{$siteName}</h1>
        </div>
        <div class="content">
            {$content}
        </div>
        <div class="footer">
            <p>&copy; " . date('Y') . " {$siteName}. All rights reserved.</p>
            <p><a href="{$siteUrl}" style="color: #667eea; text-decoration: none;">Visit Dashboard</a></p>
        </div>
    </div>
</body>
</html>
HTML;
}

/**
 * Send email notification for task assignment
 * Note: Different from the in-app notification function in functions.php
 */
if (!function_exists('sendTaskAssignmentEmail')) {
    function sendTaskAssignmentEmail($taskId) {
        try {
            $db = getDBConnection();

        // Get task details
        $stmt = $db->prepare("
            SELECT t.*, p.project_name, p.client_name,
                   u.full_name as assigned_to_name, u.email as assigned_to_email,
                   creator.full_name as created_by_name
            FROM tasks t
            JOIN projects p ON t.project_id = p.id
            JOIN users u ON t.assigned_to = u.id
            LEFT JOIN users creator ON t.created_by = creator.id
            WHERE t.id = ?
        ");
        $stmt->execute([$taskId]);
        $task = $stmt->fetch();

        if (!$task || !$task['assigned_to_email']) {
            return false;
        }

        $priorityBadge = "<span class='badge badge-{$task['priority']}'>" . strtoupper($task['priority']) . "</span>";
        $dueDateText = $task['due_date'] ? date('M j, Y', strtotime($task['due_date'])) : 'No deadline';

        $content = <<<HTML
<h2>New Task Assigned to You</h2>
<p>Hi {$task['assigned_to_name']},</p>
<p>You have been assigned a new task by {$task['created_by_name']}.</p>

<div class="task-box">
    <h3 style="margin-top: 0;">{$task['task_name']}</h3>
    <p><strong>Project:</strong> {$task['project_name']}</p>
    <p><strong>Priority:</strong> {$priorityBadge}</p>
    <p><strong>Due Date:</strong> {$dueDateText}</p>
    <p><strong>Description:</strong><br>{$task['description']}</p>
</div>

<a href="http://{$_SERVER['HTTP_HOST']}/employee/tasks.php" class="button">View Task</a>

<p style="color: #6c757d; font-size: 14px;">This is an automated notification from {SITE_NAME}.</p>
HTML;

        $emailHtml = getEmailTemplate($content, 'New Task Assigned');

        return sendEmail(
            $task['assigned_to_email'],
            "[" . SITE_NAME . "] New Task Assigned: {$task['task_name']}",
            $emailHtml
        );
        } catch (Exception $e) {
            $error = "Task assignment notification error: " . $e->getMessage();
            error_log($error);
            return ['success' => false, 'error' => $error];
        }
    }
}

/**
 * Send email notification for task status update
 * Note: Different from the in-app notification function in functions.php
 */
if (!function_exists('sendTaskUpdateEmail')) {
    function sendTaskUpdateEmail($taskId, $oldStatus, $newStatus, $updatedBy) {
        try {
            $db = getDBConnection();

        // Get task and project details
        $stmt = $db->prepare("
            SELECT t.*, p.project_name, p.assigned_manager,
                   u.full_name as assigned_to_name,
                   updater.full_name as updater_name
            FROM tasks t
            JOIN projects p ON t.project_id = p.id
            LEFT JOIN users u ON t.assigned_to = u.id
            LEFT JOIN users updater ON updater.id = ?
            WHERE t.id = ?
        ");
        $stmt->execute([$updatedBy, $taskId]);
        $task = $stmt->fetch();

        if (!$task) {
            return false;
        }

        // Get admin and manager emails
        $recipients = [];

        // Get all admins
        $admins = $db->query("SELECT email FROM users WHERE role = 'admin' AND is_active = 1");
        foreach ($admins as $admin) {
            if ($admin['email']) $recipients[] = $admin['email'];
        }

        // Get project manager
        if ($task['assigned_manager']) {
            $manager = $db->prepare("SELECT email FROM users WHERE id = ? AND is_active = 1");
            $manager->execute([$task['assigned_manager']]);
            $mgr = $manager->fetch();
            if ($mgr && $mgr['email']) $recipients[] = $mgr['email'];
        }

        if (empty($recipients)) {
            return false;
        }

        $statusEmoji = [
            'todo' => '📋',
            'in_progress' => '🔄',
            'review' => '👀',
            'completed' => '✅',
            'blocked' => '🚫'
        ];

        $content = <<<HTML
<h2>Task Status Updated</h2>
<p>{$task['updater_name']} has updated a task status.</p>

<div class="task-box">
    <h3 style="margin-top: 0;">{$task['task_name']}</h3>
    <p><strong>Project:</strong> {$task['project_name']}</p>
    <p><strong>Assigned to:</strong> {$task['assigned_to_name']}</p>
    <p><strong>Status Change:</strong> {$statusEmoji[$oldStatus]} {$oldStatus} → {$statusEmoji[$newStatus]} {$newStatus}</p>
</div>

<a href="http://{$_SERVER['HTTP_HOST']}/admin/projects.php" class="button">View Project</a>
HTML;

        $emailHtml = getEmailTemplate($content, 'Task Status Updated');

        $allSuccess = true;
        $lastError = null;
        foreach ($recipients as $email) {
            $result = sendEmail(
                $email,
                "[" . SITE_NAME . "] Task Updated: {$task['task_name']}",
                $emailHtml
            );
            if (is_array($result) && !$result['success']) {
                $allSuccess = false;
                $lastError = $result['error'];
            }
        }

        return $allSuccess ? ['success' => true, 'error' => null] : ['success' => false, 'error' => $lastError];
        } catch (Exception $e) {
            $error = "Task update notification error: " . $e->getMessage();
            error_log($error);
            return ['success' => false, 'error' => $error];
        }
    }
}

/**
 * Generate and send daily progress report
 */
function sendDailyProgressReport() {
    try {
        $db = getDBConnection();
        $today = date('Y-m-d');

        // Get daily stats
        $stats = $db->query("
            SELECT
                COUNT(DISTINCT CASE WHEN t.status = 'completed' AND DATE(t.completed_date) = '{$today}' THEN t.id END) as tasks_completed,
                COUNT(DISTINCT CASE WHEN t.status = 'in_progress' THEN t.id END) as tasks_in_progress,
                COUNT(DISTINCT CASE WHEN t.status != 'completed' AND t.due_date < CURDATE() THEN t.id END) as tasks_overdue,
                COUNT(DISTINCT CASE WHEN tl.end_time IS NOT NULL AND DATE(tl.start_time) = '{$today}' THEN tl.id END) as time_sessions,
                COALESCE(SUM(CASE WHEN DATE(tl.start_time) = '{$today}' THEN tl.duration_minutes END), 0) / 60 as total_hours
            FROM tasks t
            LEFT JOIN time_logs tl ON t.id = tl.task_id
        ")->fetch();

        // Get top performers today
        $topPerformers = $db->query("
            SELECT u.full_name, COUNT(DISTINCT t.id) as completed_tasks
            FROM users u
            JOIN tasks t ON u.id = t.assigned_to
            WHERE t.status = 'completed' AND DATE(t.completed_date) = '{$today}'
            GROUP BY u.id
            ORDER BY completed_tasks DESC
            LIMIT 3
        ")->fetchAll();

        $topPerformersList = '';
        foreach ($topPerformers as $performer) {
            $topPerformersList .= "<li><strong>{$performer['full_name']}</strong> - {$performer['completed_tasks']} tasks</li>";
        }
        if (empty($topPerformersList)) {
            $topPerformersList = '<li><em>No tasks completed today</em></li>';
        }

        $content = <<<HTML
<h2>📊 Daily Progress Report</h2>
<p><strong>Date:</strong> {$today}</p>

<div style="display: grid; gap: 12px;">
    <div class="metric">
        <div class="metric-label">Tasks Completed Today</div>
        <div class="metric-value">{$stats['tasks_completed']}</div>
    </div>
    <div class="metric">
        <div class="metric-label">Tasks In Progress</div>
        <div class="metric-value">{$stats['tasks_in_progress']}</div>
    </div>
    <div class="metric">
        <div class="metric-label">Overdue Tasks</div>
        <div class="metric-value" style="color: #dc2626;">{$stats['tasks_overdue']}</div>
    </div>
    <div class="metric">
        <div class="metric-label">Total Hours Logged</div>
        <div class="metric-value">{$stats['total_hours']}</div>
    </div>
</div>

<h3>🏆 Top Performers Today</h3>
<ul>
    {$topPerformersList}
</ul>

<a href="http://{$_SERVER['HTTP_HOST']}/admin/analytics.php" class="button">View Full Analytics</a>
HTML;

        $emailHtml = getEmailTemplate($content, 'Daily Progress Report');

        // Send to all admins and managers
        $recipients = $db->query("
            SELECT DISTINCT email
            FROM users
            WHERE role IN ('admin', 'manager') AND is_active = 1 AND email IS NOT NULL
        ")->fetchAll();

        $success = true;
        foreach ($recipients as $recipient) {
            $result = sendEmail(
                $recipient['email'],
                "[" . SITE_NAME . "] Daily Progress Report - " . date('M j, Y'),
                $emailHtml
            );
            $success = $success && $result;
        }

        return $success;
    } catch (Exception $e) {
        error_log("Daily report error: " . $e->getMessage());
        return false;
    }
}

/**
 * Generate and send weekly progress report
 */
function sendWeeklyProgressReport() {
    try {
        $db = getDBConnection();
        $weekStart = date('Y-m-d', strtotime('monday this week'));
        $weekEnd = date('Y-m-d', strtotime('sunday this week'));

        // Get weekly stats
        $stats = $db->query("
            SELECT
                COUNT(DISTINCT CASE WHEN t.status = 'completed' AND DATE(t.completed_date) BETWEEN '{$weekStart}' AND '{$weekEnd}' THEN t.id END) as tasks_completed,
                COUNT(DISTINCT CASE WHEN t.status != 'completed' AND t.due_date < CURDATE() THEN t.id END) as tasks_overdue,
                COALESCE(SUM(CASE WHEN DATE(tl.start_time) BETWEEN '{$weekStart}' AND '{$weekEnd}' THEN tl.duration_minutes END), 0) / 60 as total_hours,
                COUNT(DISTINCT CASE WHEN t.created_at BETWEEN '{$weekStart}' AND '{$weekEnd}' THEN t.id END) as tasks_created
            FROM tasks t
            LEFT JOIN time_logs tl ON t.id = tl.task_id
        ")->fetch();

        // Get top performers this week
        $topPerformers = $db->query("
            SELECT u.full_name, u.email,
                   COUNT(DISTINCT t.id) as completed_tasks,
                   COALESCE(SUM(tl.duration_minutes), 0) / 60 as hours_logged
            FROM users u
            LEFT JOIN tasks t ON u.id = t.assigned_to AND t.status = 'completed' AND DATE(t.completed_date) BETWEEN '{$weekStart}' AND '{$weekEnd}'
            LEFT JOIN time_logs tl ON u.id = tl.user_id AND DATE(tl.start_time) BETWEEN '{$weekStart}' AND '{$weekEnd}'
            WHERE u.role IN ('employee', 'manager') AND u.is_active = 1
            GROUP BY u.id
            HAVING completed_tasks > 0
            ORDER BY completed_tasks DESC, hours_logged DESC
            LIMIT 5
        ")->fetchAll();

        $topPerformersList = '';
        foreach ($topPerformers as $idx => $performer) {
            $medal = ['🥇', '🥈', '🥉'][$idx] ?? '⭐';
            $topPerformersList .= "<li>{$medal} <strong>{$performer['full_name']}</strong> - {$performer['completed_tasks']} tasks, " . round($performer['hours_logged'], 1) . "h logged</li>";
        }
        if (empty($topPerformersList)) {
            $topPerformersList = '<li><em>No tasks completed this week</em></li>';
        }

        $content = <<<HTML
<h2>📈 Weekly Progress Report</h2>
<p><strong>Week:</strong> {$weekStart} to {$weekEnd}</p>

<div style="display: grid; gap: 12px;">
    <div class="metric">
        <div class="metric-label">Tasks Completed This Week</div>
        <div class="metric-value">{$stats['tasks_completed']}</div>
    </div>
    <div class="metric">
        <div class="metric-label">Tasks Created This Week</div>
        <div class="metric-value">{$stats['tasks_created']}</div>
    </div>
    <div class="metric">
        <div class="metric-label">Currently Overdue</div>
        <div class="metric-value" style="color: #dc2626;">{$stats['tasks_overdue']}</div>
    </div>
    <div class="metric">
        <div class="metric-label">Total Hours Logged</div>
        <div class="metric-value">{$stats['total_hours']}</div>
    </div>
</div>

<h3>🏆 Top Performers This Week</h3>
<ul>
    {$topPerformersList}
</ul>

<a href="http://{$_SERVER['HTTP_HOST']}/admin/reports.php" class="button">View Detailed Reports</a>
HTML;

        $emailHtml = getEmailTemplate($content, 'Weekly Progress Report');

        // Send to all admins and managers
        $recipients = $db->query("
            SELECT DISTINCT email
            FROM users
            WHERE role IN ('admin', 'manager') AND is_active = 1 AND email IS NOT NULL
        ")->fetchAll();

        $success = true;
        foreach ($recipients as $recipient) {
            $result = sendEmail(
                $recipient['email'],
                "[" . SITE_NAME . "] Weekly Progress Report - Week of " . date('M j', strtotime($weekStart)),
                $emailHtml
            );
            $success = $success && $result;
        }

        return $success;
    } catch (Exception $e) {
        error_log("Weekly report error: " . $e->getMessage());
        return false;
    }
}

/**
 * Generate and send monthly progress report
 */
function sendMonthlyProgressReport() {
    try {
        $db = getDBConnection();
        $monthStart = date('Y-m-01');
        $monthEnd = date('Y-m-t');
        $monthName = date('F Y');

        // Get monthly stats
        $stats = $db->query("
            SELECT
                COUNT(DISTINCT CASE WHEN t.status = 'completed' AND DATE(t.completed_date) BETWEEN '{$monthStart}' AND '{$monthEnd}' THEN t.id END) as tasks_completed,
                COUNT(DISTINCT CASE WHEN t.created_at BETWEEN '{$monthStart}' AND '{$monthEnd}' THEN t.id END) as tasks_created,
                COUNT(DISTINCT CASE WHEN p.status = 'completed' AND DATE(p.completed_date) BETWEEN '{$monthStart}' AND '{$monthEnd}' THEN p.id END) as projects_completed,
                COALESCE(SUM(CASE WHEN DATE(tl.start_time) BETWEEN '{$monthStart}' AND '{$monthEnd}' THEN tl.duration_minutes END), 0) / 60 as total_hours
            FROM tasks t
            LEFT JOIN time_logs tl ON t.id = tl.task_id
            LEFT JOIN projects p ON t.project_id = p.id
        ")->fetch();

        // Get top performers this month
        $topPerformers = $db->query("
            SELECT u.full_name,
                   COUNT(DISTINCT t.id) as completed_tasks,
                   COALESCE(SUM(tl.duration_minutes), 0) / 60 as hours_logged
            FROM users u
            LEFT JOIN tasks t ON u.id = t.assigned_to AND t.status = 'completed' AND DATE(t.completed_date) BETWEEN '{$monthStart}' AND '{$monthEnd}'
            LEFT JOIN time_logs tl ON u.id = tl.user_id AND DATE(tl.start_time) BETWEEN '{$monthStart}' AND '{$monthEnd}'
            WHERE u.role IN ('employee', 'manager') AND u.is_active = 1
            GROUP BY u.id
            HAVING completed_tasks > 0
            ORDER BY completed_tasks DESC, hours_logged DESC
            LIMIT 5
        ")->fetchAll();

        $topPerformersList = '';
        foreach ($topPerformers as $idx => $performer) {
            $medal = ['🥇', '🥈', '🥉'][$idx] ?? '⭐';
            $topPerformersList .= "<li>{$medal} <strong>{$performer['full_name']}</strong> - {$performer['completed_tasks']} tasks, " . round($performer['hours_logged'], 1) . "h logged</li>";
        }
        if (empty($topPerformersList)) {
            $topPerformersList = '<li><em>No tasks completed this month</em></li>';
        }

        // Calculate completion rate
        $completionRate = $stats['tasks_created'] > 0 ? round(($stats['tasks_completed'] / $stats['tasks_created']) * 100) : 0;

        $content = <<<HTML
<h2>📅 Monthly Progress Report</h2>
<p><strong>Month:</strong> {$monthName}</p>

<div style="display: grid; gap: 12px;">
    <div class="metric">
        <div class="metric-label">Tasks Completed</div>
        <div class="metric-value">{$stats['tasks_completed']}</div>
    </div>
    <div class="metric">
        <div class="metric-label">Tasks Created</div>
        <div class="metric-value">{$stats['tasks_created']}</div>
    </div>
    <div class="metric">
        <div class="metric-label">Projects Completed</div>
        <div class="metric-value">{$stats['projects_completed']}</div>
    </div>
    <div class="metric">
        <div class="metric-label">Total Hours Logged</div>
        <div class="metric-value">{$stats['total_hours']}</div>
    </div>
    <div class="metric">
        <div class="metric-label">Completion Rate</div>
        <div class="metric-value" style="color: #16a34a;">{$completionRate}%</div>
    </div>
</div>

<h3>🏆 Top Performers This Month</h3>
<ul>
    {$topPerformersList}
</ul>

<a href="http://{$_SERVER['HTTP_HOST']}/admin/reports.php?type=monthly" class="button">View Detailed Monthly Report</a>
HTML;

        $emailHtml = getEmailTemplate($content, 'Monthly Progress Report');

        // Send to all admins and managers
        $recipients = $db->query("
            SELECT DISTINCT email
            FROM users
            WHERE role IN ('admin', 'manager') AND is_active = 1 AND email IS NOT NULL
        ")->fetchAll();

        $success = true;
        foreach ($recipients as $recipient) {
            $result = sendEmail(
                $recipient['email'],
                "[" . SITE_NAME . "] Monthly Progress Report - {$monthName}",
                $emailHtml
            );
            $success = $success && $result;
        }

        return $success;
    } catch (Exception $e) {
        error_log("Monthly report error: " . $e->getMessage());
        return false;
    }
}
?>
