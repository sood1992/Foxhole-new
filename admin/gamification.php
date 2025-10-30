<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !hasRole('admin')) {
    redirect('../login.php');
}

$db = getDBConnection();
$currentUser = getCurrentUser();

// Get top performers for leaderboard
$leaderboard = $db->query("
    SELECT
        u.id, u.full_name, u.job_title,
        COUNT(DISTINCT t.project_id) as projects,
        COUNT(DISTINCT t.id) as tasks_assigned,
        SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as tasks_completed,
        (SELECT SUM(duration_minutes) FROM time_logs WHERE user_id = u.id AND MONTH(start_time) = MONTH(CURRENT_DATE())) as total_minutes,
        (SELECT COUNT(*) FROM tasks WHERE assigned_to = u.id AND status = 'completed' AND completed_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 7 DAY)) as tasks_this_week
    FROM users u
    LEFT JOIN tasks t ON u.id = t.assigned_to
    WHERE u.role IN ('manager', 'employee') AND u.is_active = 1
    GROUP BY u.id
    ORDER BY tasks_completed DESC, total_minutes DESC
    LIMIT 20
")->fetchAll();

// Calculate scores and add ranks
foreach ($leaderboard as $key => $user) {
    // Score calculation: tasks_completed * 10 + (hours * 5)
    $hours = ($user['total_minutes'] ?? 0) / 60;
    $leaderboard[$key]['score'] = ($user['tasks_completed'] * 10) + ($hours * 5);
    $leaderboard[$key]['rank'] = $key + 1;
}

// Sort by score
usort($leaderboard, function($a, $b) {
    return $b['score'] - $a['score'];
});

// Reassign ranks after sorting
foreach ($leaderboard as $key => $user) {
    $leaderboard[$key]['rank'] = $key + 1;
}

// Sample badges data (in a real system, this would come from database)
$badges = [
    ['name' => 'Early Bird', 'description' => 'First to log time in the morning', 'icon' => 'fa-sun', 'color' => '#f8b739', 'awarded' => 12],
    ['name' => 'Task Master', 'description' => 'Completed 100+ tasks', 'icon' => 'fa-check-double', 'color' => '#17b06b', 'awarded' => 8],
    ['name' => 'Speedster', 'description' => 'Completed tasks before deadline', 'icon' => 'fa-bolt', 'color' => '#2ea1f8', 'awarded' => 15],
    ['name' => 'Team Player', 'description' => 'Helped 10+ team members', 'icon' => 'fa-handshake', 'color' => '#667eea', 'awarded' => 6],
    ['name' => 'Night Owl', 'description' => 'Most productive after hours', 'icon' => 'fa-moon', 'color' => '#764ba2', 'awarded' => 5],
    ['name' => 'Perfectionist', 'description' => 'Zero defects in tasks', 'icon' => 'fa-star', 'color' => '#f8b739', 'awarded' => 4],
];

