<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = getDBConnection();
$userId = $_SESSION['user_id'];

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

try {
    switch ($action) {
        case 'get_user_stats':
            // Get user points and stats
            $stmt = $db->prepare("SELECT * FROM user_points WHERE user_id = ?");
            $stmt->execute([$userId]);
            $userPoints = $stmt->fetch();

            if (!$userPoints) {
                // Initialize points for new user
                $stmt = $db->prepare("
                    INSERT INTO user_points (user_id, total_points, streak_days, last_activity_date)
                    VALUES (?, 0, 0, CURDATE())
                ");
                $stmt->execute([$userId]);

                $stmt = $db->prepare("SELECT * FROM user_points WHERE user_id = ?");
                $stmt->execute([$userId]);
                $userPoints = $stmt->fetch();
            }

            // Get user badges
            $stmt = $db->prepare("
                SELECT b.*, ub.earned_at
                FROM user_badges ub
                JOIN badges b ON ub.badge_id = b.id
                WHERE ub.user_id = ?
                ORDER BY ub.earned_at DESC
            ");
            $stmt->execute([$userId]);
            $earnedBadges = $stmt->fetchAll();

            // Get available badges
            $stmt = $db->prepare("
                SELECT b.*,
                       CASE WHEN ub.id IS NOT NULL THEN 1 ELSE 0 END as earned
                FROM badges b
                LEFT JOIN user_badges ub ON b.id = ub.badge_id AND ub.user_id = ?
                ORDER BY b.type, b.points_required
            ");
            $stmt->execute([$userId]);
            $allBadges = $stmt->fetchAll();

            echo json_encode([
                'success' => true,
                'user_points' => $userPoints,
                'earned_badges' => $earnedBadges,
                'all_badges' => $allBadges
            ]);
            break;

        case 'get_leaderboard':
            // Get leaderboard
            $period = $input['period'] ?? 'all_time'; // all_time, month, week

            $query = "
                SELECT
                    u.id,
                    u.full_name,
                    u.avatar,
                    up.total_points,
                    up.streak_days,
                    COUNT(DISTINCT ub.badge_id) as badge_count,
                    COUNT(DISTINCT t.id) as completed_tasks
                FROM users u
                LEFT JOIN user_points up ON u.id = up.user_id
                LEFT JOIN user_badges ub ON u.id = ub.user_id
                LEFT JOIN tasks t ON u.id = t.assigned_to AND t.status = 'completed'
                WHERE u.role IN ('employee', 'manager')
                GROUP BY u.id
                ORDER BY up.total_points DESC, up.streak_days DESC
                LIMIT 20
            ";

            $stmt = $db->prepare($query);
            $stmt->execute();
            $leaderboard = $stmt->fetchAll();

            echo json_encode([
                'success' => true,
                'leaderboard' => $leaderboard,
                'current_user_id' => $userId
            ]);
            break;

        case 'award_points':
            // Award points to user (admin/manager only)
            if (!hasRole(['admin', 'manager'])) {
                throw new Exception('Insufficient permissions');
            }

            $targetUserId = $input['user_id'] ?? null;
            $points = $input['points'] ?? 0;
            $reason = $input['reason'] ?? 'Manual award';
            $taskId = $input['task_id'] ?? null;

            if (!$targetUserId || !$points) {
                throw new Exception('User ID and points are required');
            }

            // Add points transaction
            $stmt = $db->prepare("
                INSERT INTO point_transactions (user_id, points, reason, task_id)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$targetUserId, $points, $reason, $taskId]);

            // Update user total points
            $stmt = $db->prepare("
                UPDATE user_points
                SET total_points = total_points + ?,
                    last_activity_date = CURDATE()
                WHERE user_id = ?
            ");
            $stmt->execute([$points, $targetUserId]);

            echo json_encode([
                'success' => true,
                'message' => 'Points awarded successfully'
            ]);
            break;

        case 'check_achievements':
            // Check and award achievements for a user
            $targetUserId = $input['user_id'] ?? $userId;

            // Get user stats
            $stmt = $db->prepare("SELECT * FROM user_points WHERE user_id = ?");
            $stmt->execute([$targetUserId]);
            $userPoints = $stmt->fetch();

            if (!$userPoints) {
                echo json_encode(['success' => true, 'new_badges' => []]);
                break;
            }

            // Get user's completed tasks count
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM tasks WHERE assigned_to = ? AND status = 'completed'");
            $stmt->execute([$targetUserId]);
            $completedCount = $stmt->fetch()['count'];

            // Check for badge eligibility
            $stmt = $db->prepare("
                SELECT b.*
                FROM badges b
                LEFT JOIN user_badges ub ON b.id = ub.badge_id AND ub.user_id = ?
                WHERE ub.id IS NULL
                AND (
                    (b.criteria_type = 'tasks_completed' AND ? >= b.criteria_value)
                    OR (b.criteria_type = 'streak' AND ? >= b.criteria_value)
                    OR (b.criteria_type = 'early_completion' AND ? >= b.criteria_value)
                )
            ");
            $stmt->execute([
                $targetUserId,
                $completedCount,
                $userPoints['streak_days'],
                $userPoints['tasks_completed_early']
            ]);
            $eligibleBadges = $stmt->fetchAll();

            $newBadges = [];
            foreach ($eligibleBadges as $badge) {
                // Award badge
                $stmt = $db->prepare("INSERT INTO user_badges (user_id, badge_id) VALUES (?, ?)");
                $stmt->execute([$targetUserId, $badge['id']]);

                // Award points
                if ($badge['points_required'] > 0) {
                    $stmt = $db->prepare("
                        INSERT INTO point_transactions (user_id, points, reason)
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([$targetUserId, $badge['points_required'], 'Badge earned: ' . $badge['name']]);

                    $stmt = $db->prepare("
                        UPDATE user_points
                        SET total_points = total_points + ?
                        WHERE user_id = ?
                    ");
                    $stmt->execute([$badge['points_required'], $targetUserId]);
                }

                $newBadges[] = $badge;
            }

            echo json_encode([
                'success' => true,
                'new_badges' => $newBadges
            ]);
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
