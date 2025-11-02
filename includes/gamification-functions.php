<?php
/**
 * Gamification Helper Functions
 * Award points, update streaks, grant badges
 */

// Award points to user
function awardPoints($userId, $points, $reason = '') {
    $db = getDBConnection();

    // Get or create user points record
    $stmt = $db->prepare("
        INSERT INTO user_points (user_id, points, last_activity_date)
        VALUES (?, ?, CURDATE())
        ON DUPLICATE KEY UPDATE
            points = points + ?,
            last_activity_date = CURDATE()
    ");
    $stmt->execute([$userId, $points, $points]);

    // Update level (every 100 points = 1 level)
    $updateLevel = $db->prepare("
        UPDATE user_points
        SET level = FLOOR(points / 100) + 1
        WHERE user_id = ?
    ");
    $updateLevel->execute([$userId]);

    // Log the points award
    error_log("Points awarded: User $userId got $points points for: $reason");

    return true;
}

// Update streak when task completed
function updateStreak($userId) {
    $db = getDBConnection();

    $stmt = $db->prepare("SELECT last_activity_date, current_streak, longest_streak FROM user_points WHERE user_id = ?");
    $stmt->execute([$userId]);
    $data = $stmt->fetch();

    if (!$data) {
        return;
    }

    $lastDate = $data['last_activity_date'];
    $currentStreak = $data['current_streak'];
    $longestStreak = $data['longest_streak'];

    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));

    // If last activity was yesterday, increment streak
    if ($lastDate == $yesterday) {
        $currentStreak++;
    }
    // If last activity was today, don't change streak
    elseif ($lastDate == $today) {
        // Do nothing
        return;
    }
    // If last activity was before yesterday, reset streak
    else {
        $currentStreak = 1;
    }

    // Update longest streak if current is higher
    if ($currentStreak > $longestStreak) {
        $longestStreak = $currentStreak;
    }

    // Update database
    $update = $db->prepare("
        UPDATE user_points
        SET current_streak = ?,
            longest_streak = ?,
            last_activity_date = CURDATE()
        WHERE user_id = ?
    ");
    $update->execute([$currentStreak, $longestStreak, $userId]);

    // Award badge for milestones
    if ($currentStreak == 7) {
        awardBadge($userId, 'streak_7', '🔥 Week Warrior', 'Completed tasks 7 days in a row!');
    } elseif ($currentStreak == 30) {
        awardBadge($userId, 'streak_30', '🔥🔥 Month Master', 'Completed tasks 30 days in a row!');
    } elseif ($currentStreak == 100) {
        awardBadge($userId, 'streak_100', '🔥🔥🔥 Century Streak', '100 days of consistency!');
    }
}

// Award badge to user
function awardBadge($userId, $badgeType, $badgeName, $description) {
    $db = getDBConnection();

    // Check if already has this badge
    $check = $db->prepare("SELECT id FROM user_badges WHERE user_id = ? AND badge_type = ?");
    $check->execute([$userId, $badgeType]);

    if ($check->fetch()) {
        return false; // Already has badge
    }

    // Award badge
    $stmt = $db->prepare("
        INSERT INTO user_badges (user_id, badge_type, badge_name, badge_description)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$userId, $badgeType, $badgeName, $description]);

    return true;
}

