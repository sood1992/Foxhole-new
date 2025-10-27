<!-- Manager Sidebar Navigation -->
<aside class="sidebar">
    <div class="sidebar-header">
        <h2>Foxhole</h2>
        <div class="user-role">Manager Panel</div>
    </div>

    <nav class="sidebar-nav">
        <a href="index.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
            <span class="icon">📊</span>
            Dashboard
        </a>
        <a href="projects.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'projects.php' ? 'active' : ''; ?>">
            <span class="icon">📁</span>
            My Projects
        </a>
        <a href="create-project.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'create-project.php' ? 'active' : ''; ?>">
            <span class="icon">➕</span>
            Create Project
        </a>
        <a href="tasks.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'tasks.php' || basename($_SERVER['PHP_SELF']) == 'create-task.php' ? 'active' : ''; ?>">
            <span class="icon">✓</span>
            Tasks
        </a>
        <a href="create-task.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'create-task.php' ? 'active' : ''; ?>">
            <span class="icon">📝</span>
            Create Task
        </a>
        <a href="team.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'team.php' || basename($_SERVER['PHP_SELF']) == 'manage-users.php' ? 'active' : ''; ?>">
            <span class="icon">👥</span>
            My Team
        </a>
        <a href="manage-users.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'manage-users.php' ? 'active' : ''; ?>">
            <span class="icon">👤</span>
            Manage Users
        </a>
        <a href="reports.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
            <span class="icon">📋</span>
            Reports
        </a>
        <a href="../admin/calendar.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'calendar.php' ? 'active' : ''; ?>">
            <span class="icon">📅</span>
            Calendar
        </a>
        <a href="../admin/chat.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'chat.php' ? 'active' : ''; ?>">
            <span class="icon">💬</span>
            Team Chat
        </a>
        <a href="../admin/budget.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'budget.php' ? 'active' : ''; ?>">
            <span class="icon">💰</span>
            Budget
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar">
                <?php echo strtoupper(substr($currentUser['full_name'], 0, 1)); ?>
            </div>
            <div class="user-details">
                <h4><?php echo e($currentUser['full_name']); ?></h4>
                <p><?php echo e($currentUser['job_title'] ?? 'Project Manager'); ?></p>
            </div>
        </div>
        <a href="../logout.php" class="btn btn-secondary btn-block btn-sm">Logout</a>
    </div>
</aside>
