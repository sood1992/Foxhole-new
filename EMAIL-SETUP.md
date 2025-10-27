# Email Notification System Setup Guide

## Overview

The Foxhole productivity platform includes a comprehensive email notification system that sends:

1. **Task Assignment Notifications** - Employees receive emails when tasks are assigned to them
2. **Task Update Notifications** - Admins and PMs receive emails when task statuses change
3. **Daily Progress Reports** - Automated daily summaries sent to admins/PMs
4. **Weekly Progress Reports** - Comprehensive weekly summaries every Monday
5. **Monthly Progress Reports** - Detailed monthly reports on the 1st of each month

---

## Email Configuration

### Step 1: Configure PHP Mail Settings

Edit your `php.ini` file to configure email settings:

```ini
[mail function]
SMTP = smtp.yourdomain.com
smtp_port = 587
sendmail_from = noreply@yourdomain.com
```

Or if using sendmail:
```ini
sendmail_path = /usr/sbin/sendmail -t -i
```

### Step 2: Test Email Functionality

Create a test script to verify emails work:

```php
<?php
mail('your-email@example.com', 'Test Email', 'If you receive this, email is working!');
?>
```

---

## Automated Reports Setup

### Option 1: Cron Jobs (Recommended)

Add these to your crontab (`crontab -e`):

```bash
# Daily report every day at 6 PM
0 18 * * * /usr/bin/php /path/to/foxhole/cron/send-reports.php daily

# Weekly report every Monday at 9 AM
0 9 * * 1 /usr/bin/php /path/to/foxhole/cron/send-reports.php weekly

# Monthly report on 1st day of month at 9 AM
0 9 1 * * /usr/bin/php /path/to/foxhole/cron/send-reports.php monthly
```

**Or use automatic scheduling (easier):**

```bash
# Run daily at 6 PM - automatically sends appropriate reports
0 18 * * * /usr/bin/php /path/to/foxhole/cron/send-reports.php auto
```

The `auto` mode:
- Sends daily report every day
- Sends weekly report on Mondays
- Sends monthly report on the 1st of each month

### Option 2: cPanel Cron Jobs

1. Log into cPanel
2. Go to "Cron Jobs"
3. Add a new cron job:
   - **Minute:** 0
   - **Hour:** 18
   - **Day:** *
   - **Month:** *
   - **Weekday:** *
   - **Command:** `/usr/bin/php /home/yourusername/public_html/foxhole/cron/send-reports.php auto`

### Option 3: Manual Testing

You can manually trigger reports for testing:

```bash
# Test daily report
php cron/send-reports.php daily

# Test weekly report
php cron/send-reports.php weekly

# Test monthly report
php cron/send-reports.php monthly
```

---

## Task Notification Integration

### Automatic Notifications

The following actions automatically trigger email notifications:

#### 1. Task Assignment Notifications

**When:** A task is assigned to an employee
**Recipients:** The assigned employee
**Trigger:** Automatically called when tasks are created/updated

**To integrate into existing code:**

```php
// In your task assignment/creation code
require_once 'includes/email-functions.php';

// After creating/updating a task
$taskId = $db->lastInsertId(); // or existing task ID
notifyTaskAssignment($taskId);
```

#### 2. Task Update Notifications

**When:** Task status changes
**Recipients:** All admins and the project manager
**Trigger:** Called when task status is updated

```php
// In your task update code
require_once 'includes/email-functions.php';

// After updating task status
$oldStatus = 'todo';
$newStatus = 'in_progress';
$updatedBy = $currentUser['id'];
notifyTaskUpdate($taskId, $oldStatus, $newStatus, $updatedBy);
```

---

## Email Functions Reference

### Available Functions

```php
// Send task assignment notification
notifyTaskAssignment($taskId);

// Send task update notification
notifyTaskUpdate($taskId, $oldStatus, $newStatus, $updatedBy);

// Send daily progress report
sendDailyProgressReport();

// Send weekly progress report
sendWeeklyProgressReport();

// Send monthly progress report
sendMonthlyProgressReport();

// Send custom email
sendEmail($to, $subject, $htmlBody);
```

