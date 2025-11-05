<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !hasRole('employee')) {
    redirect('../login.php');
}

$db = getDBConnection();
$currentUser = getCurrentUser();

// Get user's pomodoro settings
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$currentUser['id']]);
$user = $stmt->fetch();

$pomodoroMinutes = $user['pomodoro_duration'] ?? 25;
$shortBreakMinutes = $user['short_break_duration'] ?? 5;
$longBreakMinutes = $user['long_break_duration'] ?? 15;
$pomodorosUntilLongBreak = $user['pomodoros_until_long_break'] ?? 4;

// Get today's pomodoro sessions
$stmt = $db->prepare("
    SELECT COUNT(*) as completed_sessions
    FROM pomodoro_sessions
    WHERE user_id = ?
    AND DATE(start_time) = CURDATE()
    AND session_type = 'work'
    AND completed = 1
");
$stmt->execute([$currentUser['id']]);
$todaySessions = $stmt->fetch()['completed_sessions'] ?? 0;

// Get active tasks
$stmt = $db->prepare("
    SELECT t.id, t.task_name, p.project_name
    FROM tasks t
    JOIN projects p ON t.project_id = p.id
    WHERE t.assigned_to = ?
    AND t.status != 'completed'
    ORDER BY t.priority DESC, t.due_date ASC
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
    <title>Pomodoro Timer - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/vien-v3.css">

    <!-- Synto Dashboard Template Design -->
    <link rel="stylesheet" href="../assets/css/synto-design.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .pomodoro-container {
            max-width: 600px;
            margin: 0 auto;
            text-align: center;
        }

        .timer-display {
            font-size: 120px;
            font-weight: 700;
            color: var(--text-primary);
            margin: 40px 0;
            font-family: 'Courier New', monospace;
            text-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .timer-display.work-mode {
            color: #ef4444;
        }

        .timer-display.break-mode {
            color: #10b981;
        }

        .timer-controls {
            display: flex;
            gap: 16px;
            justify-content: center;
            margin: 30px 0;
        }

        .timer-btn {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border: none;
            font-size: 32px;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .timer-btn:hover {
            transform: scale(1.05);
        }

        .timer-btn:active {
            transform: scale(0.95);
        }

        .timer-btn.start {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .timer-btn.pause {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
        }

        .timer-btn.reset {
            background: linear-gradient(135deg, #6b7280, #4b5563);
            color: white;
        }

        .mode-selector {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-bottom: 30px;
        }

        .mode-btn {
            padding: 12px 24px;
            border-radius: 8px;
            border: 2px solid var(--border-color);
            background: var(--bg-secondary);
            cursor: pointer;
            transition: all 0.2s;
            font-weight: 600;
        }

        .mode-btn.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-color: #667eea;
        }

        .pomodoro-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 16px;
            margin: 40px 0;
        }

        .stat-card {
            background: var(--bg-secondary);
            padding: 20px;
            border-radius: 12px;
            border: 2px solid var(--border-color);
        }

        .stat-value {
            font-size: 36px;
            font-weight: 700;
            color: var(--primary);
        }

        .stat-label {
            font-size: 13px;
            color: var(--text-secondary);
            margin-top: 8px;
        }

        .task-selector {
            background: var(--bg-secondary);
            padding: 20px;
            border-radius: 12px;
            margin: 30px 0;
            text-align: left;
        }

        .task-option {
            padding: 12px;
            margin: 8px 0;
            background: var(--bg-primary);
            border-radius: 8px;
            border: 2px solid var(--border-color);
            cursor: pointer;
            transition: all 0.2s;
        }

        .task-option:hover {
            border-color: var(--primary);
            background: var(--primary-light);
        }

        .task-option.selected {
            border-color: var(--primary);
            background: var(--primary-light);
        }

        .session-indicator {
            display: flex;
            gap: 8px;
            justify-content: center;
            margin: 20px 0;
        }

        .session-dot {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: var(--border-color);
            transition: all 0.3s;
        }

        .session-dot.completed {
            background: #10b981;
            transform: scale(1.2);
        }

        .progress-ring {
            position: relative;
            width: 400px;
            height: 400px;
            margin: 0 auto;
        }

        .progress-ring svg {
            transform: rotate(-90deg);
        }

        .progress-ring circle {
            fill: none;
            stroke-width: 8;
        }

        .progress-ring .background {
            stroke: var(--border-color);
        }

        .progress-ring .progress {
            stroke: #ef4444;
            stroke-linecap: round;
            transition: stroke-dashoffset 1s linear;
        }

        .progress-ring.break-mode .progress {
            stroke: #10b981;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include '../includes/v3-employee-sidebar.php'; ?>

        <div class="main-content">
            <?php include '../includes/v3-header.php'; ?>

            <div class="content-wrapper">
                <div class="pomodoro-container">
                    <h1 style="margin-bottom: 30px;">
                        <i class="fas fa-tomato"></i> Pomodoro Timer
                    </h1>

                    <!-- Mode Selector -->
                    <div class="mode-selector">
                        <button class="mode-btn active" data-mode="work">
                            🍅 Work
                        </button>
                        <button class="mode-btn" data-mode="short-break">
                            ☕ Short Break
                        </button>
                        <button class="mode-btn" data-mode="long-break">
                            🌴 Long Break
                        </button>
                    </div>

                    <!-- Progress Ring -->
                    <div class="progress-ring" id="progressRing">
                        <svg width="400" height="400">
                            <circle class="background" cx="200" cy="200" r="190"></circle>
                            <circle class="progress" cx="200" cy="200" r="190"
                                    stroke-dasharray="1194"
                                    stroke-dashoffset="0"
                                    id="progressCircle"></circle>
                        </svg>
                        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);">
                            <div class="timer-display work-mode" id="timerDisplay">
                                <?php printf('%02d:00', $pomodoroMinutes); ?>
                            </div>
                            <div style="font-size: 18px; color: var(--text-secondary);" id="timerLabel">
                                Focus Time
                            </div>
                        </div>
                    </div>

                    <!-- Timer Controls -->
                    <div class="timer-controls">
                        <button class="timer-btn start" id="startBtn" onclick="toggleTimer()">
                            <i class="fas fa-play"></i>
                        </button>
                        <button class="timer-btn reset" onclick="resetTimer()">
                            <i class="fas fa-redo"></i>
                        </button>
                    </div>

                    <!-- Session Indicator -->
                    <div class="session-indicator">
                        <?php for ($i = 0; $i < $pomodorosUntilLongBreak; $i++): ?>
                            <div class="session-dot <?php echo ($i < $todaySessions % $pomodorosUntilLongBreak) ? 'completed' : ''; ?>"></div>
                        <?php endfor; ?>
                    </div>

                    <!-- Task Selector -->
                    <div class="task-selector">
                        <h3 style="margin: 0 0 16px;">Working on:</h3>
                        <select id="taskSelect" class="form-control" style="width: 100%; padding: 12px;">
                            <option value="">No specific task</option>
                            <?php foreach ($tasks as $task): ?>
                                <option value="<?php echo $task['id']; ?>">
                                    <?php echo e($task['task_name']); ?> - <?php echo e($task['project_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Stats -->
                    <div class="pomodoro-stats">
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $todaySessions; ?></div>
                            <div class="stat-label">Today's Pomodoros</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value" id="totalTimeToday">0h</div>
                            <div class="stat-label">Focus Time Today</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value" id="currentStreak">0</div>
                            <div class="stat-label">Session Streak</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Pomodoro Timer Logic
        let timerInterval = null;
        let totalSeconds = <?php echo $pomodoroMinutes * 60; ?>;
        let currentSeconds = totalSeconds;
        let isRunning = false;
        let currentMode = 'work';
        let completedPomodoros = <?php echo $todaySessions % $pomodorosUntilLongBreak; ?>;

        const durations = {
            work: <?php echo $pomodoroMinutes * 60; ?>,
            'short-break': <?php echo $shortBreakMinutes * 60; ?>,
            'long-break': <?php echo $longBreakMinutes * 60; ?>
        };

        const labels = {
            work: 'Focus Time',
            'short-break': 'Short Break',
            'long-break': 'Long Break'
        };

        // Mode switching
        document.querySelectorAll('.mode-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                if (isRunning) {
                    if (!confirm('Timer is running. Switch mode anyway?')) return;
                    stopTimer();
                }

                document.querySelectorAll('.mode-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');

                currentMode = this.dataset.mode;
                totalSeconds = durations[currentMode];
                currentSeconds = totalSeconds;

                updateDisplay();
                updateProgressRing();

                document.getElementById('timerLabel').textContent = labels[currentMode];
                document.getElementById('progressRing').className = 'progress-ring' + (currentMode !== 'work' ? ' break-mode' : '');
                document.getElementById('timerDisplay').className = 'timer-display' + (currentMode !== 'work' ? ' break-mode' : ' work-mode');
            });
        });

        function toggleTimer() {
            if (isRunning) {
                stopTimer();
            } else {
                startTimer();
            }
        }

        function startTimer() {
            isRunning = true;
            document.getElementById('startBtn').innerHTML = '<i class="fas fa-pause"></i>';
            document.getElementById('startBtn').classList.remove('start');
            document.getElementById('startBtn').classList.add('pause');

            timerInterval = setInterval(() => {
                currentSeconds--;
                updateDisplay();
                updateProgressRing();

                if (currentSeconds <= 0) {
                    timerComplete();
                }
            }, 1000);

            // Log session start
            logPomodoroSession('start');
        }

        function stopTimer() {
            isRunning = false;
            clearInterval(timerInterval);
            document.getElementById('startBtn').innerHTML = '<i class="fas fa-play"></i>';
            document.getElementById('startBtn').classList.remove('pause');
            document.getElementById('startBtn').classList.add('start');
        }

        function resetTimer() {
            stopTimer();
            currentSeconds = totalSeconds;
            updateDisplay();
            updateProgressRing();
        }

        function timerComplete() {
            stopTimer();

            // Play sound
            playNotificationSound();

            // Show notification
            if (currentMode === 'work') {
                showNotification('Pomodoro Complete!', 'Great job! Time for a break.');
                completedPomodoros++;
                updateSessionDots();

                // Auto-switch to break
                setTimeout(() => {
                    const breakType = (completedPomodoros % <?php echo $pomodorosUntilLongBreak; ?> === 0) ? 'long-break' : 'short-break';
                    document.querySelector(`[data-mode="${breakType}"]`).click();
                }, 2000);
            } else {
                showNotification('Break Over!', 'Ready to get back to work?');
                setTimeout(() => {
                    document.querySelector('[data-mode="work"]').click();
                }, 2000);
            }

            // Log session complete
            logPomodoroSession('complete');
        }

        function updateDisplay() {
            const minutes = Math.floor(currentSeconds / 60);
            const seconds = currentSeconds % 60;
            document.getElementById('timerDisplay').textContent =
                `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
        }

        function updateProgressRing() {
            const percentage = currentSeconds / totalSeconds;
            const circumference = 2 * Math.PI * 190;
            const offset = circumference * (1 - percentage);
            document.getElementById('progressCircle').style.strokeDashoffset = offset;
        }

        function updateSessionDots() {
            document.querySelectorAll('.session-dot').forEach((dot, index) => {
                if (index < completedPomodoros) {
                    dot.classList.add('completed');
                } else {
                    dot.classList.remove('completed');
                }
            });
        }

        function showNotification(title, body) {
            if ('Notification' in window && Notification.permission === 'granted') {
                new Notification(title, { body, icon: '../assets/images/neofox.png' });
            } else {
                alert(`${title}\n${body}`);
            }
        }

        function playNotificationSound() {
            const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBjGM1fPTgjMGHm7A7+OZRQ0PWKzn77BdGAg+mejyxHAqBSt9y/DadTEGKHe/7+OYRQ0PVKzl77BcFgo+mejyxHAqBSp9y/Dadj0GJ3W/7+OYSQ0SVavl7q9cGAg+lunyxHErBSp8y/DadDgGK3a+7+SXSQ0TV6vl7q5dGAk+lunywnErBSp8y/DZczcGLHa+7+SXSQ0TV6vl7qxdGAk+lunywm8rBSx8yvDZcjgGLHW+7+SWTQ0UVqrl7qxdFwk+lunywW4rBSx8yvDZcTgGLXW+7+SWTQ0UVKrl7qpcFwk+lOjxwW4rBSx7yvDYcDgGLnS+7+SWTQ0UVKrl7apcFwk+lOjxwG0qBSx7yfDYbzgGLnS+7+SWTQ0UVKrl7apcFwk+lOjxwG0qBSx7yfDYbzgGLnS+7+SWTQ0UU6rl7alcFwk+lOjxv2wqBSx7yfDYbjgGLnS+7+SWTQ0UU6rl7alcFwk+k+jxv2wqBSx6yfDYbjgGLnO+7+SWTQ0UU6rl7aZcFwg+k+jxvmsqBSx6yfDYbTgGLnO+7+SWTQ0UU6rl7aZcFwg+k+jxvmoqBSx6yfDYbTgGLnO+7+SWTQ0UUqrl7aZcFgg+k+jxvWkqBSx6yfDYazgGLnO+7uSWTQ0UUqrl7aVcFgg+k+jxvWkqBSx5yfDYazgGLnO+7uSWTQ0TUqrl7aVcFQg+k+jxu2gpBSx5yfDYajgGLnO+7uSWTQ0TUqrl7aRcFQg+kujxu2gpBSx5yfDYajgGLnO+7uOWTQ0TUqrl7aRcFQg+kujxumgpBSx5yPDYaTgGLnO+7uOWTQ0TUKnl7aRcFQg+kujxumcpBSx5yPDYaTgGLnO+7uOWTQ0TUKnl7aNcFQg+kujxumcpBSx5yPDYaTgGLnO+7uOWTQ0TT6nl7aNcFQg+kujxuWYpBSx5yPDYaDgGLnS+7uOWTQ0TT6nl7aNcFQg+kujxuWYpBSx5yPDYaDgGLnS+7uKWTQ0TT6nl7aNcFAg+kujxuGUpBSx5yPDYaDgGLnS+7uKWTQ0TT6jl7aNcFAg+kujxuGUpBSx5yPDYZzgGLnS+7uKWTQ0TT6jl7aJcFAg+kujxuGQpBSx5yPDYZzgGLnS+7uKWTQ0TT6jl7aJcFAg+kujxt2QpBSx5yPDYZjgGLnS+7uKWTQ0TT6jl7aJcFAg+kujxt2MpBSx5yPDYZjgGLnS+7uKWTQ0TT6jl7aJcEwg+kujxt2MpBSx5yPDYZjgGLnS+7uKWTQ0TT6jl7aFcEwg+kujxt2MpBSx5yPDYZTgGLnS+7uKWTQ0TT6jl7aFcEwg+kujxt2IpBSx5yPDYZTgGLnS+7uKWTQ0TT6jl7aFcEwg+kujxtmEpBSx5yPDYZTgGLnS+7uKWTQ0TT6jl7Z9cEwg+kujxtmEpBSx5yPDYZDgGLnS+7uKWTQ0TT6jl7Z9cEwg+kujxtmApBSx5yPDYZDgGLnS+7uKWTQ0TT6jl7Z9cEgg+kujxtl8pBSx5yPDYYzgGLnS+7uKWTQ0TT6jl7Z5cEgg+kujxtl8pBSx5yPDYYzgGLnS+7uKWTQ0TT6jl7Z5cEgg+kujxtl4pBSx5yPDYYzgGLnS+7uKWTQ0TT6jl7Z5cEgg+kujxtl4pBSx5x/DYYjgGLnW+7uKWTQ0TT6jl7Z1cEgg+kujxtlwpBSx5x/DYYjgGLnW+7uKWTQ0TT6jl7Z1cEgg+kujxtlwpBSx5x/DYYjgGLnW+7uKWTQ0TT6jl7Z');
            audio.play().catch(e => console.log('Audio play failed:', e));
        }

        function logPomodoroSession(action) {
            const taskId = document.getElementById('taskSelect').value;

            fetch('../api/pomodoro.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: action,
                    task_id: taskId || null,
                    session_type: currentMode,
                    duration_minutes: Math.floor(totalSeconds / 60)
                })
            })
            .then(response => response.json())
            .then(data => {
                console.log('Session logged:', data);
            })
            .catch(error => console.error('Error logging session:', error));
        }

        // Request notification permission
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }

        // Load today's stats
        fetch('../api/pomodoro.php?action=get_today_stats')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('totalTimeToday').textContent = data.total_hours + 'h';
                    document.getElementById('currentStreak').textContent = data.current_streak;
                }
            });
    </script>
    <script src="../assets/js/theme.js"></script>

    <!-- Synto Dashboard Interactions -->
    <script src="../assets/js/synto-interactions.js"></script>
</body>
</html>