// Get user's gamification stats
function getUserGamificationStats($userId) {
    $db = getDBConnection();

    $stmt = $db->prepare("
        SELECT points, level, current_streak, longest_streak, total_tasks_completed
        FROM user_points
        WHERE user_id = ?
    ");
    $stmt->execute([$userId]);
    $stats = $stmt->fetch();

    if (!$stats) {
        // Initialize if doesn't exist
        $init = $db->prepare("INSERT INTO user_points (user_id) VALUES (?)");
        $init->execute([$userId]);

        return [
            'points' => 0,
            'level' => 1,
            'current_streak' => 0,
            'longest_streak' => 0,
            'total_tasks_completed' => 0
        ];
    }

    return $stats;
}

// Get user's badges
function getUserBadges($userId) {
    $db = getDBConnection();

    $stmt = $db->prepare("
        SELECT badge_type, badge_name, badge_description, earned_at
        FROM user_badges
        WHERE user_id = ?
        ORDER BY earned_at DESC
    ");
    $stmt->execute([$userId]);

    return $stmt->fetchAll();
}

// Get leaderboard
function getLeaderboard($limit = 10) {
    $db = getDBConnection();

    $stmt = $db->prepare("
        SELECT u.id, u.full_name, u.job_title, u.avatar,
               up.points, up.level, up.current_streak, up.total_tasks_completed
        FROM users u
        JOIN user_points up ON u.id = up.user_id
        WHERE u.is_active = 1
        ORDER BY up.points DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);

    return $stmt->fetchAll();
}

// Process task completion (award points and update streak)
function processTaskCompletion($taskId, $userId) {
    $db = getDBConnection();

    // Get task details
    $stmt = $db->prepare("SELECT priority, due_date, status FROM tasks WHERE id = ?");
    $stmt->execute([$taskId]);
    $task = $stmt->fetch();

    if (!$task || $task['status'] !== 'completed') {
        return;
    }

    // Base points
    $points = 10;

    // Bonus for priority
    $priorityBonus = [
        'low' => 0,
        'medium' => 5,
        'high' => 10,
        'urgent' => 15
    ];
    $points += $priorityBonus[$task['priority']] ?? 0;

    // Bonus for completing on time
    if ($task['due_date'] && strtotime($task['due_date']) >= strtotime('today')) {
        $points += 5;
    }

    // Award points
    awardPoints($userId, $points, "Completed task #$taskId");

    // Update streak
    updateStreak($userId);

    // Increment total tasks completed
    $db->prepare("
        UPDATE user_points
        SET total_tasks_completed = total_tasks_completed + 1
        WHERE user_id = ?
    ")->execute([$userId]);

    // Check for achievement badges
    checkAchievements($userId);
}

// Check and award achievement badges
function checkAchievements($userId) {
    $db = getDBConnection();

    // Get user stats
    $stats = getUserGamificationStats($userId);

    // First task badge
    if ($stats['total_tasks_completed'] == 1) {
        awardBadge($userId, 'first_task', '🎯 First Steps', 'Completed your first task!');
    }

    // 10 tasks badge
    if ($stats['total_tasks_completed'] == 10) {
        awardBadge($userId, 'tasks_10', '⭐ Getting Started', 'Completed 10 tasks!');
    }

    // 50 tasks badge
    if ($stats['total_tasks_completed'] == 50) {
        awardBadge($userId, 'tasks_50', '🌟 Productive', 'Completed 50 tasks!');
    }

    // 100 tasks badge
    if ($stats['total_tasks_completed'] == 100) {
        awardBadge($userId, 'tasks_100', '💫 Century', 'Completed 100 tasks!');
    }

    // 500 tasks badge
    if ($stats['total_tasks_completed'] == 500) {
        awardBadge($userId, 'tasks_500', '🏆 Legend', 'Completed 500 tasks!');
    }

    // Level milestones
    if ($stats['level'] == 5) {
        awardBadge($userId, 'level_5', '📈 Level 5', 'Reached level 5!');
    }

    if ($stats['level'] == 10) {
        awardBadge($userId, 'level_10', '🚀 Level 10', 'Reached level 10!');
    }

    // Check for "Speed Demon" - 10 tasks in one day
    $todayTasks = $db->prepare("
        SELECT COUNT(*) as count
        FROM tasks
        WHERE assigned_to = ?
            AND status = 'completed'
            AND DATE(completed_date) = CURDATE()
    ");
    $todayTasks->execute([$userId]);
    $todayCount = $todayTasks->fetch()['count'];

    if ($todayCount >= 10) {
        awardBadge($userId, 'speed_demon', '⚡ Speed Demon', 'Completed 10 tasks in one day!');
    }
}
