<!-- Pomodoro Timer Widget -->
<div id="pomodoroWidget" style="position: fixed; bottom: 80px; right: 30px; z-index: 9999; display: none;">
    <div style="background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%); border-radius: 20px; padding: 25px; box-shadow: 0 10px 40px rgba(238, 90, 111, 0.4); min-width: 280px; color: white;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <h4 style="margin: 0; font-size: 16px;">🍅 Pomodoro</h4>
            <button onclick="closePomodoroWidget()" style="background: none; border: none; color: white; cursor: pointer; font-size: 20px;">×</button>
        </div>

        <div id="pomodoroDisplay" style="text-align: center; margin: 20px 0;">
            <div id="pomodoroTimer" style="font-size: 48px; font-weight: 700; letter-spacing: 2px;">25:00</div>
            <div id="pomodoroPhase" style="font-size: 14px; opacity: 0.9; margin-top: 5px;">Work Session</div>
        </div>

        <div style="display: flex; gap: 10px; margin-bottom: 15px;">
            <button id="pomodoroStartBtn" onclick="startPomodoro()" class="btn" style="flex: 1; background: white; color: #ff6b6b; border: none;">
                <i class="fas fa-play"></i> Start
            </button>
            <button id="pomodoroPauseBtn" onclick="pausePomodoro()" class="btn" style="flex: 1; background: rgba(255,255,255,0.2); color: white; border: none; display: none;">
                <i class="fas fa-pause"></i> Pause
            </button>
            <button id="pomodoroResetBtn" onclick="resetPomodoro()" class="btn" style="background: rgba(255,255,255,0.2); color: white; border: none;">
                <i class="fas fa-redo"></i>
            </button>
        </div>

        <div style="font-size: 12px; opacity: 0.8;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                <span>Sessions today:</span>
                <span id="pomodoroSessionsToday">0</span>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span>Total focus time:</span>
                <span id="pomodoroTotalTime">0h 0m</span>
            </div>
        </div>
    </div>
</div>

<!-- Pomodoro Toggle Button -->
<button id="pomodoroToggleBtn" onclick="togglePomodoroWidget()"
        style="position: fixed; bottom: 20px; right: 30px; z-index: 9999; width: 60px; height: 60px; border-radius: 50%; background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%); border: none; color: white; font-size: 24px; cursor: pointer; box-shadow: 0 5px 20px rgba(238, 90, 111, 0.4); transition: all 0.3s ease;">
    🍅
</button>

<style>
#pomodoroToggleBtn:hover {
    transform: scale(1.1);
    box-shadow: 0 8px 25px rgba(238, 90, 111, 0.6);
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

.pomodoro-active {
    animation: pulse 2s ease-in-out infinite;
}
</style>

<script>
let pomodoroState = {
    duration: 25 * 60, // 25 minutes in seconds
    timeLeft: 25 * 60,
    isRunning: false,
    isPaused: false,
    phase: 'work', // 'work' or 'break'
    sessionsToday: 0,
    totalTimeToday: 0,
    interval: null,
    currentTaskId: null
};

// Toggle widget visibility
function togglePomodoroWidget() {
    const widget = document.getElementById('pomodoroWidget');
    const btn = document.getElementById('pomodoroToggleBtn');

    if (widget.style.display === 'none') {
        widget.style.display = 'block';
        btn.style.display = 'none';
        loadPomodoroStats();
    } else {
        widget.style.display = 'none';
        btn.style.display = 'block';
    }
}

function closePomodoroWidget() {
    document.getElementById('pomodoroWidget').style.display = 'none';
    document.getElementById('pomodoroToggleBtn').style.display = 'block';
}

// Start Pomodoro
function startPomodoro() {
    if (pomodoroState.isRunning) return;

    pomodoroState.isRunning = true;
    pomodoroState.isPaused = false;

    document.getElementById('pomodoroStartBtn').style.display = 'none';
    document.getElementById('pomodoroPauseBtn').style.display = 'block';
    document.getElementById('pomodoroToggleBtn').classList.add('pomodoro-active');

    pomodoroState.interval = setInterval(updatePomodoro, 1000);

    // Save session start
    savePomodoroSession('start');
}

