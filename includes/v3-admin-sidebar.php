<?php
/**
 * V3 Two-Panel Sidebar for Admin
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
            <a href="../admin/index.php" class="main-menu-item <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>"
               data-menu="dashboard" title="Dashboard">
                <i class="fas fa-home"></i>
            </a>
            <a href="../admin/projects.php" class="main-menu-item <?php echo (in_array($current_page, ['projects.php', 'create-project.php'])) ? 'active' : ''; ?>"
               data-menu="projects" title="Projects">
                <i class="fas fa-folder"></i>
            </a>
            <a href="../admin/team.php" class="main-menu-item <?php echo (in_array($current_page, ['team.php', 'users.php'])) ? 'active' : ''; ?>"
               data-menu="team" title="Team">
                <i class="fas fa-users"></i>
            </a>
            <a href="../admin/bulk-operations.php" class="main-menu-item <?php echo (in_array($current_page, ['bulk-operations.php', 'bulk-import.php'])) ? 'active' : ''; ?>"
               data-menu="bulk" title="Bulk Operations">
                <i class="fas fa-layer-group"></i>
            </a>
            <a href="../admin/gamification.php" class="main-menu-item <?php echo ($current_page == 'gamification.php') ? 'active' : ''; ?>"
               data-menu="gamification" title="Gamification">
                <i class="fas fa-trophy"></i>
            </a>
            <a href="../admin/analytics.php" class="main-menu-item <?php echo (in_array($current_page, ['analytics.php', 'advanced-analytics.php'])) ? 'active' : ''; ?>"
               data-menu="analytics" title="Analytics">
                <i class="fas fa-chart-line"></i>
            </a>
            <a href="../admin/email-config.php" class="main-menu-item <?php echo (in_array($current_page, ['email-config.php', 'email-test.php'])) ? 'active' : ''; ?>"
               data-menu="settings" title="Settings">
                <i class="fas fa-cog"></i>
            </a>
        </div>
    </div>

    <!-- Sub Panel (Menu Items) -->
    <div class="sidebar-sub-panel">
        <!-- Dashboard Section -->
        <div class="menu-section" data-menu="dashboard">
            <div class="menu-title">Dashboard</div>
            <a href="../admin/index.php" class="menu-item <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i> Overview
            </a>
            <a href="../admin/index.php#stats" class="menu-item">
                <i class="fas fa-chart-bar"></i> Statistics
            </a>
            <a href="../admin/index.php#activity" class="menu-item">
                <i class="fas fa-clock"></i> Recent Activity
            </a>
        </div>

        <!-- Projects Section -->
        <div class="menu-section" data-menu="projects">
            <div class="menu-title">Projects</div>
            <a href="../admin/projects.php" class="menu-item <?php echo ($current_page == 'projects.php') ? 'active' : ''; ?>">
                <i class="fas fa-list"></i> All Projects
            </a>
            <a href="../admin/projects.php#create" class="menu-item">
                <i class="fas fa-plus"></i> Create Project
            </a>
            <a href="../admin/projects.php?status=in_progress" class="menu-item">
                <i class="fas fa-spinner"></i> In Progress
            </a>
            <a href="../admin/projects.php?status=completed" class="menu-item">
                <i class="fas fa-check-circle"></i> Completed
            </a>
        </div>

        <!-- Team Section -->
        <div class="menu-section" data-menu="team">
            <div class="menu-title">Team Management</div>
            <a href="../admin/team.php" class="menu-item <?php echo ($current_page == 'team.php') ? 'active' : ''; ?>">
                <i class="fas fa-users"></i> All Team Members
            </a>
            <a href="../admin/users.php?action=add" class="menu-item <?php echo ($current_page == 'users.php') ? 'active' : ''; ?>">
                <i class="fas fa-user-plus"></i> Add User
            </a>
            <a href="../admin/team.php?role=manager" class="menu-item">
                <i class="fas fa-user-tie"></i> Managers
            </a>
            <a href="../admin/team.php?role=employee" class="menu-item">
                <i class="fas fa-user"></i> Employees
            </a>
        </div>

        <!-- Bulk Operations Section -->
        <div class="menu-section" data-menu="bulk">
            <div class="menu-title">Bulk Operations</div>
            <a href="../admin/bulk-operations.php" class="menu-item <?php echo ($current_page == 'bulk-operations.php') ? 'active' : ''; ?>">
                <i class="fas fa-users-cog"></i> Bulk User Operations
            </a>
            <a href="../admin/bulk-import.php" class="menu-item <?php echo ($current_page == 'bulk-import.php') ? 'active' : ''; ?>">
                <i class="fas fa-file-import"></i> Bulk Import
            </a>
        </div>

        <!-- Gamification Section -->
        <div class="menu-section" data-menu="gamification">
            <div class="menu-title">Gamification</div>
            <a href="../admin/gamification.php" class="menu-item <?php echo ($current_page == 'gamification.php') ? 'active' : ''; ?>">
                <i class="fas fa-crown"></i> Leaderboard
            </a>
            <a href="../admin/gamification.php#badges" class="menu-item">
                <i class="fas fa-award"></i> Badges
            </a>
            <a href="../admin/gamification.php#achievements" class="menu-item">
                <i class="fas fa-star"></i> Achievements
            </a>
        </div>

        <!-- Analytics Section -->
        <div class="menu-section" data-menu="analytics">
            <div class="menu-title">Analytics & Reports</div>
            <a href="../admin/analytics.php" class="menu-item <?php echo ($current_page == 'analytics.php') ? 'active' : ''; ?>">
                <i class="fas fa-chart-pie"></i> Overview
            </a>
            <a href="../admin/advanced-analytics.php" class="menu-item <?php echo ($current_page == 'advanced-analytics.php') ? 'active' : ''; ?>">
                <i class="fas fa-chart-line"></i> Advanced Analytics
            </a>
            <a href="../admin/budget.php" class="menu-item <?php echo ($current_page == 'budget.php') ? 'active' : ''; ?>">
                <i class="fas fa-rupee-sign"></i> Budget Tracking
            </a>
        </div>

        <!-- Settings Section -->
        <div class="menu-section" data-menu="settings">
            <div class="menu-title">Settings</div>
            <a href="../admin/email-config.php" class="menu-item <?php echo ($current_page == 'email-config.php') ? 'active' : ''; ?>">
                <i class="fas fa-envelope"></i> Email Configuration
            </a>
            <a href="../admin/email-test.php" class="menu-item <?php echo ($current_page == 'email-test.php') ? 'active' : ''; ?>">
                <i class="fas fa-vial"></i> Test Emails
            </a>
            <a href="../profile.php" class="menu-item <?php echo ($current_page == 'profile.php') ? 'active' : ''; ?>">
                <i class="fas fa-user-circle"></i> Profile
            </a>
            <a href="../logout.php" class="menu-item">
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