// Sample achievements data
$achievements = [
    ['title' => '30-Day Streak', 'description' => 'Logged time for 30 consecutive days', 'progress' => 85, 'target' => 100],
    ['title' => 'Project Champion', 'description' => 'Complete 5 projects', 'progress' => 60, 'target' => 100],
    ['title' => 'Time Warrior', 'description' => 'Log 200+ hours', 'progress' => 75, 'target' => 100],
    ['title' => 'Bug Hunter', 'description' => 'Fix 50 bugs', 'progress' => 40, 'target' => 100],
    ['title' => 'Mentor', 'description' => 'Help 20 team members', 'progress' => 30, 'target' => 100],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gamification - <?php echo SITE_NAME; ?> V3</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/vien-v3.css">
</head>
<body>
    <div class="app-container">
        <?php include '../includes/v3-admin-sidebar.php'; ?>

        <div class="main-content">
            <?php include '../includes/v3-header.php'; ?>

            <div class="content-wrapper">
                <!-- Page Title -->
                <div style="margin-bottom: 30px;">
                    <h1 style="margin-bottom: 8px;">Gamification</h1>
                    <p style="color: var(--text-secondary); font-size: 14px; margin: 0;">
                        Track team performance, achievements, and rewards
                    </p>
                </div>

                <!-- Tab Navigation -->
                <div class="tab-nav" style="margin-bottom: 30px;">
                    <a href="#leaderboard" class="tab-link active" data-tab="leaderboard">
                        <i class="fas fa-crown"></i> Leaderboard
                    </a>
                    <a href="#badges" class="tab-link" data-tab="badges">
                        <i class="fas fa-award"></i> Badges
                    </a>
                    <a href="#achievements" class="tab-link" data-tab="achievements">
                        <i class="fas fa-star"></i> Achievements
                    </a>
                </div>

                <!-- Tab: Leaderboard -->
                <div class="tab-content active" id="leaderboard">
                    <!-- Top 3 Podium -->
                    <?php if (count($leaderboard) >= 3): ?>
                    <div class="row" style="margin-bottom: 30px;">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body" style="padding: 40px;">
                                    <div style="display: flex; justify-content: center; align-items: flex-end; gap: 30px;">
                                        <!-- 2nd Place -->
                                        <div style="text-align: center; flex: 1; max-width: 200px;">
                                            <div style="width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg, #c0c0c0 0%, #e8e8e8 100%); display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 32px; margin: 0 auto 16px;">
                                                2
                                            </div>
                                            <h3 style="margin: 0 0 4px 0;"><?php echo e($leaderboard[1]['full_name']); ?></h3>
                                            <p style="color: var(--text-secondary); font-size: 13px; margin: 0 0 8px 0;">
                                                <?php echo e($leaderboard[1]['job_title'] ?? 'Employee'); ?>
                                            </p>
                                            <div style="font-size: 24px; font-weight: 700; color: #c0c0c0;">
                                                <?php echo number_format($leaderboard[1]['score']); ?> pts
                                            </div>
                                        </div>

                                        <!-- 1st Place -->
                                        <div style="text-align: center; flex: 1; max-width: 200px; margin-top: -40px;">
                                            <div style="width: 100px; height: 100px; border-radius: 50%; background: linear-gradient(135deg, #ffd700 0%, #ffed4e 100%); display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 40px; margin: 0 auto 16px; box-shadow: 0 8px 20px rgba(255, 215, 0, 0.4);">
                                                <i class="fas fa-crown"></i>
                                            </div>
                                            <h3 style="margin: 0 0 4px 0; font-size: 20px;"><?php echo e($leaderboard[0]['full_name']); ?></h3>
                                            <p style="color: var(--text-secondary); font-size: 13px; margin: 0 0 8px 0;">
                                                <?php echo e($leaderboard[0]['job_title'] ?? 'Employee'); ?>
                                            </p>
                                            <div style="font-size: 32px; font-weight: 700; color: #ffd700;">
                                                <?php echo number_format($leaderboard[0]['score']); ?> pts
                                            </div>
                                        </div>

                                        <!-- 3rd Place -->
                                        <div style="text-align: center; flex: 1; max-width: 200px;">
                                            <div style="width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg, #cd7f32 0%, #e8a869 100%); display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 32px; margin: 0 auto 16px;">
                                                3
                                            </div>
                                            <h3 style="margin: 0 0 4px 0;"><?php echo e($leaderboard[2]['full_name']); ?></h3>
                                            <p style="color: var(--text-secondary); font-size: 13px; margin: 0 0 8px 0;">
                                                <?php echo e($leaderboard[2]['job_title'] ?? 'Employee'); ?>
                                            </p>
                                            <div style="font-size: 24px; font-weight: 700; color: #cd7f32;">
                                                <?php echo number_format($leaderboard[2]['score']); ?> pts
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Full Leaderboard Table -->
                    <div class="card">
                        <div class="card-header">
                            <div>
                                <h3 style="margin: 0;">Full Leaderboard</h3>
                                <p style="font-size: 13px; color: var(--text-secondary); margin: 4px 0 0 0;">
                                    Complete rankings based on tasks completed and hours logged
                                </p>
                            </div>
                        </div>
                        <div class="card-body" style="padding: 0;">
                            <div class="data-table-container" style="border: none; box-shadow: none;">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Rank</th>
                                            <th>Name</th>
                                            <th>Role</th>
                                            <th>Tasks Completed</th>
                                            <th>Hours This Month</th>
                                            <th>This Week</th>
                                            <th>Score</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($leaderboard as $user): ?>
                                        <tr>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 8px;">
                                                    <?php if ($user['rank'] <= 3): ?>
                                                        <i class="fas fa-medal" style="color: <?php echo $user['rank'] === 1 ? '#ffd700' : ($user['rank'] === 2 ? '#c0c0c0' : '#cd7f32'); ?>; font-size: 18px;"></i>
                                                    <?php endif; ?>
                                                    <strong style="font-size: 16px;">#<?php echo $user['rank']; ?></strong>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 12px;">
                                                    <div style="width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 14px;">
                                                        <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                                                    </div>
                                                    <strong><?php echo e($user['full_name']); ?></strong>
                                                </div>
                                            </td>
                                            <td><?php echo e($user['job_title'] ?? 'N/A'); ?></td>
                                            <td>
                                                <span class="badge badge-success">
                                                    <?php echo $user['tasks_completed']; ?> tasks
                                                </span>
                                            </td>
                                            <td>
                                                <strong style="color: var(--primary);">
                                                    <?php echo formatHours($user['total_minutes'] ?? 0); ?>h
                                                </strong>
                                            </td>
                                            <td>
                                                <span class="badge badge-info">
                                                    <?php echo $user['tasks_this_week']; ?> this week
                                                </span>
                                            </td>
                                            <td>
                                                <strong style="font-size: 16px; color: var(--heading-color);">
                                                    <?php echo number_format($user['score']); ?> pts
                                                </strong>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- End Tab: Leaderboard -->

                <!-- Tab: Badges -->
                <div class="tab-content" id="badges">
                    <div class="row">
                        <?php foreach ($badges as $badge): ?>
                        <div class="col-lg-4 col-md-6">
                            <div class="card hover-lift" style="cursor: pointer;">
                                <div class="card-body" style="text-align: center; padding: 30px;">
                                    <div style="width: 80px; height: 80px; border-radius: 50%; background: <?php echo $badge['color']; ?>; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; box-shadow: 0 8px 16px rgba(0,0,0,0.1);">
                                        <i class="fas <?php echo $badge['icon']; ?>" style="font-size: 36px; color: white;"></i>
                                    </div>
                                    <h4 style="margin-bottom: 8px;"><?php echo e($badge['name']); ?></h4>
                                    <p style="color: var(--text-secondary); font-size: 13px; margin-bottom: 16px;">
                                        <?php echo e($badge['description']); ?>
                                    </p>
                                    <div style="padding-top: 16px; border-top: 1px solid var(--border-light);">
                                        <span style="color: var(--text-secondary); font-size: 12px;">
                                            Awarded to <strong style="color: var(--primary);"><?php echo $badge['awarded']; ?></strong> members
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <!-- End Tab: Badges -->

                <!-- Tab: Achievements -->
                <div class="tab-content" id="achievements">
                    <div class="card">
                        <div class="card-header">
                            <div>
                                <h3 style="margin: 0;">Team Achievements</h3>
                                <p style="font-size: 13px; color: var(--text-secondary); margin: 4px 0 0 0;">
                                    Track progress towards team goals and milestones
                                </p>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php foreach ($achievements as $achievement): ?>
                            <div style="margin-bottom: 30px; padding-bottom: 30px; border-bottom: 1px solid var(--border-light);">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                                    <div>
                                        <h4 style="margin: 0 0 4px 0;"><?php echo e($achievement['title']); ?></h4>
                                        <p style="color: var(--text-secondary); font-size: 13px; margin: 0;">
                                            <?php echo e($achievement['description']); ?>
                                        </p>
                                    </div>
                                    <div style="text-align: right;">
                                        <div style="font-size: 24px; font-weight: 700; color: var(--primary);">
                                            <?php echo $achievement['progress']; ?>%
                                        </div>
                                    </div>
                                </div>
                                <div style="height: 12px; background: var(--border-light); border-radius: 6px; overflow: hidden;">
                                    <div style="width: <?php echo $achievement['progress']; ?>%; height: 100%; background: linear-gradient(90deg, #17b06b 0%, #14d48f 100%); transition: width 0.5s ease;"></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <!-- End Tab: Achievements -->

            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Tab navigation functionality
        const tabLinks = document.querySelectorAll('.tab-link');
        const tabContents = document.querySelectorAll('.tab-content');

        // Check for hash in URL on page load
        const currentHash = window.location.hash.slice(1) || 'leaderboard';
        switchTab(currentHash);

        tabLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const tabId = this.getAttribute('data-tab');
                switchTab(tabId);
                // Update URL hash without scrolling
                history.pushState(null, null, '#' + tabId);
            });
        });

        function switchTab(tabId) {
            // Remove active class from all tabs
            tabLinks.forEach(l => l.classList.remove('active'));
            tabContents.forEach(c => c.classList.remove('active'));

            // Add active class to selected tab
            const selectedLink = document.querySelector(`.tab-link[data-tab="${tabId}"]`);
            const selectedContent = document.getElementById(tabId);

            if (selectedLink && selectedContent) {
                selectedLink.classList.add('active');
                selectedContent.classList.add('active');
            }
        }

        // Listen for hash changes
        window.addEventListener('hashchange', function() {
            const hash = window.location.hash.slice(1) || 'leaderboard';
            switchTab(hash);
        });
    });
    </script>
</body>
</html>
