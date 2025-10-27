<!-- Employee Sidebar Navigation -->
<aside class="sidebar">
    <div class="sidebar-header">
        <h2>Foxhole</h2>
        <div class="user-role">Employee Panel</div>
    </div>

    <nav class="sidebar-nav">
        <a href="index.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
            <span class="icon">📊</span>
            Dashboard
        </a>
        <a href="tasks.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'tasks.php' || basename($_SERVER['PHP_SELF']) == 'tasks-premium.php' ? 'active' : ''; ?>">
            <span class="icon">✓</span>
            My Tasks
        </a>
        <a href="calendar.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'calendar.php' ? 'active' : ''; ?>">
            <span class="icon">📅</span>
            Calendar
        </a>
        <a href="chat.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'chat.php' ? 'active' : ''; ?>">
            <span class="icon">💬</span>
            Team Chat
        </a>
        <a href="time-logs.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'time-logs.php' ? 'active' : ''; ?>">
            <span class="icon">⏱️</span>
            Time Logs
        </a>
        <a href="my-stats.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'my-stats.php' ? 'active' : ''; ?>">
            <span class="icon">📈</span>
            My Statistics
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar">
                <?php echo strtoupper(substr($currentUser['full_name'], 0, 1)); ?>
            </div>
            <div class="user-details">
                <h4><?php echo e($currentUser['full_name']); ?></h4>
                <p><?php echo e($currentUser['job_title'] ?? 'Employee'); ?></p>
            </div>
        </div>
        <a href="../logout.php" class="btn btn-secondary btn-block btn-sm">Logout</a>
    </div>
</aside>
