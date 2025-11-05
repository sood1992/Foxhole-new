<?php
/**
 * V3 Two-Panel Sidebar for Employee - SYNTO EDITION
 * Synto Dashboard Template Design
 */

// Get current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>

<div class="sidebar custom-scrollbar">
    <!-- Main Panel (Icons) -->
    <div class="sidebar-main-panel">
        <div class="sidebar-logo" style="padding: 20px; text-align: center;">
            <img src="../assets/images/neofox.png" alt="<?php echo SITE_NAME; ?>" style="height: 32px;">
        </div>

        <div class="main-menu">
            <a href="../employee/index.php" class="main-menu-item <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>"
               data-menu="dashboard" title="Dashboard">
                <i class="ri-dashboard-line" style="font-size: 20px;"></i>
            </a>
            <a href="../employee/tasks.php" class="main-menu-item <?php echo (in_array($current_page, ['tasks.php', 'create-task.php', 'task-detail.php'])) ? 'active' : ''; ?>"
               data-menu="tasks" title="My Tasks">
                <i class="ri-task-line" style="font-size: 20px;"></i>
            </a>
            <a href="../employee/time-logs.php" class="main-menu-item <?php echo ($current_page == 'time-logs.php') ? 'active' : ''; ?>"
               data-menu="time" title="Time Logs">
                <i class="ri-time-line" style="font-size: 20px;"></i>
            </a>
            <a href="../employee/my-stats.php" class="main-menu-item <?php echo ($current_page == 'my-stats.php') ? 'active' : ''; ?>"
               data-menu="stats" title="My Stats">
                <i class="ri-bar-chart-box-line" style="font-size: 20px;"></i>
            </a>
            <a href="../employee/eisenhower-matrix.php" class="main-menu-item <?php echo (in_array($current_page, ['eisenhower-matrix.php', 'pomodoro.php', 'daily-planning.php', 'goals.php', 'morning-ritual.php', 'time-boxing.php', 'weekly-review.php', 'focus-mode.php'])) ? 'active' : ''; ?>"
               data-menu="productivity" title="Productivity">
                <i class="ri-focus-2-line" style="font-size: 20px;"></i>
            </a>
            <a href="../employee/calendar.php" class="main-menu-item <?php echo ($current_page == 'calendar.php') ? 'active' : ''; ?>"
               data-menu="calendar" title="Calendar">
                <i class="ri-calendar-line" style="font-size: 20px;"></i>
            </a>
            <a href="../employee/chat.php" class="main-menu-item <?php echo ($current_page == 'chat.php') ? 'active' : ''; ?>"
               data-menu="chat" title="Team Chat">
                <i class="ri-chat-3-line" style="font-size: 20px;"></i>
            </a>
        </div>
    </div>

    <!-- Sub Panel (Menu Items) -->
    <div class="sidebar-sub-panel">
        <!-- Dashboard Section -->
        <div class="menu-section" data-menu="dashboard">
            <div class="menu-title">DASHBOARD</div>
            <a href="../employee/index.php" class="menu-item <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">
                <i class="ri-speed-line"></i> Overview
            </a>
            <a href="../employee/index.php#tasks" class="menu-item">
                <i class="ri-list-check"></i> My Tasks
            </a>
            <a href="../employee/index.php#activity" class="menu-item">
                <i class="ri-history-line"></i> Recent Activity
            </a>
        </div>

        <!-- Tasks Section -->
        <div class="menu-section" data-menu="tasks">
            <div class="menu-title">MY TASKS</div>
            <a href="../employee/tasks.php" class="menu-item <?php echo ($current_page == 'tasks.php') ? 'active' : ''; ?>">
                <i class="ri-list-check-2"></i> All My Tasks
            </a>
            <a href="../employee/create-task.php" class="menu-item <?php echo ($current_page == 'create-task.php') ? 'active' : ''; ?>">
                <i class="ri-add-circle-line"></i> Create Task
            </a>
            <a href="../employee/tasks.php?status=todo" class="menu-item">
                <i class="ri-checkbox-blank-circle-line"></i> To Do
            </a>
            <a href="../employee/tasks.php?status=in_progress" class="menu-item">
                <i class="ri-loader-2-line"></i> In Progress
            </a>
            <a href="../employee/tasks.php?status=completed" class="menu-item">
                <i class="ri-checkbox-circle-line"></i> Completed
            </a>
            <a href="../employee/tasks.php?status=blocked" class="menu-item">
                <i class="ri-error-warning-line"></i> Blocked
            </a>
        </div>

        <!-- Time Logs Section -->
        <div class="menu-section" data-menu="time">
            <div class="menu-title">TIME TRACKING</div>
            <a href="../employee/time-logs.php" class="menu-item <?php echo ($current_page == 'time-logs.php') ? 'active' : ''; ?>">
                <i class="ri-time-line"></i> My Time Logs
            </a>
            <a href="../employee/time-logs.php?period=today" class="menu-item">
                <i class="ri-calendar-check-line"></i> Today
            </a>
            <a href="../employee/time-logs.php?period=week" class="menu-item">
                <i class="ri-calendar-event-line"></i> This Week
            </a>
            <a href="../employee/time-logs.php?period=month" class="menu-item">
                <i class="ri-calendar-2-line"></i> This Month
            </a>
        </div>

        <!-- Stats Section -->
        <div class="menu-section" data-menu="stats">
            <div class="menu-title">PERFORMANCE</div>
            <a href="../employee/my-stats.php" class="menu-item <?php echo ($current_page == 'my-stats.php') ? 'active' : ''; ?>">
                <i class="ri-line-chart-line"></i> My Statistics
            </a>
            <a href="../employee/my-stats.php#achievements" class="menu-item">
                <i class="ri-trophy-line"></i> Achievements
            </a>
            <a href="../employee/my-stats.php#badges" class="menu-item">
                <i class="ri-medal-line"></i> Badges
            </a>
            <a href="../employee/my-stats.php#leaderboard" class="menu-item">
                <i class="ri-vip-crown-line"></i> Leaderboard
            </a>
        </div>

        <!-- Productivity Section -->
        <div class="menu-section" data-menu="productivity">
            <div class="menu-title">PRODUCTIVITY TOOLS</div>
            <a href="../employee/daily-planning.php" class="menu-item <?php echo ($current_page == 'daily-planning.php') ? 'active' : ''; ?>">
                <i class="ri-sun-line"></i> Daily Planning
            </a>
            <a href="../employee/morning-ritual.php" class="menu-item <?php echo ($current_page == 'morning-ritual.php') ? 'active' : ''; ?>">
                <i class="ri-contrast-2-line"></i> Morning Ritual
            </a>
            <a href="../employee/eisenhower-matrix.php" class="menu-item <?php echo ($current_page == 'eisenhower-matrix.php') ? 'active' : ''; ?>">
                <i class="ri-grid-line"></i> Eisenhower Matrix
            </a>
            <a href="../employee/pomodoro.php" class="menu-item <?php echo ($current_page == 'pomodoro.php') ? 'active' : ''; ?>">
                <i class="ri-timer-line"></i> Pomodoro Timer
            </a>
            <a href="../employee/time-boxing.php" class="menu-item <?php echo ($current_page == 'time-boxing.php') ? 'active' : ''; ?>">
                <i class="ri-calendar-check-fill"></i> Time Boxing
            </a>
            <a href="../employee/focus-mode.php" class="menu-item <?php echo ($current_page == 'focus-mode.php') ? 'active' : ''; ?>">
                <i class="ri-focus-2-line"></i> Focus Mode
            </a>
            <a href="../employee/goals.php" class="menu-item <?php echo ($current_page == 'goals.php') ? 'active' : ''; ?>">
                <i class="ri-bullseye-line"></i> Goals
            </a>
            <a href="../employee/weekly-review.php" class="menu-item <?php echo ($current_page == 'weekly-review.php') ? 'active' : ''; ?>">
                <i class="ri-calendar-todo-line"></i> Weekly Review
            </a>
        </div>

        <!-- Calendar Section -->
        <div class="menu-section" data-menu="calendar">
            <div class="menu-title">CALENDAR</div>
            <a href="../employee/calendar.php" class="menu-item <?php echo ($current_page == 'calendar.php') ? 'active' : ''; ?>">
                <i class="ri-calendar-2-line"></i> My Calendar
            </a>
            <a href="../employee/calendar.php#today" class="menu-item">
                <i class="ri-calendar-check-line"></i> Today
            </a>
            <a href="../employee/calendar.php#week" class="menu-item">
                <i class="ri-calendar-event-line"></i> This Week
            </a>
        </div>

        <!-- Chat Section -->
        <div class="menu-section" data-menu="chat">
            <div class="menu-title">TEAM CHAT</div>
            <a href="../employee/chat.php" class="menu-item <?php echo ($current_page == 'chat.php') ? 'active' : ''; ?>">
                <i class="ri-message-3-line"></i> Team Chat
            </a>
            <a href="../employee/chat.php#general" class="menu-item">
                <i class="ri-group-line"></i> General
            </a>
            <a href="../logout.php" class="menu-item" style="margin-top: 20px; color: var(--synto-danger); border-top: 1px solid rgba(255,255,255,0.1); padding-top: 12px;">
                <i class="ri-logout-box-line"></i> Logout
            </a>
        </div>
    </div>
</div>

<script>
// Sidebar menu highlighting and interactions
document.addEventListener('DOMContentLoaded', function() {
    // Get active menu from main panel
    const activeMainItem = document.querySelector('.main-menu-item.active');
    if (activeMainItem) {
        const activeMenu = activeMainItem.dataset.menu;

        // Hide all menu sections
        document.querySelectorAll('.menu-section').forEach(section => {
            section.style.display = 'none';
        });

        // Show active menu section
        const activeSection = document.querySelector(`.menu-section[data-menu="${activeMenu}"]`);
        if (activeSection) {
            activeSection.style.display = 'block';
        }
    }

    // Main menu item clicks
    document.querySelectorAll('.main-menu-item').forEach(item => {
        item.addEventListener('click', function(e) {
            const menuName = this.dataset.menu;

            // Update active state
            document.querySelectorAll('.main-menu-item').forEach(i => i.classList.remove('active'));
            this.classList.add('active');

            // Show corresponding menu section
            document.querySelectorAll('.menu-section').forEach(section => {
                section.style.display = 'none';
            });

            const targetSection = document.querySelector(`.menu-section[data-menu="${menuName}"]`);
            if (targetSection) {
                targetSection.style.display = 'block';
            }
        });
    });
});
</script>
