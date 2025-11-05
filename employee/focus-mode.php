<?php
require_once '../config/config.php';
require_once '../includes/functions.php';
if (!isLoggedIn() || !hasRole('employee')) redirect('../login.php');

$db = getDBConnection();
$currentUser = getCurrentUser();

// Get active tasks
$stmt = $db->prepare("
    SELECT t.*, p.project_name FROM tasks t
    JOIN projects p ON t.project_id = p.id
    WHERE t.assigned_to = ? AND t.status != 'completed'
    ORDER BY
        CASE t.priority
            WHEN 'urgent' THEN 1
            WHEN 'high' THEN 2
            WHEN 'medium' THEN 3
            WHEN 'low' THEN 4
        END,
        t.due_date ASC
    LIMIT 10
");
$stmt->execute([$currentUser['id']]);
$tasks = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Focus Mode - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/vien-v3.css">

    <!-- Synto Dashboard Template Design -->
    <link rel="stylesheet" href="../assets/css/synto-design.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body.focus-active {
            overflow: hidden;
        }
        .focus-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.95);
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
        }
        .focus-overlay.active {
            display: flex;
        }
        .focus-content {
            max-width: 800px;
            width: 90%;
            color: white;
            text-align: center;
            animation: fadeIn 0.5s ease-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .focus-timer {
            font-size: 120px;
            font-weight: bold;
            margin: 40px 0;
            font-family: 'Courier New', monospace;
            letter-spacing: 10px;
        }
        .focus-task-name {
            font-size: 32px;
            margin: 20px 0;
            opacity: 0.9;
        }
        .focus-controls {
            display: flex;
            gap: 20px;
            justify-content: center;
            margin-top: 40px;
        }
        .focus-btn {
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 15px 30px;
            border-radius: 50px;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
            backdrop-filter: blur(10px);
        }
        .focus-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.5);
            transform: scale(1.05);
        }
        .focus-btn.primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-color: #667eea;
        }
        .focus-btn.danger {
            background: #ef4444;
            border-color: #dc2626;
        }
        .task-selector {
            margin: 20px 0;
        }
        .task-option {
            background: var(--bg-secondary);
            padding: 15px;
            margin: 10px 0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid transparent;
        }
        .task-option:hover {
            background: var(--bg-tertiary);
            border-color: #667eea;
            transform: translateX(5px);
        }
        .task-option.selected {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: #667eea;
        }
        .break-reminder-settings {
            background: var(--bg-secondary);
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .setting-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px 0;
            border-bottom: 1px solid var(--border-color);
        }
        .setting-row:last-child {
            border-bottom: none;
        }
        .toggle-switch {
            position: relative;
            width: 60px;
            height: 30px;
            background: #ccc;
            border-radius: 15px;
            cursor: pointer;
            transition: background 0.3s;
        }
        .toggle-switch.active {
            background: #10b981;
        }
        .toggle-switch::after {
            content: '';
            position: absolute;
            top: 3px;
            left: 3px;
            width: 24px;
            height: 24px;
            background: white;
            border-radius: 50%;
            transition: left 0.3s;
        }
        .toggle-switch.active::after {
            left: 33px;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include '../includes/v3-employee-sidebar.php'; ?>
        <div class="main-content">
            <?php include '../includes/v3-header.php'; ?>
            <div class="content-wrapper">
                <h1><i class="fas fa-brain"></i> Focus Mode</h1>
                <p style="color:var(--text-secondary);margin-bottom:30px;">
                    Distraction-free work environment to maximize concentration
                </p>

                <!-- Task Selection -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3>Select a Task to Focus On</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($tasks)): ?>
                        <p style="text-align:center;color:var(--text-secondary);padding:40px;">
                            No active tasks available. Create or get assigned to a task first!
                        </p>
                        <?php else: ?>
                        <div class="task-selector" id="taskSelector">
                            <?php foreach ($tasks as $task): ?>
                            <div class="task-option" data-task-id="<?php echo $task['id']; ?>" data-task-name="<?php echo e($task['task_name']); ?>" onclick="selectTask(this)">
                                <div style="display:flex;justify-content:space-between;align-items:center;">
                                    <div>
                                        <strong><?php echo e($task['task_name']); ?></strong>
                                        <div style="font-size:13px;color:var(--text-secondary);margin-top:5px;">
                                            <?php echo e($task['project_name']); ?>
                                            <?php if ($task['due_date']): ?>
                                            • Due: <?php echo date('M j, Y', strtotime($task['due_date'])); ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <span class="badge" style="background:<?php
                                        echo $task['priority'] === 'urgent' ? '#ef4444' :
                                            ($task['priority'] === 'high' ? '#f59e0b' :
                                            ($task['priority'] === 'medium' ? '#3b82f6' : '#6b7280'));
                                    ?>;">
                                        <?php echo ucfirst($task['priority']); ?>
                                    </span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Break Reminder Settings -->
                <div class="dashboard-card" style="margin-top:20px;">
                    <div class="card-header">
                        <h3><i class="fas ri-notification-3-line"></i> Break Reminders</h3>
                    </div>
                    <div class="card-body">
                        <div class="break-reminder-settings">
                            <div class="setting-row">
                                <div>
                                    <strong>Enable Break Reminders</strong>
                                    <div style="font-size:13px;color:var(--text-secondary);">Get notified to take breaks</div>
                                </div>
                                <div class="toggle-switch" id="breakToggle" onclick="toggleBreakReminders()"></div>
                            </div>
                            <div class="setting-row">
                                <div>
                                    <strong>Reminder Interval</strong>
                                    <div style="font-size:13px;color:var(--text-secondary);">How often to remind you</div>
                                </div>
                                <select id="breakInterval" class="form-control" style="width:auto;" onchange="saveBreakSettings()">
                                    <option value="30">Every 30 minutes</option>
                                    <option value="45">Every 45 minutes</option>
                                    <option value="60" selected>Every hour</option>
                                    <option value="90">Every 90 minutes</option>
                                    <option value="120">Every 2 hours</option>
                                </select>
                            </div>
                            <div class="setting-row">
                                <div>
                                    <strong>Break Duration</strong>
                                    <div style="font-size:13px;color:var(--text-secondary);">Suggested break length</div>
                                </div>
                                <select id="breakDuration" class="form-control" style="width:auto;" onchange="saveBreakSettings()">
                                    <option value="5" selected>5 minutes</option>
                                    <option value="10">10 minutes</option>
                                    <option value="15">15 minutes</option>
                                    <option value="20">20 minutes</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (!empty($tasks)): ?>
                <div style="text-align:center;margin:30px 0;">
                    <button id="startFocusBtn" class="btn btn-primary" style="padding:20px 50px;font-size:20px;" onclick="startFocusMode()" disabled>
                        <i class="fas fa-brain"></i> Enter Focus Mode
                    </button>
                    <p style="color:var(--text-secondary);margin-top:15px;">Select a task above to begin</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Focus Mode Overlay -->
    <div class="focus-overlay" id="focusOverlay">
        <div class="focus-content">
            <div class="focus-task-name" id="focusTaskName">Task Name</div>
            <div class="focus-timer" id="focusTimer">00:00:00</div>
            <div style="opacity:0.7;">Working on this task...</div>
            <div class="focus-controls">
                <button class="focus-btn" onclick="pauseFocus()">
                    <i class="fas fa-pause"></i> Pause
                </button>
                <button class="focus-btn danger" onclick="exitFocusMode()">
                    <i class="fas fa-times"></i> Exit Focus Mode
                </button>
            </div>
        </div>
    </div>

    <script>
        let selectedTaskId = null;
        let selectedTaskName = '';
        let focusStartTime = null;
        let focusTimerId = null;
        let breakReminderInterval = null;
        let breakSettings = {
            enabled: false,
            interval: 60,
            duration: 5
        };

        function selectTask(element) {
            // Remove previous selection
            document.querySelectorAll('.task-option').forEach(opt => {
                opt.classList.remove('selected');
            });

            // Select this task
            element.classList.add('selected');
            selectedTaskId = element.dataset.taskId;
            selectedTaskName = element.dataset.taskName;

            // Enable focus button
            document.getElementById('startFocusBtn').disabled = false;
        }

        function startFocusMode() {
            if (!selectedTaskId) {
                alert('Please select a task first');
                return;
            }

            // Start time tracking
            fetch('../api/time-tracking.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'start',
                    task_id: selectedTaskId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Enter focus mode
                    document.getElementById('focusTaskName').textContent = selectedTaskName;
                    document.getElementById('focusOverlay').classList.add('active');
                    document.body.classList.add('focus-active');

                    focusStartTime = Date.now();
                    startFocusTimer();

                    // Start break reminders if enabled
                    if (breakSettings.enabled) {
                        startBreakReminders();
                    }
                } else {
                    alert('Error starting timer: ' + data.message);
                }
            });
        }

        function startFocusTimer() {
            focusTimerId = setInterval(() => {
                const elapsed = Math.floor((Date.now() - focusStartTime) / 1000);
                const hours = Math.floor(elapsed / 3600);
                const minutes = Math.floor((elapsed % 3600) / 60);
                const seconds = elapsed % 60;

                document.getElementById('focusTimer').textContent =
                    `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            }, 1000);
        }

        function pauseFocus() {
            // Not implemented - could pause the timer
            alert('Pause functionality coming soon!');
        }

        function exitFocusMode() {
            // Stop time tracking
            fetch('../api/time-tracking.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'stop_by_task',
                    task_id: selectedTaskId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Exit focus mode
                    document.getElementById('focusOverlay').classList.remove('active');
                    document.body.classList.remove('focus-active');

                    if (focusTimerId) {
                        clearInterval(focusTimerId);
                        focusTimerId = null;
                    }

                    if (breakReminderInterval) {
                        clearInterval(breakReminderInterval);
                        breakReminderInterval = null;
                    }

                    alert('Great work! Time logged successfully.');
                } else {
                    alert('Error stopping timer: ' + data.message);
                }
            });
        }

        function toggleBreakReminders() {
            const toggle = document.getElementById('breakToggle');
            breakSettings.enabled = !breakSettings.enabled;

            if (breakSettings.enabled) {
                toggle.classList.add('active');
            } else {
                toggle.classList.remove('active');
            }

            saveBreakSettings();
        }

        function saveBreakSettings() {
            breakSettings.interval = parseInt(document.getElementById('breakInterval').value);
            breakSettings.duration = parseInt(document.getElementById('breakDuration').value);

            fetch('../api/productivity.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'update_break_settings',
                    reminder_enabled: breakSettings.enabled ? 1 : 0,
                    interval_minutes: breakSettings.interval,
                    break_duration_minutes: breakSettings.duration
                })
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    alert('Error saving settings: ' + data.message);
                }
            });
        }

        function loadBreakSettings() {
            fetch('../api/productivity.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'get_break_settings' })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    breakSettings = {
                        enabled: data.settings.reminder_enabled == 1,
                        interval: parseInt(data.settings.interval_minutes),
                        duration: parseInt(data.settings.break_duration_minutes)
                    };

                    // Update UI
                    if (breakSettings.enabled) {
                        document.getElementById('breakToggle').classList.add('active');
                    }
                    document.getElementById('breakInterval').value = breakSettings.interval;
                    document.getElementById('breakDuration').value = breakSettings.duration;
                }
            });
        }

        function startBreakReminders() {
            if (breakReminderInterval) {
                clearInterval(breakReminderInterval);
            }

            breakReminderInterval = setInterval(() => {
                if (Notification.permission === 'granted') {
                    new Notification('Time for a break!', {
                        body: `Take a ${breakSettings.duration} minute break to recharge.`,
                        icon: '../assets/images/logo.png'
                    });
                } else {
                    alert(`Time for a break! Take ${breakSettings.duration} minutes to recharge.`);
                }
            }, breakSettings.interval * 60 * 1000);
        }

        // Request notification permission
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }

        // Load settings on page load
        loadBreakSettings();
    </script>
    <script src="../assets/js/theme.js"></script>

    <!-- Synto Dashboard Interactions -->
    <script src="../assets/js/synto-interactions.js"></script>
</body>
</html>
