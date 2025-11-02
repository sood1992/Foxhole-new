<?php
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/gamification-functions.php';

if (!isLoggedIn() || !hasRole('admin')) {
    redirect('../login.php');
}

$db = getDBConnection();
$currentUser = getCurrentUser();

// Get leaderboard
$leaderboard = getLeaderboard(20);

// Get overall stats
try {
    $totalPointsStmt = $db->query("SELECT SUM(points) as total FROM user_points");
    $totalPoints = $totalPointsStmt->fetch()['total'] ?? 0;

    $totalBadgesStmt = $db->query("SELECT COUNT(*) as total FROM user_badges");
    $totalBadges = $totalBadgesStmt->fetch()['total'] ?? 0;

    $avgLevelStmt = $db->query("SELECT AVG(level) as avg FROM user_points");
    $avgLevel = round($avgLevelStmt->fetch()['avg'] ?? 1, 1);

    $activeStreaksStmt = $db->query("SELECT COUNT(*) as count FROM user_points WHERE current_streak >= 3");
    $activeStreaks = $activeStreaksStmt->fetch()['count'] ?? 0;
} catch (Exception $e) {
    $totalPoints = 0;
    $totalBadges = 0;
    $avgLevel = 1;
    $activeStreaks = 0;
}

// Get recent badges
try {
    $recentBadgesStmt = $db->query("
        SELECT ub.*, u.full_name, u.avatar
        FROM user_badges ub
        JOIN users u ON ub.user_id = u.id
        ORDER BY ub.earned_at DESC
        LIMIT 10
    ");
    $recentBadges = $recentBadgesStmt->fetchAll();
} catch (Exception $e) {
    $recentBadges = [];
}

// Get level distribution
try {
    $levelDistStmt = $db->query("
        SELECT level, COUNT(*) as count
        FROM user_points
        GROUP BY level
        ORDER BY level
    ");
    $levelDistribution = $levelDistStmt->fetchAll();
} catch (Exception $e) {
    $levelDistribution = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gamification & Leaderboard - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/vien-v3.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        .leaderboard-container {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: var(--radius-lg);
            padding: 40px;
            color: white;
            margin-bottom: 32px;
        }

        .leaderboard-header {
            text-align: center;
            margin-bottom: 32px;
        }

        .leaderboard-title {
            font-size: 48px;
            margin-bottom: 16px;
        }

        .leaderboard-subtitle {
            font-size: 18px;
            opacity: 0.9;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            padding: 24px;
            text-align: center;
        }

        .stat-value {
            font-size: 48px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 8px;
        }

        .stat-label {
            font-size: 14px;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .leaderboard-list {
            background: white;
            border-radius: var(--radius-lg);
            overflow: hidden;
        }

        .leaderboard-item {
            display: grid;
            grid-template-columns: 80px 60px 1fr auto auto auto;
            gap: 16px;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid var(--border);
            transition: background 0.2s;
        }

        .leaderboard-item:hover {
            background: var(--bg-tertiary);
        }

        .leaderboard-item:first-child,
        .leaderboard-item:nth-child(2),
        .leaderboard-item:nth-child(3) {
            background: linear-gradient(90deg, rgba(251,191,36,0.1) 0%, transparent 100%);
        }

        .rank {
            font-size: 32px;
            font-weight: 700;
            text-align: center;
        }

        .rank-1 { color: #fbbf24; }
        .rank-2 { color: #94a3b8; }
        .rank-3 { color: #cd7f32; }

        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            background: var(--primary-light);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 600;
            color: var(--primary);
        }

        .user-info {
            flex: 1;
        }

        .user-name {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .user-title {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .user-stat {
            text-align: center;
            min-width: 80px;
        }

        .user-stat-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary);
        }

        .user-stat-label {
            font-size: 11px;
            color: var(--text-secondary);
            text-transform: uppercase;
        }

        .badge-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 16px;
            margin-top: 20px;
        }

        .badge-item {
            background: var(--bg-secondary);
            border-radius: var(--radius-md);
            padding: 16px;
            text-align: center;
        }

        .badge-icon {
            font-size: 48px;
            margin-bottom: 12px;
        }

        .badge-name {
            font-weight: 600;
            margin-bottom: 4px;
        }

        .badge-user {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .badge-date {
            font-size: 11px;
            color: var(--text-muted);
            margin-top: 4px;
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .leaderboard-item {
                grid-template-columns: 50px 1fr;
                gap: 12px;
            }

            .user-stat {
                grid-column: 1 / -1;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include '../includes/v3-admin-sidebar.php'; ?>

        <div class="main-content">
            <?php include '../includes/v3-header.php'; ?>

            <div class="content-wrapper">
                <div class="leaderboard-container">
                    <div class="leaderboard-header">
                        <div class="leaderboard-title">
                            <i class="fas fa-trophy"></i> Gamification & Leaderboard
                        </div>
                        <div class="leaderboard-subtitle">
                            Track team performance, achievements, and engagement
                        </div>
                    </div>
                </div>

                <!-- Overall Stats -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo number_format($totalPoints); ?></div>
                        <div class="stat-label">Total Points</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $totalBadges; ?></div>
                        <div class="stat-label">Badges Earned</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $avgLevel; ?></div>
                        <div class="stat-label">Average Level</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $activeStreaks; ?></div>
                        <div class="stat-label">Active Streaks</div>
                    </div>
                </div>

                <!-- Leaderboard -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3><i class="fas fa-ranking-star"></i> Top Performers</h3>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <div class="leaderboard-list">
                            <?php if (empty($leaderboard)): ?>
                                <div style="text-align: center; padding: 40px; color: var(--text-secondary);">
                                    <i class="fas fa-trophy" style="font-size: 48px; opacity: 0.3; margin-bottom: 16px;"></i>
                                    <p>No gamification data available yet. Complete the database migrations to enable this feature.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($leaderboard as $index => $user): ?>
                                    <?php $rank = $index + 1; ?>
                                    <div class="leaderboard-item">
                                        <div class="rank rank-<?php echo $rank <= 3 ? $rank : ''; ?>">
                                            <?php if ($rank == 1): ?>
                                                🥇
                                            <?php elseif ($rank == 2): ?>
                                                🥈
                                            <?php elseif ($rank == 3): ?>
                                                🥉
                                            <?php else: ?>
                                                #<?php echo $rank; ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="user-avatar">
                                            <?php if ($user['avatar']): ?>
                                                <img src="<?php echo e($user['avatar']); ?>" alt="" style="width: 100%; height: 100%; border-radius: 50%;">
                                            <?php else: ?>
                                                <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="user-info">
                                            <div class="user-name"><?php echo e($user['full_name']); ?></div>
                                            <div class="user-title"><?php echo e($user['job_title'] ?? 'Team Member'); ?></div>
                                        </div>
                                        <div class="user-stat">
                                            <div class="user-stat-value"><?php echo number_format($user['points']); ?></div>
                                            <div class="user-stat-label">Points</div>
                                        </div>
                                        <div class="user-stat">
                                            <div class="user-stat-value"><?php echo $user['level']; ?></div>
                                            <div class="user-stat-label">Level</div>
                                        </div>
                                        <div class="user-stat">
                                            <div class="user-stat-value">🔥 <?php echo $user['current_streak']; ?></div>
                                            <div class="user-stat-label">Streak</div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Recent Badges -->
                <div class="dashboard-card" style="margin-top: 32px;">
                    <div class="card-header">
                        <h3><i class="fas fa-award"></i> Recent Badges Earned</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recentBadges)): ?>
                            <div style="text-align: center; padding: 40px; color: var(--text-secondary);">
                                <i class="fas fa-award" style="font-size: 48px; opacity: 0.3; margin-bottom: 16px;"></i>
                                <p>No badges earned yet</p>
                            </div>
                        <?php else: ?>
                            <div class="badge-grid">
                                <?php foreach ($recentBadges as $badge): ?>
                                    <div class="badge-item">
                                        <div class="badge-icon">🏆</div>
                                        <div class="badge-name"><?php echo e($badge['badge_name']); ?></div>
                                        <div class="badge-user"><?php echo e($badge['full_name']); ?></div>
                                        <div class="badge-date"><?php echo timeAgo($badge['earned_at']); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Level Distribution Chart -->
                <?php if (!empty($levelDistribution)): ?>
                <div class="dashboard-card" style="margin-top: 32px;">
                    <div class="card-header">
                        <h3><i class="fas fa-chart-bar"></i> Level Distribution</h3>
                    </div>
                    <div class="card-body">
                        <canvas id="levelChart" style="max-height: 300px;"></canvas>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="../assets/js/theme.js"></script>
    <?php if (!empty($levelDistribution)): ?>
    <script>
        const ctx = document.getElementById('levelChart').getContext('2d');
        const levelChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: [<?php echo implode(',', array_map(fn($d) => '"Level ' . $d['level'] . '"', $levelDistribution)); ?>],
                datasets: [{
                    label: 'Number of Users',
                    data: [<?php echo implode(',', array_column($levelDistribution, 'count')); ?>],
                    backgroundColor: 'rgba(102, 126, 234, 0.5)',
                    borderColor: 'rgba(102, 126, 234, 1)',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>
