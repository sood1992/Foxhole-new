<?php
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/gamification-functions.php';

if (!isLoggedIn() || !hasRole('employee')) {
    redirect('../login.php');
}

$db = getDBConnection();
$currentUser = getCurrentUser();

// Get gamification stats
$gamificationStats = getUserGamificationStats($currentUser['id']);
$badges = getUserBadges($currentUser['id']);

// Define all available rewards and their requirements
$availableTitles = [
    ['title' => 'Newcomer', 'points_required' => 0, 'icon' => '🌱', 'description' => 'Welcome aboard!'],
    ['title' => 'Task Warrior', 'points_required' => 500, 'icon' => '⚔️', 'description' => 'Completed 50+ tasks'],
    ['title' => 'Productivity Master', 'points_required' => 1000, 'icon' => '👑', 'description' => 'Reached level 10'],
    ['title' => 'Time Lord', 'points_required' => 2000, 'icon' => '⏰', 'description' => 'Logged 200+ hours'],
    ['title' => 'Elite Performer', 'points_required' => 5000, 'icon' => '💎', 'description' => 'Top 1% productivity'],
    ['title' => 'Legendary', 'points_required' => 10000, 'icon' => '🏆', 'description' => 'Ultimate achievement']
];

// Define milestone rewards
$milestones = [
    ['name' => 'First Blood', 'tasks_required' => 1, 'icon' => '🎯', 'reward' => 'Beginner Badge'],
    ['name' => 'Getting Started', 'tasks_required' => 10, 'icon' => '🚀', 'reward' => 'Progress Badge'],
    ['name' => 'Century Club', 'tasks_required' => 100, 'icon' => '💯', 'reward' => 'Century Badge'],
    ['name' => 'Half-K Hero', 'tasks_required' => 500, 'icon' => '🦸', 'reward' => 'Hero Title'],
    ['name' => 'Task Master', 'tasks_required' => 1000, 'icon' => '🎓', 'reward' => 'Master Title'],
    ['name' => 'Unstoppable', 'tasks_required' => 5000, 'icon' => '⚡', 'reward' => 'Legend Status']
];

// Define streak rewards
$streakRewards = [
    ['name' => '3-Day Streak', 'days_required' => 3, 'icon' => '🔥', 'reward' => '+50 Bonus Points'],
    ['name' => 'Week Warrior', 'days_required' => 7, 'icon' => '🌟', 'reward' => '+100 Bonus Points'],
    ['name' => 'Two Week Champion', 'days_required' => 14, 'icon' => '⭐', 'reward' => '+200 Bonus Points'],
    ['name' => 'Monthly Maestro', 'days_required' => 30, 'icon' => '🏅', 'reward' => '+500 Bonus Points'],
    ['name' => 'Consistency King', 'days_required' => 60, 'icon' => '👑', 'reward' => 'Royal Badge'],
    ['name' => 'Unstoppable Force', 'days_required' => 100, 'icon' => '💪', 'reward' => 'Ultimate Badge']
];

// Calculate user's current title
$currentTitle = $availableTitles[0];
foreach ($availableTitles as $title) {
    if ($gamificationStats['points'] >= $title['points_required']) {
        $currentTitle = $title;
    }
}