// Pause Pomodoro
function pausePomodoro() {
    pomodoroState.isRunning = false;
    pomodoroState.isPaused = true;

    document.getElementById('pomodoroStartBtn').style.display = 'block';
    document.getElementById('pomodoroPauseBtn').style.display = 'none';
    document.getElementById('pomodoroToggleBtn').classList.remove('pomodoro-active');

    clearInterval(pomodoroState.interval);
}

// Reset Pomodoro
function resetPomodoro() {
    pausePomodoro();
    pomodoroState.timeLeft = pomodoroState.duration;
    pomodoroState.phase = 'work';
    updateDisplay();
}

// Update timer
function updatePomodoro() {
    if (pomodoroState.timeLeft > 0) {
        pomodoroState.timeLeft--;
        updateDisplay();
    } else {
        // Session complete!
        completeSession();
    }
}

// Update display
function updateDisplay() {
    const minutes = Math.floor(pomodoroState.timeLeft / 60);
    const seconds = pomodoroState.timeLeft % 60;

    document.getElementById('pomodoroTimer').textContent =
        String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');

    document.getElementById('pomodoroPhase').textContent =
        pomodoroState.phase === 'work' ? 'Work Session' : 'Break Time';
}

// Complete session
function completeSession() {
    clearInterval(pomodoroState.interval);
    pomodoroState.isRunning = false;

    // Play sound notification
    playNotificationSound();

    // Save completed session
    savePomodoroSession('complete');

    if (pomodoroState.phase === 'work') {
        // Work session done, start break
        pomodoroState.sessionsToday++;
        pomodoroState.totalTimeToday += 25;

        // Decide break length (5 min or 15 min after 4 sessions)
        const breakDuration = (pomodoroState.sessionsToday % 4 === 0) ? 15 : 5;
        pomodoroState.duration = breakDuration * 60;
        pomodoroState.timeLeft = breakDuration * 60;
        pomodoroState.phase = 'break';

        alert(`🎉 Work session complete! Take a ${breakDuration} minute break.`);
    } else {
        // Break done, start work
        pomodoroState.duration = 25 * 60;
        pomodoroState.timeLeft = 25 * 60;
        pomodoroState.phase = 'work';

        alert('✨ Break complete! Ready for another work session?');
    }

    updateDisplay();
    updateStats();

    // Auto-start next session (optional - remove if you want manual start)
    // startPomodoro();
}

// Update stats display
function updateStats() {
    document.getElementById('pomodoroSessionsToday').textContent = pomodoroState.sessionsToday;

    const hours = Math.floor(pomodoroState.totalTimeToday / 60);
    const mins = pomodoroState.totalTimeToday % 60;
    document.getElementById('pomodoroTotalTime').textContent = `${hours}h ${mins}m`;
}

// Load today's stats
async function loadPomodoroStats() {
    try {
        const response = await fetch('../api/pomodoro.php?action=stats');
        const data = await response.json();

        if (data.success) {
            pomodoroState.sessionsToday = data.sessions_today || 0;
            pomodoroState.totalTimeToday = data.total_minutes_today || 0;
            updateStats();
        }
    } catch (error) {
        console.error('Error loading pomodoro stats:', error);
    }
}

// Save session to database
async function savePomodoroSession(status) {
    try {
        await fetch('../api/pomodoro.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: status,
                session_type: pomodoroState.phase,
                duration_minutes: Math.floor(pomodoroState.duration / 60),
                task_id: pomodoroState.currentTaskId
            })
        });
    } catch (error) {
        console.error('Error saving pomodoro session:', error);
    }
}

// Play notification sound
function playNotificationSound() {
    // Simple beep using Web Audio API
    const audioContext = new (window.AudioContext || window.webkitAudioContext)();
    const oscillator = audioContext.createOscillator();
    const gainNode = audioContext.createGain();

    oscillator.connect(gainNode);
    gainNode.connect(audioContext.destination);

    oscillator.frequency.value = 800;
    oscillator.type = 'sine';

    gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
    gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.5);

    oscillator.start(audioContext.currentTime);
    oscillator.stop(audioContext.currentTime + 0.5);
}

// Load stats when page loads
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', loadPomodoroStats);
} else {
    loadPomodoroStats();
}
</script>
