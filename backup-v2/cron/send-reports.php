<?php
/**
 * CRON JOB SCRIPT - Email Reports
 *
 * This script sends automated progress reports via email
 *
 * SETUP INSTRUCTIONS:
 * Add these lines to your crontab (crontab -e):
 *
 * # Daily report at 6 PM every day
 * 0 18 * * * /usr/bin/php /path/to/foxhole/cron/send-reports.php daily
 *
 * # Weekly report every Monday at 9 AM
 * 0 9 * * 1 /usr/bin/php /path/to/foxhole/cron/send-reports.php weekly
 *
 * # Monthly report on 1st day of month at 9 AM
 * 0 9 1 * * /usr/bin/php /path/to/foxhole/cron/send-reports.php monthly
 *
 * OR use a single combined schedule:
 * # Run every day at 6 PM, script decides which reports to send
 * 0 18 * * * /usr/bin/php /path/to/foxhole/cron/send-reports.php auto
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/cron-errors.log');

// Include required files
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/email-functions.php';

// Get report type from command line argument
$reportType = $argv[1] ?? 'auto';

// Log execution
$logFile = __DIR__ . '/../logs/cron-reports.log';
$logEntry = date('Y-m-d H:i:s') . " - Starting report generation: {$reportType}\n";
file_put_contents($logFile, $logEntry, FILE_APPEND);

try {
    $results = [];

    switch ($reportType) {
        case 'daily':
            echo "Sending daily progress report...\n";
            $results['daily'] = sendDailyProgressReport();
            echo $results['daily'] ? "Daily report sent successfully.\n" : "Failed to send daily report.\n";
            break;

        case 'weekly':
            echo "Sending weekly progress report...\n";
            $results['weekly'] = sendWeeklyProgressReport();
            echo $results['weekly'] ? "Weekly report sent successfully.\n" : "Failed to send weekly report.\n";
            break;

        case 'monthly':
            echo "Sending monthly progress report...\n";
            $results['monthly'] = sendMonthlyProgressReport();
            echo $results['monthly'] ? "Monthly report sent successfully.\n" : "Failed to send monthly report.\n";
            break;

        case 'auto':
            // Automatic mode - determine what to send based on date
            $today = date('N'); // 1 (Monday) through 7 (Sunday)
            $dayOfMonth = date('j');

            // Always send daily report
            echo "Sending daily progress report...\n";
            $results['daily'] = sendDailyProgressReport();
            echo $results['daily'] ? "Daily report sent successfully.\n" : "Failed to send daily report.\n";

            // Send weekly report on Mondays
            if ($today == 1) {
                echo "Sending weekly progress report...\n";
                $results['weekly'] = sendWeeklyProgressReport();
                echo $results['weekly'] ? "Weekly report sent successfully.\n" : "Failed to send weekly report.\n";
            }

            // Send monthly report on the 1st of the month
            if ($dayOfMonth == 1) {
                echo "Sending monthly progress report...\n";
                $results['monthly'] = sendMonthlyProgressReport();
                echo $results['monthly'] ? "Monthly report sent successfully.\n" : "Failed to send monthly report.\n";
            }
            break;

        default:
            echo "Unknown report type: {$reportType}\n";
            echo "Usage: php send-reports.php [daily|weekly|monthly|auto]\n";
            exit(1);
    }

    // Log results
    $logEntry = date('Y-m-d H:i:s') . " - Report generation completed: " . json_encode($results) . "\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);

    exit(0);

} catch (Exception $e) {
    $errorMsg = "Error generating reports: " . $e->getMessage() . "\n";
    echo $errorMsg;
    error_log($errorMsg);

    $logEntry = date('Y-m-d H:i:s') . " - ERROR: " . $e->getMessage() . "\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);

    exit(1);
}
?>