---

## Email Templates

All emails use a professional HTML template with:
- Purple gradient header matching your brand
- Responsive design for mobile devices
- Clear call-to-action buttons
- Branded footer with site link

### Customizing Email Templates

Edit `includes/email-functions.php` and modify the `getEmailTemplate()` function to customize:
- Header colors
- Logo placement
- Footer text
- Button styles

---

## Troubleshooting

### Emails Not Sending

**Check 1: PHP Mail Configuration**
```bash
php -i | grep sendmail
```

**Check 2: Test Basic PHP Mail**
```php
<?php
$result = mail('test@example.com', 'Test', 'Test message');
var_dump($result); // Should be TRUE
?>
```

**Check 3: Check Error Logs**
```bash
tail -f logs/cron-errors.log
tail -f logs/cron-reports.log
```

**Check 4: Verify User Emails**
Ensure users in the database have valid email addresses:
```sql
SELECT username, email FROM users WHERE role IN ('admin', 'manager', 'employee');
```

### Cron Jobs Not Running

**Check cron is enabled:**
```bash
service cron status
```

**Check cron logs:**
```bash
grep CRON /var/log/syslog
```

**Make script executable:**
```bash
chmod +x cron/send-reports.php
```

**Test manually:**
```bash
php cron/send-reports.php daily
```

---

## Email Report Contents

### Daily Report Includes:
- Tasks completed today
- Tasks currently in progress
- Overdue tasks count
- Total hours logged today
- Top 3 performers of the day

### Weekly Report Includes:
- Tasks completed this week
- Tasks created this week
- Currently overdue tasks
- Total hours logged
- Top 5 performers with hours logged

### Monthly Report Includes:
- Tasks completed this month
- Tasks created this month
- Projects completed this month
- Total hours logged
- Completion rate percentage
- Top 5 performers of the month

---

## Best Practices

1. **Set up email logging** - Monitor email delivery
2. **Test before deploying** - Send test emails to yourself first
3. **Verify recipient lists** - Ensure admins/managers have valid emails
4. **Monitor cron execution** - Check logs regularly
5. **Adjust timing** - Schedule reports at convenient times for your timezone
6. **Backup email data** - Keep logs of sent emails

---

## Advanced Configuration

### Using SMTP Instead of PHP Mail

For better deliverability, consider using SMTP with PHPMailer:

```php
// Install via composer: composer require phpmailer/phpmailer

use PHPMailer\PHPMailer\PHPMailer;

$mail = new PHPMailer();
$mail->isSMTP();
$mail->Host = 'smtp.gmail.com';
$mail->SMTPAuth = true;
$mail->Username = 'your-email@gmail.com';
$mail->Password = 'your-app-password';
$mail->SMTPSecure = 'tls';
$mail->Port = 587;
```

### Email Rate Limiting

If sending many emails, add rate limiting:

```php
foreach ($recipients as $recipient) {
    sendEmail($recipient, $subject, $body);
    usleep(100000); // Wait 0.1 seconds between emails
}
```

---

## Security Considerations

1. **Validate email addresses** - Don't send to unverified emails
2. **Rate limiting** - Prevent email spam
3. **Secure credentials** - Never commit SMTP passwords to git
4. **Sanitize content** - Escape HTML in email bodies
5. **Use app passwords** - For Gmail/SMTP use app-specific passwords

---

## Support

For issues or questions:
1. Check error logs in `logs/` directory
2. Verify cron job execution
3. Test email functions manually
4. Check PHP mail configuration

---

## Summary

✅ Email notifications for task assignments
✅ Task update notifications to admins/PMs
✅ Automated daily progress reports
✅ Automated weekly summaries
✅ Automated monthly reports
✅ Professional HTML email templates
✅ Easy cron job setup
✅ Comprehensive error logging

Your team will now receive timely notifications and regular progress updates!