// Calculate next title
$nextTitle = null;
foreach ($availableTitles as $title) {
    if ($title['points_required'] > $gamificationStats['points']) {
        $nextTitle = $title;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rewards & Achievements - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/vien-v3.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .rewards-hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: var(--radius-lg);
            padding: 48px 32px;
            text-align: center;
            color: white;
            margin-bottom: 32px;
            position: relative;
            overflow: hidden;
        }

        .rewards-hero::before {
            content: '✨';
            position: absolute;
            top: -20px;
            right: -20px;
            font-size: 120px;
            opacity: 0.2;
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(10deg); }
        }

        .current-title {
            font-size: 64px;
            margin-bottom: 16px;
        }

        .title-name {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .title-description {
            font-size: 16px;
            opacity: 0.9;
        }

        .rewards-section {
            margin-bottom: 48px;
        }

        .section-header {
            display: flex;
            align-items: center;
            margin-bottom: 24px;
        }

        .section-icon {
            font-size: 32px;
            margin-right: 16px;
        }

        .section-title {
            font-size: 24px;
            font-weight: 700;
        }

        .rewards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }

        .reward-card {
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            padding: 24px;
            text-align: center;
            transition: all 0.3s;
            border: 2px solid transparent;
            position: relative;
        }

        .reward-card.unlocked {
            border-color: var(--primary);
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.3);
        }

        .reward-card.locked {
            opacity: 0.5;
            filter: grayscale(80%);
        }

        .reward-card:hover {
            transform: translateY(-4px);
        }

        .reward-icon {
            font-size: 48px;
            margin-bottom: 16px;
        }

        .reward-name {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .reward-requirement {
            font-size: 13px;
            color: var(--text-secondary);
            margin-bottom: 12px;
        }

        .reward-status {
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .reward-status.unlocked {
            background: var(--green);
            color: white;
        }

        .reward-status.locked {
            background: var(--bg-tertiary);
            color: var(--text-secondary);
        }

        .progress-bar-container {
            background: var(--bg-tertiary);
            border-radius: 20px;
            height: 24px;
            margin-top: 12px;
            overflow: hidden;
            position: relative;
        }

        .progress-bar-fill {
            background: linear-gradient(90deg, var(--primary), var(--primary-dark));
            height: 100%;
            transition: width 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 600;
            color: white;
        }

        .locked-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(0,0,0,0.6);
            border-radius: var(--radius-lg);
        }

        .locked-icon {
            font-size: 32px;
            color: white;
        }

        .badge-showcase {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            justify-content: center;
            margin-top: 24px;
        }

        .badge-item {
            background: var(--bg-secondary);
            padding: 12px 20px;
            border-radius: 20px;
            font-size: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            transition: transform 0.2s;
        }

        .badge-item:hover {
            transform: scale(1.1);
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <?php include '../includes/v3-employee-sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <?php include '../includes/v3-header.php'; ?>

            <div class="content-wrapper">
                <!-- Hero Section -->
                <div class="rewards-hero">
                    <div class="current-title"><?php echo $currentTitle['icon']; ?></div>
                    <div class="title-name"><?php echo e($currentTitle['title']); ?></div>
                    <div class="title-description"><?php echo e($currentTitle['description']); ?></div>

                    <?php if ($nextTitle): ?>
                    <div style="margin-top: 32px; padding: 16px; background: rgba(255,255,255,0.1); border-radius: 12px;">
                        <div style="font-size: 14px; opacity: 0.9; margin-bottom: 8px;">
                            Next Title: <?php echo $nextTitle['icon']; ?> <?php echo e($nextTitle['title']); ?>
                        </div>
                        <div class="progress-bar-container">
                            <?php
                                $pointsToNext = $nextTitle['points_required'] - $gamificationStats['points'];
                                $progressPercent = ($gamificationStats['points'] / $nextTitle['points_required']) * 100;
                            ?>
                            <div class="progress-bar-fill" style="width: <?php echo min(100, $progressPercent); ?>%;">
                                <?php echo number_format($gamificationStats['points']); ?> / <?php echo number_format($nextTitle['points_required']); ?> points
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Your Badges -->
                <?php if (!empty($badges)): ?>
                <div class="dashboard-card" style="margin-bottom: 32px;">
                    <div class="card-header">
                        <h3>🏆 Your Badges</h3>
                    </div>
                    <div class="card-body">
                        <div class="badge-showcase">
                            <?php foreach ($badges as $badge): ?>
                            <div class="badge-item" title="<?php echo e($badge['badge_name'] . ' - ' . $badge['badge_description']); ?>">
                                <?php
                                    // Get emoji for badge type
                                    $badgeEmojis = [
                                        'first_task' => '🎯',
                                        'getting_started' => '🚀',
                                        'week_warrior' => '🌟',
                                        'speed_demon' => '⚡',
                                        'night_owl' => '🦉',
                                        'early_bird' => '🐦',
                                        'perfectionist' => '💯',
                                        'team_player' => '🤝',
                                        'task_master' => '🎓',
                                        'century' => '💯',
                                        'streak' => '🔥'
                                    ];
                                    echo $badgeEmojis[$badge['badge_type']] ?? '🏅';
                                ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Available Titles -->
                <div class="rewards-section">
                    <div class="section-header">
                        <div class="section-icon">👑</div>
                        <div class="section-title">Unlockable Titles</div>
                    </div>
                    <div class="rewards-grid">
                        <?php foreach ($availableTitles as $title): ?>
                        <?php $isUnlocked = $gamificationStats['points'] >= $title['points_required']; ?>
                        <div class="reward-card <?php echo $isUnlocked ? 'unlocked' : 'locked'; ?>">
                            <div class="reward-icon"><?php echo $title['icon']; ?></div>
                            <div class="reward-name"><?php echo e($title['title']); ?></div>
                            <div class="reward-requirement">
                                <?php echo number_format($title['points_required']); ?> points required
                            </div>
                            <div class="reward-status <?php echo $isUnlocked ? 'unlocked' : 'locked'; ?>">
                                <?php echo $isUnlocked ? '✓ Unlocked' : '🔒 Locked'; ?>
                            </div>
                            <?php if (!$isUnlocked): ?>
                            <div class="progress-bar-container">
                                <?php
                                    $progressPercent = ($gamificationStats['points'] / $title['points_required']) * 100;
                                ?>
                                <div class="progress-bar-fill" style="width: <?php echo min(100, $progressPercent); ?>%;">
                                    <?php echo round($progressPercent); ?>%
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Milestone Rewards -->
                <div class="rewards-section">
                    <div class="section-header">
                        <div class="section-icon">🎯</div>
                        <div class="section-title">Task Milestones</div>
                    </div>
                    <div class="rewards-grid">
                        <?php foreach ($milestones as $milestone): ?>
                        <?php $isUnlocked = $gamificationStats['total_tasks_completed'] >= $milestone['tasks_required']; ?>
                        <div class="reward-card <?php echo $isUnlocked ? 'unlocked' : 'locked'; ?>">
                            <div class="reward-icon"><?php echo $milestone['icon']; ?></div>
                            <div class="reward-name"><?php echo e($milestone['name']); ?></div>
                            <div class="reward-requirement">
                                Complete <?php echo number_format($milestone['tasks_required']); ?> tasks
                            </div>
                            <div style="margin: 12px 0; font-size: 13px; color: var(--primary); font-weight: 600;">
                                <?php echo e($milestone['reward']); ?>
                            </div>
                            <div class="reward-status <?php echo $isUnlocked ? 'unlocked' : 'locked'; ?>">
                                <?php echo $isUnlocked ? '✓ Unlocked' : '🔒 Locked'; ?>
                            </div>
                            <?php if (!$isUnlocked): ?>
                            <div class="progress-bar-container">
                                <?php
                                    $progressPercent = ($gamificationStats['total_tasks_completed'] / $milestone['tasks_required']) * 100;
                                ?>
                                <div class="progress-bar-fill" style="width: <?php echo min(100, $progressPercent); ?>%;">
                                    <?php echo number_format($gamificationStats['total_tasks_completed']); ?> / <?php echo number_format($milestone['tasks_required']); ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Streak Rewards -->
                <div class="rewards-section">
                    <div class="section-header">
                        <div class="section-icon">🔥</div>
                        <div class="section-title">Streak Rewards</div>
                    </div>
                    <div class="rewards-grid">
                        <?php foreach ($streakRewards as $reward): ?>
                        <?php $isUnlocked = $gamificationStats['longest_streak'] >= $reward['days_required']; ?>
                        <div class="reward-card <?php echo $isUnlocked ? 'unlocked' : 'locked'; ?>">
                            <div class="reward-icon"><?php echo $reward['icon']; ?></div>
                            <div class="reward-name"><?php echo e($reward['name']); ?></div>
                            <div class="reward-requirement">
                                Maintain <?php echo $reward['days_required']; ?> day streak
                            </div>
                            <div style="margin: 12px 0; font-size: 13px; color: var(--primary); font-weight: 600;">
                                <?php echo e($reward['reward']); ?>
                            </div>
                            <div class="reward-status <?php echo $isUnlocked ? 'unlocked' : 'locked'; ?>">
                                <?php echo $isUnlocked ? '✓ Unlocked' : '🔒 Locked'; ?>
                            </div>
                            <?php if (!$isUnlocked): ?>
                            <div class="progress-bar-container">
                                <?php
                                    $progressPercent = ($gamificationStats['longest_streak'] / $reward['days_required']) * 100;
                                ?>
                                <div class="progress-bar-fill" style="width: <?php echo min(100, $progressPercent); ?>%;">
                                    <?php echo $gamificationStats['longest_streak']; ?> / <?php echo $reward['days_required']; ?> days
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/theme.js"></script>
</body>
</html>
