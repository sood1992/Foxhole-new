<?php
/**
 * V3 Two-Panel Sidebar for Employee
 * Vien Admin Panel Design
 */

// Get current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>

<div class="sidebar">
    <!-- Main Panel (Icons) -->
    <div class="sidebar-main-panel">
        <div class="sidebar-logo">
            <img src="../assets/images/neofox.png" alt="<?php echo SITE_NAME; ?>">
        </div>

        <div class="main-menu">
            <a href="../employee/index.php" class="main-menu-item <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>"
               data-menu="dashboard" title="Dashboard">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="../employee/tasks.php" class="main-menu-item <?php echo (in_array($current_page, ['tasks.php', 'create-task.php'])) ? 'active' : ''; ?>"
               data-menu="tasks" title="My Tasks">
                <i class="fas fa-tasks"></i>
                <span>My Tasks</span>
            </a>
            <a href="../employee/time-logs.php" class="main-menu-item <?php echo ($current_page == 'time-logs.php') ? 'active' : ''; ?>"
               data-menu="time" title="Time Logs">
                <i class="fas fa-clock"></i>
                <span>Time Logs</span>
            </a>
            <a href="../employee/my-stats.php" class="main-menu-item <?php echo ($current_page == 'my-stats.php') ? 'active' : ''; ?>"
               data-menu="stats" title="My Stats">
                <i class="fas fa-chart-bar"></i>
                <span>My Stats</span>
            </a>
            <a href="../employee/calendar.php" class="main-menu-item <?php echo ($current_page == 'calendar.php') ? 'active' : ''; ?>"
               data-menu="calendar" title="Calendar">
                <i class="fas fa-calendar"></i>
                <span>Calendar</span>
            </a>
            <a href="../employee/chat.php" class="main-menu-item <?php echo ($current_page == 'chat.php') ? 'active' : ''; ?>"
               data-menu="chat" title="Team Chat">
                <i class="fas fa-comments"></i>
                <span>Chat</span>
            </a>
        </div>
    </div>

    <!-- Sub Panel (Menu Items) -->
    <div class="sidebar-sub-panel">
        <!-- Dashboard Section -->
        <div class="menu-section" data-menu="dashboard">
            <div class="menu-title">Dashboard</div>
            <a href="../employee/index.php" class="menu-item <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i> Overview
            </a>
            <a href="../employee/index.php#tasks" class="menu-item">
                <i class="fas fa-list"></i> My Tasks
            </a>
            <a href="../employee/index.php#activity" class="menu-item">
                <i class="fas fa-clock"></i> Recent Activity
            </a>
        </div>

        <!-- Tasks Section -->
        <div class="menu-section" data-menu="tasks">
            <div class="menu-title">Tasks</div>
            <a href="../employee/tasks.php" class="menu-item <?php echo ($current_page == 'tasks.php') ? 'active' : ''; ?>">
                <i class="fas fa-list-check"></i> All My Tasks
            </a>
            <a href="../employee/create-task.php" class="menu-item <?php echo ($current_page == 'create-task.php') ? 'active' : ''; ?>">
                <i class="fas fa-plus"></i> Create Task
            </a>
            <a href="../employee/tasks.php?status=todo" class="menu-item">
                <i class="fas fa-circle"></i> To Do
            </a>
            <a href="../employee/tasks.php?status=in_progress" class="menu-item">
                <i class="fas fa-spinner"></i> In Progress
            </a>
            <a href="../employee/tasks.php?status=completed" class="menu-item">
                <i class="fas fa-check"></i> Completed
            </a>
            <a href="../employee/tasks.php?status=blocked" class="menu-item">
                <i class="fas fa-ban"></i> Blocked
            </a>
        </div>

        <!-- Time Logs Section -->
        <div class="menu-section" data-menu="time">
            <div class="menu-title">Time Tracking</div>
            <a href="../employee/time-logs.php" class="menu-item <?php echo ($current_page == 'time-logs.php') ? 'active' : ''; ?>">
                <i class="fas fa-clock"></i> My Time Logs
            </a>
            <a href="../employee/time-logs.php?period=today" class="menu-item">
                <i class="fas fa-calendar-day"></i> Today
            </a>
            <a href="../employee/time-logs.php?period=week" class="menu-item">
                <i class="fas fa-calendar-week"></i> This Week
            </a>
            <a href="../employee/time-logs.php?period=month" class="menu-item">
                <i class="fas fa-calendar"></i> This Month
            </a>
        </div>

        <!-- Stats Section -->
        <div class="menu-section" data-menu="stats">
            <div class="menu-title">Performance</div>
            <a href="../employee/my-stats.php" class="menu-item <?php echo ($current_page == 'my-stats.php') ? 'active' : ''; ?>">
                <i class="fas fa-chart-line"></i> My Statistics
            </a>
            <a href="../employee/my-stats.php#achievements" class="menu-item">
                <i class="fas fa-trophy"></i> Achievements
            </a>
            <a href="../employee/my-stats.php#badges" class="menu-item">
                <i class="fas fa-award"></i> Badges
            </a>
            <a href="../employee/my-stats.php#leaderboard" class="menu-item">
                <i class="fas fa-crown"></i> Leaderboard
            </a>
        </div>

        <!-- Calendar Section -->
        <div class="menu-section" data-menu="calendar">
            <div class="menu-title">Calendar</div>
            <a href="../employee/calendar.php" class="menu-item <?php echo ($current_page == 'calendar.php') ? 'active' : ''; ?>">
                <i class="fas fa-calendar-alt"></i> My Calendar
            </a>
            <a href="../employee/calendar.php#today" class="menu-item">
                <i class="fas fa-calendar-day"></i> Today
            </a>
            <a href="../employee/calendar.php#week" class="menu-item">
                <i class="fas fa-calendar-week"></i> This Week
            </a>
        </div>

        <!-- Chat Section -->
        <div class="menu-section" data-menu="chat">
            <div class="menu-title">Team Chat</div>
            <a href="../employee/chat.php" class="menu-item <?php echo ($current_page == 'chat.php') ? 'active' : ''; ?>">
                <i class="fas fa-comments"></i> Team Chat
            </a>
            <a href="../employee/chat.php#general" class="menu-item">
                <i class="fas fa-users"></i> General
            </a>
            <a href="../logout.php" class="menu-item" style="margin-top: 20px; color: var(--danger);">
                <i class="fas fa-sign-out-alt"></i> Logout
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
