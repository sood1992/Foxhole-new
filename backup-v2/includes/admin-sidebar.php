<!-- Admin Sidebar Navigation -->
<aside class="sidebar">
    <div class="sidebar-header">
        <h2>Foxhole</h2>
        <div class="user-role">Admin Panel</div>
    </div>

    <nav class="sidebar-nav">
        <a href="index.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
            <span class="icon">📊</span>
            Dashboard
        </a>
        <a href="calendar.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'calendar.php' ? 'active' : ''; ?>">
            <span class="icon">📅</span>
            Calendar
        </a>
        <a href="analytics.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'analytics.php' ? 'active' : ''; ?>">
            <span class="icon">📈</span>
            Analytics
        </a>
        <a href="advanced-analytics.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'advanced-analytics.php' ? 'active' : ''; ?>">
            <span class="icon">🎯</span>
            Advanced Analytics
        </a>
        <a href="chat.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'chat.php' ? 'active' : ''; ?>">
            <span class="icon">💬</span>
            Team Chat
        </a>
        <a href="reports.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
            <span class="icon">📋</span>
            Reports
        </a>
        <a href="projects.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'projects.php' ? 'active' : ''; ?>">
            <span class="icon">📁</span>
            Projects
        </a>
        <a href="budget.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'budget.php' ? 'active' : ''; ?>">
            <span class="icon">💰</span>
            Budget & Costs
        </a>
        <a href="team.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'team.php' ? 'active' : ''; ?>">
            <span class="icon">👥</span>
            Team Management
        </a>
        <a href="bulk-operations.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'bulk-operations.php' ? 'active' : ''; ?>">
            <span class="icon">⚡</span>
            Bulk Operations
        </a>
        <a href="bulk-import.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'bulk-import.php' ? 'active' : ''; ?>">
            <span class="icon">📥</span>
            Bulk Import
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar">
                <?php echo strtoupper(substr($currentUser['full_name'], 0, 1)); ?>
            </div>
            <div class="user-details">
                <h4><?php echo e($currentUser['full_name']); ?></h4>
                <p><?php echo e($currentUser['job_title'] ?? 'Administrator'); ?></p>
            </div>
        </div>
        <a href="../logout.php" class="btn btn-secondary btn-block btn-sm">Logout</a>
    </div>
</aside>
