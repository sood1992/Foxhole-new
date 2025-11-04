<?php
/**
 * Gamification Helper Functions
 * Amazing Marvin-inspired productivity features
 */

/**
 * Award points to a user for completing a task
 */
function awardTaskCompletionPoints($db, $userId, $taskId, $task) {
    // Base points for completing a task
    $points = 10;

    // Bonus points based on priority
    $priorityBonus = [
        'low' => 0,
        'medium' => 5,
        'high' => 10,
        'urgent' => 15
    ];
    $points += $priorityBonus[$task['priority']] ?? 0;

    // Bonus for early completion
    if (isset($task['due_date']) && $task['due_date']) {
        $dueDate = strtotime($task['due_date']);
        $completedDate = time();

        if ($completedDate < $dueDate) {
            $daysEarly = ceil(($dueDate - $completedDate) / (60 * 60 * 24));
            $earlyBonus = min($daysEarly * 5, 25); // Max 25 bonus points
            $points += $earlyBonus;

            // Update early completion count
            $stmt = $db->prepare("
                UPDATE user_points
                SET tasks_completed_early = tasks_completed_early + 1
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
        } elseif ($completedDate <= $dueDate + (60 * 60 * 24)) {
            // On time completion (within 24 hours)
            $points += 5;

            $stmt = $db->prepare("
                UPDATE user_points
                SET tasks_completed_on_time = tasks_completed_on_time + 1
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
        } else {
            // Late completion
            $stmt = $db->prepare("
                UPDATE user_points
                SET tasks_completed_late = tasks_completed_late + 1
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
        }
    }

    // Add point transaction
    $stmt = $db->prepare("
        INSERT INTO point_transactions (user_id, points, reason, task_id)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$userId, $points, 'Task completed: ' . $task['task_name'], $taskId]);

    // Update user total points
    $stmt = $db->prepare("
        UPDATE user_points
        SET total_points = total_points + ?,
            last_activity_date = CURDATE()
        WHERE user_id = ?
    ");
    $stmt->execute([$points, $userId]);

    // Update streak
    updateUserStreak($db, $userId);

    // Check for new achievements
    checkAndAwardBadges($db, $userId);

    return $points;
}

/**
 * Update user's daily activity streak
 */
function updateUserStreak($db, $userId) {
    $stmt = $db->prepare("SELECT last_activity_date, streak_days FROM user_points WHERE user_id = ?");
    $stmt->execute([$userId]);
    $data = $stmt->fetch();

    if (!$data) {
        // Initialize user points
        $stmt = $db->prepare("
            INSERT INTO user_points (user_id, total_points, streak_days, last_activity_date)
            VALUES (?, 0, 1, CURDATE())
        ");
        $stmt->execute([$userId]);
        return;
    }

    $lastActivity = $data['last_activity_date'];
    $currentStreak = $data['streak_days'];
    $today = date('Y-m-d');

    if ($lastActivity === $today) {
        // Already active today, no change
        return;
    }

    $lastActivityDate = new DateTime($lastActivity);
    $todayDate = new DateTime($today);
    $diff = $todayDate->diff($lastActivityDate)->days;

    if ($diff === 1) {
        // Consecutive day - increase streak
        $newStreak = $currentStreak + 1;
    } elseif ($diff > 1) {
        // Streak broken - reset to 1
        $newStreak = 1;
    } else {
        $newStreak = $currentStreak;
    }

    $stmt = $db->prepare("
        UPDATE user_points
        SET streak_days = ?,
            last_activity_date = CURDATE()
        WHERE user_id = ?
    ");
    $stmt->execute([$newStreak, $userId]);
}

/**
 * Check and award badges based on user achievements
 */
function checkAndAwardBadges($db, $userId) {
    // Get user stats
    $stmt = $db->prepare("SELECT * FROM user_points WHERE user_id = ?");
    $stmt->execute([$userId]);
    $userPoints = $stmt->fetch();

    if (!$userPoints) {
        return [];
    }

    // Get user's completed tasks count
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM tasks WHERE assigned_to = ? AND status = 'completed'");
    $stmt->execute([$userId]);
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
        $userId,
        $completedCount,
        $userPoints['streak_days'],
        $userPoints['tasks_completed_early']
    ]);
    $eligibleBadges = $stmt->fetchAll();

    $newBadges = [];
    foreach ($eligibleBadges as $badge) {
        // Award badge
        $stmt = $db->prepare("INSERT INTO user_badges (user_id, badge_id) VALUES (?, ?)");
        $stmt->execute([$userId, $badge['id']]);

        // Award bonus points
        if ($badge['points_required'] > 0) {
            $stmt = $db->prepare("
                INSERT INTO point_transactions (user_id, points, reason)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$userId, $badge['points_required'], 'Badge earned: ' . $badge['name']]);

            $stmt = $db->prepare("
                UPDATE user_points
                SET total_points = total_points + ?
                WHERE user_id = ?
            ");
            $stmt->execute([$badge['points_required'], $userId]);
        }

        $newBadges[] = $badge;
    }

    return $newBadges;
}

/**
 * Calculate productivity score (0-100) based on various metrics
 * Amazing Marvin-inspired scoring system
 */
function calculateProductivityScore($db, $userId, $period = 'week') {
    $score = 0;

    // Date range based on period
    switch ($period) {
        case 'today':
            $startDate = date('Y-m-d');
            break;
        case 'week':
            $startDate = date('Y-m-d', strtotime('monday this week'));
            break;
        case 'month':
            $startDate = date('Y-m-01');
            break;
        default:
            $startDate = date('Y-m-d', strtotime('monday this week'));
    }

    // 1. Task completion rate (40 points max)
    $stmt = $db->prepare("
        SELECT
            COUNT(*) as total_tasks,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_tasks
        FROM tasks
        WHERE assigned_to = ?
        AND created_at >= ?
    ");
    $stmt->execute([$userId, $startDate]);
    $taskData = $stmt->fetch();

    if ($taskData['total_tasks'] > 0) {
        $completionRate = $taskData['completed_tasks'] / $taskData['total_tasks'];
        $score += $completionRate * 40;
    }

    // 2. On-time completion rate (30 points max)
    $stmt = $db->prepare("SELECT tasks_completed_early, tasks_completed_on_time, tasks_completed_late FROM user_points WHERE user_id = ?");
    $stmt->execute([$userId]);
    $timelinessData = $stmt->fetch();

    if ($timelinessData) {
        $totalCompleted = $timelinessData['tasks_completed_early'] + $timelinessData['tasks_completed_on_time'] + $timelinessData['tasks_completed_late'];
        if ($totalCompleted > 0) {
            $onTimeRate = ($timelinessData['tasks_completed_early'] + $timelinessData['tasks_completed_on_time']) / $totalCompleted;
            $score += $onTimeRate * 30;
        }
    }

    // 3. Streak bonus (15 points max)
    $stmt = $db->prepare("SELECT streak_days FROM user_points WHERE user_id = ?");
    $stmt->execute([$userId]);
    $streakData = $stmt->fetch();

    if ($streakData) {
        $streakBonus = min($streakData['streak_days'] / 30 * 15, 15); // Max 15 points for 30-day streak
        $score += $streakBonus;
    }

    // 4. Activity level (15 points max)
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT DATE(start_time)) as active_days
        FROM time_logs
        WHERE user_id = ?
        AND start_time >= ?
    ");
    $stmt->execute([$userId, $startDate]);
    $activityData = $stmt->fetch();

    $expectedDays = ($period === 'week') ? 5 : (($period === 'month') ? 20 : 1); // Weekdays
    if ($activityData['active_days'] > 0) {
        $activityRate = min($activityData['active_days'] / $expectedDays, 1);
        $score += $activityRate * 15;
    }

    return round($score, 1);
}

/**
 * Get task priority using Eisenhower Matrix
 * Returns: urgent-important, urgent-not-important, not-urgent-important, not-urgent-not-important
 */
function getEisenhowerQuadrant($task) {
    $isUrgent = false;
    $isImportant = false;

    // Determine urgency based on due date
    if (isset($task['due_date']) && $task['due_date']) {
        $dueDate = strtotime($task['due_date']);
        $now = time();
        $daysUntilDue = ($dueDate - $now) / (60 * 60 * 24);

        // Urgent if due within 3 days or overdue
        $isUrgent = ($daysUntilDue <= 3);
    }

    // Determine importance based on priority
    $isImportant = in_array($task['priority'], ['high', 'urgent']);

    // Return quadrant
    if ($isUrgent && $isImportant) {
        return ['quadrant' => 'urgent-important', 'label' => 'Do First', 'color' => '#ef4444'];
    } elseif ($isUrgent && !$isImportant) {
        return ['quadrant' => 'urgent-not-important', 'label' => 'Schedule', 'color' => '#f59e0b'];
    } elseif (!$isUrgent && $isImportant) {
        return ['quadrant' => 'not-urgent-important', 'label' => 'Decide When', 'color' => '#3b82f6'];
    } else {
        return ['quadrant' => 'not-urgent-not-important', 'label' => 'Delegate/Eliminate', 'color' => '#6b7280'];
    }
}
?>
