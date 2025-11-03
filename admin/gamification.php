<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !hasRole('admin')) {
    redirect('../login.php');
}

$db = getDBConnection();
$currentUser = getCurrentUser();

// Get date range for stats
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Get leaderboard data
try {
    $leaderboard = $db->prepare("
        SELECT
            u.id,
            u.full_name,
            u.job_title,
            up.total_points,
            up.streak_days,
            up.tasks_completed_early,
            up.tasks_completed_on_time,
            up.tasks_completed_late,
            COUNT(DISTINCT ub.badge_id) as badges_earned,
            COUNT(DISTINCT t.id) as total_tasks,
            SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks
        FROM users u
        LEFT JOIN user_points up ON u.id = up.user_id
        LEFT JOIN user_badges ub ON u.id = ub.user_id
        LEFT JOIN tasks t ON u.id = t.assigned_to
        WHERE u.role IN ('employee', 'manager') AND u.is_active = 1
        GROUP BY u.id
        ORDER BY up.total_points DESC, up.streak_days DESC
        LIMIT 20
    ");
    $leaderboard->execute();
    $topUsers = $leaderboard->fetchAll();
} catch (PDOException $e) {
    $topUsers = [];
}

// Get all badges
try {
    $badgesQuery = $db->query("
        SELECT
            b.*,
            COUNT(ub.user_id) as times_earned
        FROM badges b
        LEFT JOIN user_badges ub ON b.id = ub.badge_id
        GROUP BY b.id
        ORDER BY b.type, b.points_required
    ");
    $allBadges = $badgesQuery->fetchAll();
} catch (PDOException $e) {
    $allBadges = [];
}

// Get recent achievements
try {
    $recentAchievements = $db->prepare("
        SELECT
            u.full_name,
            b.name as badge_name,
            b.icon,
            b.type,
            ub.earned_at
        FROM user_badges ub
        JOIN users u ON ub.user_id = u.id
        JOIN badges b ON ub.badge_id = b.id
        ORDER BY ub.earned_at DESC
        LIMIT 10
    ");
    $recentAchievements->execute();
    $achievements = $recentAchievements->fetchAll();
} catch (PDOException $e) {
    $achievements = [];
}

// Calculate team stats
$totalPoints = array_sum(array_column($topUsers, 'total_points'));
$totalBadges = array_sum(array_column($topUsers, 'badges_earned'));
$avgStreak = count($topUsers) > 0 ? array_sum(array_column($topUsers, 'streak_days')) / count($topUsers) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gamification & Leaderboard - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/vien-v3.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    .leaderboard-table {
        width: 100%;
        background: white;
        border-radius: var(--radius-md);
        border: 1px solid var(--border);
        overflow: hidden;
    }

    .leaderboard-table table {
        width: 100%;
        border-collapse: collapse;
    }

    .leaderboard-table th {
        background: var(--bg-tertiary);
        padding: 16px;
        text-align: left;
        font-weight: 600;
        font-size: 13px;
        text-transform: uppercase;
        color: var(--text-secondary);
    }

    .leaderboard-table td {
        padding: 16px;
        border-top: 1px solid var(--border-light);
    }

    .leaderboard-table tr:hover {
        background: var(--bg-tertiary);
    }

    .rank-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        font-weight: 700;
        font-size: 14px;
    }

    .rank-1 { background: linear-gradient(135deg, #FFD700, #FFA500); color: white; }
    .rank-2 { background: linear-gradient(135deg, #C0C0C0, #808080); color: white; }
    .rank-3 { background: linear-gradient(135deg, #CD7F32, #8B4513); color: white; }
    .rank-other { background: var(--light); color: var(--text-secondary); }

    .user-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .user-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: var(--primary);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 16px;
    }

    .badge-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }

    .badge-card {
        background: white;
        border: 1px solid var(--border);
        border-radius: var(--radius-md);
        padding: 20px;
        transition: all 0.2s;
    }

    .badge-card:hover {
        box-shadow: var(--shadow-lg);
        transform: translateY(-2px);
    }

    .badge-icon {
        font-size: 48px;
        margin-bottom: 12px;
    }

    .badge-type {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        margin-bottom: 8px;
    }

    .badge-type.bronze { background: #CD7F32; color: white; }
    .badge-type.silver { background: #C0C0C0; color: white; }
    .badge-type.gold { background: #FFD700; color: #333; }
    .badge-type.platinum { background: linear-gradient(135deg, #E5E4E2, #BCC6CC); color: #333; }
    .badge-type.special { background: linear-gradient(135deg, #667eea, #764ba2); color: white; }

    .points-display {
        font-size: 32px;
        font-weight: 700;
        color: var(--primary);
    }

    .streak-indicator {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        background: linear-gradient(135deg, #FF6B6B, #FF8E53);
        color: white;
        border-radius: 20px;
        font-weight: 600;
        font-size: 14px;
    }

    .achievement-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px;
        background: white;
        border-radius: var(--radius-sm);
        border: 1px solid var(--border-light);
        margin-bottom: 8px;
    }

    .achievement-icon {
        font-size: 32px;
    }

    .stats-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    </style>
</head>
<body>
    <?php include '../includes/v3-admin-sidebar.php'; ?>

    <div class="main-content">
        <?php include '../includes/v3-header.php'; ?>

        <div class="content-area">
            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h1 class="page-title">🏆 Gamification & Leaderboard</h1>
                    <p class="page-subtitle">Team performance, badges, and achievements</p>
                </div>
            </div>

            <!-- Summary Stats -->
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #667eea, #764ba2);">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo number_format($totalPoints); ?></div>
                        <div class="stat-label">Total Points Earned</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb, #f5576c);">
                        <i class="fas fa-award"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo $totalBadges; ?></div>
                        <div class="stat-label">Badges Unlocked</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #FF6B6B, #FF8E53);">
                        <i class="fas fa-fire"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo number_format($avgStreak, 1); ?></div>
                        <div class="stat-label">Avg Streak Days</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe, #00f2fe);">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo count($topUsers); ?></div>
                        <div class="stat-label">Active Players</div>
                    </div>
                </div>
            </div>

            <!-- Leaderboard -->
            <div class="card" id="leaderboard">
                <div class="card-header">
                    <h2 class="card-title">🥇 Top Performers Leaderboard</h2>
                    <p style="color: var(--text-secondary); font-size: 14px; margin-top: 4px;">
                        Ranked by total points earned
                    </p>
                </div>
                <div class="card-body" style="padding: 0;">
                    <div class="leaderboard-table">
                        <table>
                            <thead>
                                <tr>
                                    <th style="width: 60px;">Rank</th>
                                    <th>Team Member</th>
                                    <th style="text-align: center;">Points</th>
                                    <th style="text-align: center;">Streak</th>
                                    <th style="text-align: center;">Badges</th>
                                    <th style="text-align: center;">Tasks</th>
                                    <th style="text-align: center;">Completion</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($topUsers)): ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center; padding: 40px; color: var(--text-secondary);">
                                            No leaderboard data available yet. Complete tasks to earn points!
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($topUsers as $index => $user):
                                        $rank = $index + 1;
                                        $rankClass = $rank <= 3 ? "rank-{$rank}" : "rank-other";
                                        $rankIcon = $rank == 1 ? '🥇' : ($rank == 2 ? '🥈' : ($rank == 3 ? '🥉' : ''));
                                        $completionRate = $user['total_tasks'] > 0 ?
                                            round(($user['completed_tasks'] / $user['total_tasks']) * 100) : 0;
                                    ?>
                                        <tr>
                                            <td>
                                                <div class="rank-badge <?php echo $rankClass; ?>">
                                                    <?php echo $rankIcon ?: $rank; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="user-info">
                                                    <div class="user-avatar">
                                                        <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <div style="font-weight: 600; color: var(--heading-color);">
                                                            <?php echo htmlspecialchars($user['full_name']); ?>
                                                        </div>
                                                        <div style="font-size: 13px; color: var(--text-secondary);">
                                                            <?php echo htmlspecialchars($user['job_title'] ?? 'Team Member'); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td style="text-align: center;">
                                                <span class="points-display" style="font-size: 18px;">
                                                    <?php echo number_format($user['total_points'] ?? 0); ?>
                                                </span>
                                            </td>
                                            <td style="text-align: center;">
                                                <?php if (($user['streak_days'] ?? 0) > 0): ?>
                                                    <span class="streak-indicator">
                                                        🔥 <?php echo $user['streak_days']; ?> days
                                                    </span>
                                                <?php else: ?>
                                                    <span style="color: var(--text-tertiary);">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="text-align: center;">
                                                <span style="font-weight: 600; color: var(--primary); font-size: 16px;">
                                                    <?php echo $user['badges_earned'] ?? 0; ?>
                                                </span>
                                            </td>
                                            <td style="text-align: center;">
                                                <span style="font-weight: 500;">
                                                    <?php echo $user['completed_tasks']; ?> / <?php echo $user['total_tasks']; ?>
                                                </span>
                                            </td>
                                            <td style="text-align: center;">
                                                <span class="badge badge-<?php echo $completionRate >= 80 ? 'success' : ($completionRate >= 60 ? 'warning' : 'danger'); ?>">
                                                    <?php echo $completionRate; ?>%
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Badges System -->
            <div class="card" id="badges" style="margin-top: 30px;">
                <div class="card-header">
                    <h2 class="card-title">🎖️ Badge System</h2>
                    <p style="color: var(--text-secondary); font-size: 14px; margin-top: 4px;">
                        All available badges and their requirements
                    </p>
                </div>
                <div class="card-body">
                    <?php if (empty($allBadges)): ?>
                        <div style="text-align: center; padding: 40px; color: var(--text-secondary);">
                            No badges configured yet. Run gamification-schema.sql to set up badges.
                        </div>
                    <?php else: ?>
                        <div class="badge-grid">
                            <?php foreach ($allBadges as $badge): ?>
                                <div class="badge-card">
                                    <div class="badge-icon"><?php echo $badge['icon']; ?></div>
                                    <span class="badge-type <?php echo $badge['type']; ?>">
                                        <?php echo ucfirst($badge['type']); ?>
                                    </span>
                                    <h3 style="font-size: 18px; font-weight: 600; margin: 8px 0;">
                                        <?php echo htmlspecialchars($badge['name']); ?>
                                    </h3>
                                    <p style="color: var(--text-secondary); font-size: 14px; margin-bottom: 12px;">
                                        <?php echo htmlspecialchars($badge['description']); ?>
                                    </p>
                                    <div style="display: flex; justify-content: space-between; padding-top: 12px; border-top: 1px solid var(--border-light);">
                                        <div>
                                            <div style="font-size: 12px; color: var(--text-tertiary);">Requirement</div>
                                            <div style="font-weight: 600;"><?php echo $badge['criteria_value']; ?> <?php echo str_replace('_', ' ', $badge['criteria_type']); ?></div>
                                        </div>
                                        <div style="text-align: right;">
                                            <div style="font-size: 12px; color: var(--text-tertiary);">Earned by</div>
                                            <div style="font-weight: 600; color: var(--primary);"><?php echo $badge['times_earned']; ?> users</div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Achievements -->
            <div class="card" id="achievements" style="margin-top: 30px;">
                <div class="card-header">
                    <h2 class="card-title">⭐ Recent Achievements</h2>
                    <p style="color: var(--text-secondary); font-size: 14px; margin-top: 4px;">
                        Latest badges earned by team members
                    </p>
                </div>
                <div class="card-body">
                    <?php if (empty($achievements)): ?>
                        <div style="text-align: center; padding: 40px; color: var(--text-secondary);">
                            No achievements earned yet. Start completing tasks to unlock badges!
                        </div>
                    <?php else: ?>
                        <?php foreach ($achievements as $achievement): ?>
                            <div class="achievement-item">
                                <div class="achievement-icon"><?php echo $achievement['icon']; ?></div>
                                <div style="flex: 1;">
                                    <div style="font-weight: 600; color: var(--heading-color);">
                                        <?php echo htmlspecialchars($achievement['full_name']); ?>
                                        <span style="font-weight: 400; color: var(--text-secondary);">unlocked</span>
                                        <?php echo htmlspecialchars($achievement['badge_name']); ?>
                                    </div>
                                    <div style="font-size: 13px; color: var(--text-tertiary);">
                                        <?php echo date('M j, Y g:i A', strtotime($achievement['earned_at'])); ?>
                                    </div>
                                </div>
                                <span class="badge-type <?php echo $achievement['type']; ?>">
                                    <?php echo ucfirst($achievement['type']); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
