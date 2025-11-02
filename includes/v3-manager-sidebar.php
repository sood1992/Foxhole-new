<?php
/**
 * V3 Two-Panel Sidebar for Manager
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
            <a href="../manager/index.php" class="main-menu-item <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>"
               data-menu="dashboard" title="Dashboard">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="../manager/projects.php" class="main-menu-item <?php echo (in_array($current_page, ['projects.php', 'create-project.php'])) ? 'active' : ''; ?>"
               data-menu="projects" title="Projects">
                <i class="fas fa-folder"></i>
                <span>Projects</span>
            </a>
            <a href="../manager/tasks.php" class="main-menu-item <?php echo (in_array($current_page, ['tasks.php', 'create-task.php'])) ? 'active' : ''; ?>"
               data-menu="tasks" title="Tasks">
                <i class="fas fa-tasks"></i>
                <span>Tasks</span>
            </a>
            <a href="../manager/team.php" class="main-menu-item <?php echo (in_array($current_page, ['team.php', 'manage-users.php'])) ? 'active' : ''; ?>"
               data-menu="team" title="Team">
                <i class="fas fa-users"></i>
                <span>Team</span>
            </a>
            <a href="../manager/reports.php" class="main-menu-item <?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>"
               data-menu="reports" title="Reports">
                <i class="fas fa-chart-bar"></i>
                <span>Reports</span>
            </a>
            <a href="../manager/calendar.php" class="main-menu-item <?php echo ($current_page == 'calendar.php') ? 'active' : ''; ?>"
               data-menu="calendar" title="Calendar">
                <i class="fas fa-calendar"></i>
                <span>Calendar</span>
            </a>
            <a href="../manager/chat.php" class="main-menu-item <?php echo ($current_page == 'chat.php') ? 'active' : ''; ?>"
               data-menu="chat" title="Chat">
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
            <a href="../manager/index.php" class="menu-item <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i> Overview
            </a>
            <a href="../manager/index.php#stats" class="menu-item">
                <i class="fas fa-chart-pie"></i> Statistics
            </a>
            <a href="../manager/index.php#activity" class="menu-item">
                <i class="fas fa-clock"></i> Recent Activity
            </a>
        </div>

        <!-- Projects Section -->
        <div class="menu-section" data-menu="projects">
            <div class="menu-title">Projects</div>
            <a href="../manager/projects.php" class="menu-item <?php echo ($current_page == 'projects.php') ? 'active' : ''; ?>">
                <i class="fas fa-list"></i> My Projects
            </a>
            <a href="../manager/create-project.php" class="menu-item <?php echo ($current_page == 'create-project.php') ? 'active' : ''; ?>">
                <i class="fas fa-plus"></i> Create Project
            </a>
            <a href="../manager/projects.php?status=in_progress" class="menu-item">
                <i class="fas fa-spinner"></i> In Progress
            </a>
            <a href="../manager/projects.php?status=completed" class="menu-item">
                <i class="fas fa-check-circle"></i> Completed
            </a>
        </div>

        <!-- Tasks Section -->
        <div class="menu-section" data-menu="tasks">
            <div class="menu-title">Tasks</div>
            <a href="../manager/tasks.php" class="menu-item <?php echo ($current_page == 'tasks.php') ? 'active' : ''; ?>">
                <i class="fas fa-list-check"></i> All Tasks
            </a>
            <a href="../manager/create-task.php" class="menu-item <?php echo ($current_page == 'create-task.php') ? 'active' : ''; ?>">
                <i class="fas fa-plus"></i> Create Task
            </a>
            <a href="../manager/tasks.php?status=todo" class="menu-item">
                <i class="fas fa-circle"></i> To Do
            </a>
            <a href="../manager/tasks.php?status=in_progress" class="menu-item">
                <i class="fas fa-spinner"></i> In Progress
            </a>
            <a href="../manager/tasks.php?status=completed" class="menu-item">
                <i class="fas fa-check"></i> Completed
            </a>
        </div>

        <!-- Team Section -->
        <div class="menu-section" data-menu="team">
            <div class="menu-title">Team Management</div>
            <a href="../manager/team.php" class="menu-item <?php echo ($current_page == 'team.php') ? 'active' : ''; ?>">
                <i class="fas fa-users"></i> My Team
            </a>
            <a href="../manager/manage-users.php?action=add" class="menu-item <?php echo ($current_page == 'manage-users.php') ? 'active' : ''; ?>">
                <i class="fas fa-user-plus"></i> Add Team Member
            </a>
            <a href="../manager/team.php#performance" class="menu-item">
                <i class="fas fa-chart-line"></i> Performance
            </a>
        </div>

        <!-- Reports Section -->
        <div class="menu-section" data-menu="reports">
            <div class="menu-title">Reports & Analytics</div>
            <a href="../manager/reports.php" class="menu-item <?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>">
                <i class="fas fa-file-alt"></i> All Reports
            </a>
            <a href="../manager/reports.php?type=project" class="menu-item">
                <i class="fas fa-folder"></i> Project Reports
            </a>
            <a href="../manager/reports.php?type=team" class="menu-item">
                <i class="fas fa-users"></i> Team Reports
            </a>
        </div>

        <!-- Calendar Section -->
        <div class="menu-section" data-menu="calendar">
            <div class="menu-title">Calendar</div>
            <a href="../manager/calendar.php" class="menu-item <?php echo ($current_page == 'calendar.php') ? 'active' : ''; ?>">
                <i class="fas fa-calendar-alt"></i> View Calendar
            </a>
            <a href="../manager/calendar.php?view=day" class="menu-item">
                <i class="fas fa-calendar-day"></i> Day View
            </a>
            <a href="../manager/calendar.php?view=week" class="menu-item">
                <i class="fas fa-calendar-week"></i> Week View
            </a>
            <a href="../manager/calendar.php?view=month" class="menu-item">
                <i class="fas fa-calendar"></i> Month View
            </a>
        </div>

        <!-- Chat Section -->
        <div class="menu-section" data-menu="chat">
            <div class="menu-title">Chat & Messages</div>
            <a href="../manager/chat.php" class="menu-item <?php echo ($current_page == 'chat.php') ? 'active' : ''; ?>">
                <i class="fas fa-comments"></i> All Messages
            </a>
            <a href="../manager/chat.php?type=direct" class="menu-item">
                <i class="fas fa-comment"></i> Direct Messages
            </a>
            <a href="../manager/chat.php?type=group" class="menu-item">
                <i class="fas fa-users"></i> Group Chats
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
