<?php
/**
 * V3 Two-Panel Sidebar for Manager - SYNTO EDITION
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
            <a href="../manager/index.php" class="main-menu-item <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>"
               data-menu="dashboard" title="Dashboard">
                <i class="ri-dashboard-line" style="font-size: 20px;"></i>
            </a>
            <a href="../manager/projects.php" class="main-menu-item <?php echo (in_array($current_page, ['projects.php', 'create-project.php'])) ? 'active' : ''; ?>"
               data-menu="projects" title="Projects">
                <i class="ri-folder-line" style="font-size: 20px;"></i>
            </a>
            <a href="../manager/tasks.php" class="main-menu-item <?php echo (in_array($current_page, ['tasks.php', 'create-task.php'])) ? 'active' : ''; ?>"
               data-menu="tasks" title="Tasks">
                <i class="ri-task-line" style="font-size: 20px;"></i>
            </a>
            <a href="../manager/team.php" class="main-menu-item <?php echo (in_array($current_page, ['team.php', 'manage-users.php'])) ? 'active' : ''; ?>"
               data-menu="team" title="Team">
                <i class="ri-team-line" style="font-size: 20px;"></i>
            </a>
            <a href="../manager/reports.php" class="main-menu-item <?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>"
               data-menu="reports" title="Reports">
                <i class="ri-file-chart-line" style="font-size: 20px;"></i>
            </a>
            <a href="../manager/calendar.php" class="main-menu-item <?php echo ($current_page == 'calendar.php') ? 'active' : ''; ?>"
               data-menu="calendar" title="Calendar">
                <i class="ri-calendar-line" style="font-size: 20px;"></i>
            </a>
            <a href="../manager/chat.php" class="main-menu-item <?php echo ($current_page == 'chat.php') ? 'active' : ''; ?>"
               data-menu="chat" title="Chat">
                <i class="ri-chat-3-line" style="font-size: 20px;"></i>
            </a>
        </div>
    </div>

    <!-- Sub Panel (Menu Items) -->
    <div class="sidebar-sub-panel">
        <!-- Dashboard Section -->
        <div class="menu-section" data-menu="dashboard">
            <div class="menu-title">DASHBOARD</div>
            <a href="../manager/index.php" class="menu-item <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">
                <i class="ri-speed-line"></i> Overview
            </a>
            <a href="../manager/index.php#stats" class="menu-item">
                <i class="ri-pie-chart-line"></i> Statistics
            </a>
            <a href="../manager/index.php#activity" class="menu-item">
                <i class="ri-time-line"></i> Recent Activity
            </a>
        </div>

        <!-- Projects Section -->
        <div class="menu-section" data-menu="projects">
            <div class="menu-title">PROJECTS</div>
            <a href="../manager/projects.php" class="menu-item <?php echo ($current_page == 'projects.php') ? 'active' : ''; ?>">
                <i class="ri-folder-2-line"></i> My Projects
            </a>
            <a href="../manager/create-project.php" class="menu-item <?php echo ($current_page == 'create-project.php') ? 'active' : ''; ?>">
                <i class="ri-add-circle-line"></i> Create Project
            </a>
            <a href="../manager/projects.php?status=in_progress" class="menu-item">
                <i class="ri-loader-2-line"></i> In Progress
            </a>
            <a href="../manager/projects.php?status=completed" class="menu-item">
                <i class="ri-checkbox-circle-line"></i> Completed
            </a>
        </div>

        <!-- Tasks Section -->
        <div class="menu-section" data-menu="tasks">
            <div class="menu-title">TASKS</div>
            <a href="../manager/tasks.php" class="menu-item <?php echo ($current_page == 'tasks.php') ? 'active' : ''; ?>">
                <i class="ri-task-line"></i> All Tasks
            </a>
            <a href="../manager/create-task.php" class="menu-item <?php echo ($current_page == 'create-task.php') ? 'active' : ''; ?>">
                <i class="ri-add-line"></i> Create Task
            </a>
            <a href="../manager/tasks.php?filter=pending" class="menu-item">
                <i class="ri-time-line"></i> Pending
            </a>
            <a href="../manager/tasks.php?filter=completed" class="menu-item">
                <i class="ri-check-double-line"></i> Completed
            </a>
        </div>

        <!-- Team Section -->
        <div class="menu-section" data-menu="team">
            <div class="menu-title">TEAM</div>
            <a href="../manager/team.php" class="menu-item <?php echo ($current_page == 'team.php') ? 'active' : ''; ?>">
                <i class="ri-group-line"></i> My Team
            </a>
            <a href="../manager/manage-users.php" class="menu-item <?php echo ($current_page == 'manage-users.php') ? 'active' : ''; ?>">
                <i class="ri-user-add-line"></i> Manage Users
            </a>
            <a href="../manager/team.php#performance" class="menu-item">
                <i class="ri-line-chart-line"></i> Performance
            </a>
        </div>

        <!-- Reports Section -->
        <div class="menu-section" data-menu="reports">
            <div class="menu-title">REPORTS</div>
            <a href="../manager/reports.php" class="menu-item <?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>">
                <i class="ri-file-text-line"></i> All Reports
            </a>
            <a href="../manager/reports.php?type=team" class="menu-item">
                <i class="ri-team-line"></i> Team Reports
            </a>
            <a href="../manager/reports.php?type=project" class="menu-item">
                <i class="ri-folder-chart-line"></i> Project Reports
            </a>
        </div>

        <!-- Calendar Section -->
        <div class="menu-section" data-menu="calendar">
            <div class="menu-title">CALENDAR</div>
            <a href="../manager/calendar.php" class="menu-item <?php echo ($current_page == 'calendar.php') ? 'active' : ''; ?>">
                <i class="ri-calendar-2-line"></i> Full Calendar
            </a>
            <a href="../manager/calendar.php#month" class="menu-item">
                <i class="ri-calendar-line"></i> Month View
            </a>
            <a href="../manager/calendar.php#week" class="menu-item">
                <i class="ri-calendar-event-line"></i> Week View
            </a>
        </div>

        <!-- Chat Section -->
        <div class="menu-section" data-menu="chat">
            <div class="menu-title">CHAT</div>
            <a href="../manager/chat.php" class="menu-item <?php echo ($current_page == 'chat.php') ? 'active' : ''; ?>">
                <i class="ri-message-3-line"></i> Messages
            </a>
            <a href="../manager/chat.php#team" class="menu-item">
                <i class="ri-group-line"></i> Team Chat
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
