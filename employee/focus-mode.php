<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    redirect('../login.php');
}

$db = getDBConnection();
$currentUser = getCurrentUser();

$taskId = intval($_GET['task_id'] ?? 0);

if (!$taskId) {
    redirect('tasks.php');
}

// Get task details
$stmt = $db->prepare("
    SELECT t.*, p.project_name, p.client_name
    FROM tasks t
    JOIN projects p ON t.project_id = p.id
    WHERE t.id = ? AND t.assigned_to = ?
");
$stmt->execute([$taskId, $currentUser['id']]);
$task = $stmt->fetch();

if (!$task) {
    redirect('tasks.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Focus Mode - <?php echo e($task['task_name']); ?></title>
    <link rel="stylesheet" href="../assets/css/vien-v3.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: #1a1a1a;
            color: #ffffff;
            overflow: hidden;
            margin: 0;
            padding: 0;
            font-family: 'Nunito', sans-serif;
        }

        .focus-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 40px;
            max-width: 900px;
            margin: 0 auto;
        }

        .focus-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .focus-title {
            font-size: 48px;
            font-weight: 700;
            margin-bottom: 20px;
            line-height: 1.2;
        }

        .focus-meta {
            font-size: 18px;
            opacity: 0.7;
        }

        .focus-description {
            font-size: 20px;
            line-height: 1.8;
            opacity: 0.9;
            margin-bottom: 40px;
            padding: 30px;
            background: rgba(255,255,255,0.05);
            border-radius: 20px;
            min-height: 200px;
        }

        .focus-timer {
            font-size: 72px;
            font-weight: 700;
            margin-bottom: 30px;
            letter-spacing: 4px;
        }

        .focus-controls {
            display: flex;
            gap: 20px;
            margin-bottom: 40px;
        }

        .focus-btn {
            padding: 15px 40px;
            font-size: 18px;
            border: 2px solid rgba(255,255,255,0.2);
            background: rgba(255,255,255,0.1);
            color: white;
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .focus-btn:hover {
            background: rgba(255,255,255,0.2);
            border-color: rgba(255,255,255,0.4);
        }

        .focus-btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-color: transparent;
        }

        .focus-btn-primary:hover {
            transform: scale(1.05);
        }

        .focus-stats {
            display: flex;
            gap: 40px;
            margin-top: 40px;
        }

        .focus-stat {
            text-align: center;
        }

        .focus-stat-value {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .focus-stat-label {
            font-size: 14px;
            opacity: 0.6;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .exit-btn {
            position: fixed;
            top: 30px;
            right: 30px;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: rgba(255,255,255,0.1);
            border: 2px solid rgba(255,255,255,0.2);
            color: white;
            font-size: 24px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .exit-btn:hover {
            background: rgba(255,255,255,0.2);
            transform: rotate(90deg);
        }

        @keyframes breathe {
            0%, 100% { opacity: 0.5; transform: scale(1); }
            50% { opacity: 1; transform: scale(1.02); }
        }

        .breathing {
            animation: breathe 4s ease-in-out infinite;
        }
    </style>
</head>
<body>
    <button class="exit-btn" onclick="exitFocusMode()" title="Exit Focus Mode (ESC)">
        <i class="fas fa-times"></i>
    </button>

    <div class="focus-container">
        <div class="focus-header">
            <div class="focus-meta">
                📁 <?php echo e($task['project_name']); ?>
                <?php if ($task['client_name']): ?>
                    • 👤 <?php echo e($task['client_name']); ?>
                <?php endif; ?>
            </div>
            <h1 class="focus-title"><?php echo e($task['task_name']); ?></h1>
        </div>

        <?php if ($task['description']): ?>
        <div class="focus-description breathing">
            <?php echo nl2br(e($task['description'])); ?>
        </div>
        <?php endif; ?>

        <div class="focus-timer" id="focusTimer">00:00:00</div>

        <div class="focus-controls">
            <button class="focus-btn focus-btn-primary" id="startBtn" onclick="startFocusTimer()">
                <i class="fas fa-play"></i> Start Focus Session
            </button>
            <button class="focus-btn" id="pauseBtn" onclick="pauseFocusTimer()" style="display: none;">
                <i class="fas fa-pause"></i> Pause
            </button>
            <button class="focus-btn" onclick="markComplete()">
                <i class="fas fa-check"></i> Mark Complete
            </button>
        </div>

        <div class="focus-stats">
            <div class="focus-stat">
                <div class="focus-stat-value">
                    <?php echo $task['estimated_hours'] ? number_format($task['estimated_hours'], 1) . 'h' : 'N/A'; ?>
                </div>
                <div class="focus-stat-label">Estimated</div>
            </div>
            <div class="focus-stat">
                <div class="focus-stat-value" id="actualHours">
                    <?php echo $task['actual_hours'] ? number_format($task['actual_hours'], 1) . 'h' : '0h'; ?>
                </div>
                <div class="focus-stat-label">Actual</div>
            </div>
            <div class="focus-stat">
                <div class="focus-stat-value">
                    <span class="badge <?php echo getPriorityClass($task['priority']); ?>">
                        <?php echo ucfirst($task['priority']); ?>
                    </span>
                </div>
                <div class="focus-stat-label">Priority</div>
            </div>
        </div>
    </div>

    <script>
        let focusState = {
            taskId: <?php echo $taskId; ?>,
            timeLogId: null,
            startTime: null,
            elapsedSeconds: 0,
            isRunning: false,
            interval: null
        };

        // Start focus timer
        async function startFocusTimer() {
            try {
                // Start time tracking
                const response = await fetch('../api/time-tracking.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'start',
                        task_id: focusState.taskId,
                        project_id: <?php echo $task['project_id']; ?>
                    })
                });

                const data = await response.json();

                if (data.success) {
                    focusState.timeLogId = data.log_id;
                    focusState.startTime = Date.now();
                    focusState.isRunning = true;

                    document.getElementById('startBtn').style.display = 'none';
                    document.getElementById('pauseBtn').style.display = 'block';

                    focusState.interval = setInterval(updateTimer, 1000);
                } else {
                    alert(data.message || 'Failed to start timer');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Failed to start focus session');
            }
        }

        // Pause focus timer
        async function pauseFocusTimer() {
            if (!focusState.timeLogId) return;

            try {
                const response = await fetch('../api/time-tracking.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'stop',
                        log_id: focusState.timeLogId,
                        notes: 'Focus mode session'
                    })
                });

                const data = await response.json();

                if (data.success) {
                    focusState.isRunning = false;
                    clearInterval(focusState.interval);

                    document.getElementById('startBtn').style.display = 'block';
                    document.getElementById('pauseBtn').style.display = 'none';

                    // Refresh actual hours
                    const actualHours = (data.duration_minutes / 60).toFixed(1);
                    document.getElementById('actualHours').textContent = actualHours + 'h';
                }
            } catch (error) {
                console.error('Error:', error);
            }
        }

        // Update timer display
        function updateTimer() {
            focusState.elapsedSeconds++;

            const hours = Math.floor(focusState.elapsedSeconds / 3600);
            const minutes = Math.floor((focusState.elapsedSeconds % 3600) / 60);
            const seconds = focusState.elapsedSeconds % 60;

            document.getElementById('focusTimer').textContent =
                String(hours).padStart(2, '0') + ':' +
                String(minutes).padStart(2, '0') + ':' +
                String(seconds).padStart(2, '0');
        }

        // Mark task complete
        async function markComplete() {
            if (confirm('Mark this task as complete?')) {
                // Stop timer if running
                if (focusState.isRunning) {
                    await pauseFocusTimer();
                }

                // TODO: Update task status
                window.location.href = 'tasks.php';
            }
        }

        // Exit focus mode
        function exitFocusMode() {
            if (focusState.isRunning) {
                if (confirm('Timer is running. Stop timer and exit?')) {
                    pauseFocusTimer();
                    window.history.back();
                }
            } else {
                window.history.back();
            }
        }

        // ESC key to exit
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                exitFocusMode();
            }
            // Space to start/pause
            if (e.key === ' ') {
                e.preventDefault();
                if (focusState.isRunning) {
                    pauseFocusTimer();
                } else {
                    startFocusTimer();
                }
            }
        });

        // Auto-start if coming from a task
        const autoStart = new URLSearchParams(window.location.search).get('autostart');
        if (autoStart === '1') {
            setTimeout(() => {
                startFocusTimer();
            }, 1000);
        }
    </script>
</body>
</html>
