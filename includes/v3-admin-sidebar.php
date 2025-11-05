<?php
/**
 * V3 Two-Panel Sidebar for Admin - SYNTO EDITION
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
            <a href="../admin/index.php" class="main-menu-item <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>"
               data-menu="dashboard" title="Dashboard">
                <i class="ri-dashboard-line" style="font-size: 20px;"></i>
            </a>
            <a href="../admin/projects.php" class="main-menu-item <?php echo (in_array($current_page, ['projects.php', 'create-project.php'])) ? 'active' : ''; ?>"
               data-menu="projects" title="Projects">
                <i class="ri-folder-line" style="font-size: 20px;"></i>
            </a>
            <a href="../admin/team.php" class="main-menu-item <?php echo (in_array($current_page, ['team.php', 'users.php'])) ? 'active' : ''; ?>"
               data-menu="team" title="Team">
                <i class="ri-team-line" style="font-size: 20px;"></i>
            </a>
            <a href="../admin/bulk-operations.php" class="main-menu-item <?php echo (in_array($current_page, ['bulk-operations.php', 'bulk-import.php'])) ? 'active' : ''; ?>"
               data-menu="bulk" title="Bulk Operations">
                <i class="ri-stack-line" style="font-size: 20px;"></i>
            </a>
            <a href="../admin/gamification.php" class="main-menu-item <?php echo ($current_page == 'gamification.php') ? 'active' : ''; ?>"
               data-menu="gamification" title="Gamification">
                <i class="ri-trophy-line" style="font-size: 20px;"></i>
            </a>
            <a href="../admin/analytics.php" class="main-menu-item <?php echo (in_array($current_page, ['analytics.php', 'advanced-analytics.php', 'profit-loss.php'])) ? 'active' : ''; ?>"
               data-menu="analytics" title="Analytics">
                <i class="ri-line-chart-line" style="font-size: 20px;"></i>
            </a>
            <a href="../admin/calendar.php" class="main-menu-item <?php echo ($current_page == 'calendar.php') ? 'active' : ''; ?>"
               data-menu="calendar" title="Calendar">
                <i class="ri-calendar-line" style="font-size: 20px;"></i>
            </a>
            <a href="../admin/email-config.php" class="main-menu-item <?php echo (in_array($current_page, ['email-config.php', 'email-test.php'])) ? 'active' : ''; ?>"
               data-menu="settings" title="Settings">
                <i class="ri-settings-3-line" style="font-size: 20px;"></i>
            </a>
        </div>
    </div>

    <!-- Sub Panel (Menu Items) -->
    <div class="sidebar-sub-panel">
        <!-- Dashboard Section -->
        <div class="menu-section" data-menu="dashboard">
            <div class="menu-title">DASHBOARD</div>
            <a href="../admin/index.php" class="menu-item <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">
                <i class="ri-speed-line"></i> Overview
            </a>
            <a href="../admin/index.php#stats" class="menu-item">
                <i class="ri-bar-chart-box-line"></i> Statistics
            </a>
            <a href="../admin/index.php#activity" class="menu-item">
                <i class="ri-time-line"></i> Recent Activity
            </a>
        </div>

        <!-- Projects Section -->
        <div class="menu-section" data-menu="projects">
            <div class="menu-title">PROJECTS</div>
            <a href="../admin/projects.php" class="menu-item <?php echo ($current_page == 'projects.php') ? 'active' : ''; ?>">
                <i class="ri-list-check"></i> All Projects
            </a>
            <a href="../admin/projects.php#create" class="menu-item">
                <i class="ri-add-circle-line"></i> Create Project
            </a>
            <a href="../admin/projects.php?status=in_progress" class="menu-item">
                <i class="ri-loader-2-line"></i> In Progress
            </a>
            <a href="../admin/projects.php?status=completed" class="menu-item">
                <i class="ri-checkbox-circle-line"></i> Completed
            </a>
        </div>

        <!-- Team Section -->
        <div class="menu-section" data-menu="team">
            <div class="menu-title">TEAM MANAGEMENT</div>
            <a href="../admin/team.php" class="menu-item <?php echo ($current_page == 'team.php') ? 'active' : ''; ?>">
                <i class="ri-team-line"></i> All Team Members
            </a>
            <a href="../admin/users.php?action=add" class="menu-item <?php echo ($current_page == 'users.php') ? 'active' : ''; ?>">
                <i class="ri-user-add-line"></i> Add User
            </a>
            <a href="../admin/team.php?role=manager" class="menu-item">
                <i class="ri-shield-user-line"></i> Managers
            </a>
            <a href="../admin/team.php?role=employee" class="menu-item">
                <i class="ri-user-3-line"></i> Employees
            </a>
        </div>

        <!-- Bulk Operations Section -->
        <div class="menu-section" data-menu="bulk">
            <div class="menu-title">BULK OPERATIONS</div>
            <a href="../admin/bulk-operations.php" class="menu-item <?php echo ($current_page == 'bulk-operations.php') ? 'active' : ''; ?>">
                <i class="ri-user-settings-line"></i> Bulk User Operations
            </a>
            <a href="../admin/bulk-import.php" class="menu-item <?php echo ($current_page == 'bulk-import.php') ? 'active' : ''; ?>">
                <i class="ri-file-upload-line"></i> Bulk Import
            </a>
        </div>

        <!-- Gamification Section -->
        <div class="menu-section" data-menu="gamification">
            <div class="menu-title">GAMIFICATION</div>
            <a href="../admin/gamification.php" class="menu-item <?php echo ($current_page == 'gamification.php') ? 'active' : ''; ?>">
                <i class="ri-vip-crown-line"></i> Leaderboard
            </a>
            <a href="../admin/gamification.php#badges" class="menu-item">
                <i class="ri-medal-line"></i> Badges
            </a>
            <a href="../admin/gamification.php#achievements" class="menu-item">
                <i class="ri-star-line"></i> Achievements
            </a>
        </div>

        <!-- Analytics Section -->
        <div class="menu-section" data-menu="analytics">
            <div class="menu-title">ANALYTICS & REPORTS</div>
            <a href="../admin/analytics.php" class="menu-item <?php echo ($current_page == 'analytics.php') ? 'active' : ''; ?>">
                <i class="ri-pie-chart-line"></i> Overview
            </a>
            <a href="../admin/advanced-analytics.php" class="menu-item <?php echo ($current_page == 'advanced-analytics.php') ? 'active' : ''; ?>">
                <i class="ri-line-chart-line"></i> Advanced Analytics
            </a>
            <a href="../admin/profit-loss.php" class="menu-item <?php echo ($current_page == 'profit-loss.php') ? 'active' : ''; ?>">
                <i class="ri-funds-line"></i> Profit & Loss
            </a>
            <a href="../admin/budget.php" class="menu-item <?php echo ($current_page == 'budget.php') ? 'active' : ''; ?>">
                <i class="ri-money-rupee-circle-line"></i> Budget Tracking
            </a>
        </div>

        <!-- Calendar Section -->
        <div class="menu-section" data-menu="calendar">
            <div class="menu-title">CALENDAR</div>
            <a href="../admin/calendar.php" class="menu-item <?php echo ($current_page == 'calendar.php') ? 'active' : ''; ?>">
                <i class="ri-calendar-2-line"></i> Full Calendar
            </a>
            <a href="../admin/calendar.php#month" class="menu-item">
                <i class="ri-calendar-line"></i> Month View
            </a>
            <a href="../admin/calendar.php#week" class="menu-item">
                <i class="ri-calendar-event-line"></i> Week View
            </a>
            <a href="../admin/calendar.php#day" class="menu-item">
                <i class="ri-calendar-check-line"></i> Day View
            </a>
        </div>

        <!-- Settings Section -->
        <div class="menu-section" data-menu="settings">
            <div class="menu-title">SETTINGS</div>
            <a href="../admin/email-config.php" class="menu-item <?php echo ($current_page == 'email-config.php') ? 'active' : ''; ?>">
                <i class="ri-mail-settings-line"></i> Email Configuration
            </a>
            <a href="../admin/email-test.php" class="menu-item <?php echo ($current_page == 'email-test.php') ? 'active' : ''; ?>">
                <i class="ri-test-tube-line"></i> Test Emails
            </a>
            <a href="../admin/profile.php" class="menu-item">
                <i class="ri-user-settings-line"></i> Profile
            </a>
            <a href="../logout.php" class="menu-item">
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
            // Don't prevent default - allow navigation
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
