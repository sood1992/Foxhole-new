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
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'save_daily_plan':
            $today = date('Y-m-d');
            $morningIntention = $input['morning_intention'] ?? '';
            $top3Priorities = $input['top_3_priorities'] ?? '';

            // Check if plan exists for today
            $stmt = $db->prepare("SELECT id FROM daily_plans WHERE user_id = ? AND plan_date = ?");
            $stmt->execute([$userId, $today]);
            $existing = $stmt->fetch();

            if ($existing) {
                // Update existing plan
                $stmt = $db->prepare("
                    UPDATE daily_plans
                    SET morning_intention = ?, top_3_priorities = ?, updated_at = NOW()
                    WHERE user_id = ? AND plan_date = ?
                ");
                $stmt->execute([$morningIntention, $top3Priorities, $userId, $today]);
            } else {
                // Create new plan
                $stmt = $db->prepare("
                    INSERT INTO daily_plans (user_id, plan_date, morning_intention, top_3_priorities)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$userId, $today, $morningIntention, $top3Priorities]);
            }

            echo json_encode(['success' => true, 'message' => 'Daily plan saved successfully']);
            break;

        case 'create_goal':
            $goalTitle = $input['goal_title'] ?? '';
            $goalDescription = $input['goal_description'] ?? '';
            $goalType = $input['goal_type'] ?? 'weekly';
            $targetValue = $input['target_value'] ?? 0;
            $unit = $input['unit'] ?? '';
            $targetDate = $input['target_date'] ?? null;

            if (empty($goalTitle)) {
                throw new Exception('Goal title is required');
            }

            $stmt = $db->prepare("
                INSERT INTO goals (user_id, goal_title, goal_description, goal_type, target_value, current_value, unit, target_date)
                VALUES (?, ?, ?, ?, ?, 0, ?, ?)
            ");
            $stmt->execute([$userId, $goalTitle, $goalDescription, $goalType, $targetValue, $unit, $targetDate]);

            echo json_encode(['success' => true, 'goal_id' => $db->lastInsertId()]);
            break;

        case 'update_goal_progress':
            $goalId = $input['goal_id'] ?? 0;
            $currentValue = $input['current_value'] ?? 0;

            // Verify goal belongs to user
            $stmt = $db->prepare("SELECT id FROM goals WHERE id = ? AND user_id = ?");
            $stmt->execute([$goalId, $userId]);
            if (!$stmt->fetch()) {
                throw new Exception('Goal not found');
            }

            $stmt = $db->prepare("UPDATE goals SET current_value = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$currentValue, $goalId]);

            echo json_encode(['success' => true]);
            break;

        case 'save_weekly_review':
            $weekStartDate = $input['week_start_date'] ?? date('Y-m-d', strtotime('monday this week'));
            $wins = $input['wins'] ?? '';
            $challenges = $input['challenges'] ?? '';
            $lessonsLearned = $input['lessons_learned'] ?? '';
            $nextWeekGoals = $input['next_week_goals'] ?? '';
            $energyLevel = $input['energy_level'] ?? 5;
            $satisfactionScore = $input['satisfaction_score'] ?? 5;

            // Check if review exists for this week
            $stmt = $db->prepare("SELECT id FROM weekly_reviews WHERE user_id = ? AND week_start_date = ?");
            $stmt->execute([$userId, $weekStartDate]);
            $existing = $stmt->fetch();

            if ($existing) {
                $stmt = $db->prepare("
                    UPDATE weekly_reviews
                    SET wins = ?, challenges = ?, lessons_learned = ?, next_week_goals = ?,
                        energy_level = ?, satisfaction_score = ?, updated_at = NOW()
                    WHERE user_id = ? AND week_start_date = ?
                ");
                $stmt->execute([$wins, $challenges, $lessonsLearned, $nextWeekGoals, $energyLevel, $satisfactionScore, $userId, $weekStartDate]);
            } else {
                $stmt = $db->prepare("
                    INSERT INTO weekly_reviews (user_id, week_start_date, wins, challenges, lessons_learned, next_week_goals, energy_level, satisfaction_score)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$userId, $weekStartDate, $wins, $challenges, $lessonsLearned, $nextWeekGoals, $energyLevel, $satisfactionScore]);
            }

            echo json_encode(['success' => true]);
            break;

        case 'add_time_box':
            $taskId = $input['task_id'] ?? null;
            $startTime = $input['start_time'] ?? '';
            $endTime = $input['end_time'] ?? '';
            $description = $input['description'] ?? '';
            $boxDate = $input['box_date'] ?? date('Y-m-d');

            if (empty($startTime) || empty($endTime)) {
                throw new Exception('Start and end times are required');
            }

            $stmt = $db->prepare("
                INSERT INTO time_boxes (user_id, task_id, box_date, start_time, end_time, description)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$userId, $taskId, $boxDate, $startTime, $endTime, $description]);

            echo json_encode(['success' => true, 'box_id' => $db->lastInsertId()]);
            break;

        case 'get_time_boxes':
            $date = $input['date'] ?? $_GET['date'] ?? date('Y-m-d');

            $stmt = $db->prepare("
                SELECT tb.*, t.task_name, p.project_name
                FROM time_boxes tb
                LEFT JOIN tasks t ON tb.task_id = t.id
                LEFT JOIN projects p ON t.project_id = p.id
                WHERE tb.user_id = ? AND tb.box_date = ?
                ORDER BY tb.start_time
            ");
            $stmt->execute([$userId, $date]);
            $boxes = $stmt->fetchAll();

            echo json_encode(['success' => true, 'boxes' => $boxes]);
            break;

        case 'delete_time_box':
            $boxId = $input['box_id'] ?? 0;

            $stmt = $db->prepare("DELETE FROM time_boxes WHERE id = ? AND user_id = ?");
            $stmt->execute([$boxId, $userId]);

            echo json_encode(['success' => true]);
            break;

        case 'create_morning_ritual':
            $ritualName = $input['ritual_name'] ?? '';
            $ritualOrder = $input['ritual_order'] ?? 0;

            if (empty($ritualName)) {
                throw new Exception('Ritual name is required');
            }

            $stmt = $db->prepare("
                INSERT INTO morning_rituals (user_id, ritual_name, ritual_order, is_active)
                VALUES (?, ?, ?, 1)
            ");
            $stmt->execute([$userId, $ritualName, $ritualOrder]);

            echo json_encode(['success' => true, 'ritual_id' => $db->lastInsertId()]);
            break;

        case 'get_morning_rituals':
            $stmt = $db->prepare("
                SELECT mr.*,
                       (SELECT COUNT(*) FROM morning_ritual_completions
                        WHERE ritual_id = mr.id AND completion_date = CURDATE()) as completed_today
                FROM morning_rituals mr
                WHERE mr.user_id = ? AND mr.is_active = 1
                ORDER BY mr.ritual_order
            ");
            $stmt->execute([$userId]);
            $rituals = $stmt->fetchAll();

            echo json_encode(['success' => true, 'rituals' => $rituals]);
            break;

        case 'complete_morning_ritual':
            $ritualId = $input['ritual_id'] ?? 0;
            $today = date('Y-m-d');

            // Check if already completed today
            $stmt = $db->prepare("
                SELECT id FROM morning_ritual_completions
                WHERE ritual_id = ? AND completion_date = ?
            ");
            $stmt->execute([$ritualId, $today]);
            if ($stmt->fetch()) {
                throw new Exception('Ritual already completed today');
            }

            // Verify ritual belongs to user
            $stmt = $db->prepare("SELECT id FROM morning_rituals WHERE id = ? AND user_id = ?");
            $stmt->execute([$ritualId, $userId]);
            if (!$stmt->fetch()) {
                throw new Exception('Ritual not found');
            }

            $stmt = $db->prepare("
                INSERT INTO morning_ritual_completions (ritual_id, completion_date)
                VALUES (?, ?)
            ");
            $stmt->execute([$ritualId, $today]);

            echo json_encode(['success' => true]);
            break;

        case 'uncomplete_morning_ritual':
            $ritualId = $input['ritual_id'] ?? 0;
            $today = date('Y-m-d');

            $stmt = $db->prepare("
                DELETE FROM morning_ritual_completions
                WHERE ritual_id = ? AND completion_date = ?
            ");
            $stmt->execute([$ritualId, $today]);

            echo json_encode(['success' => true]);
            break;

        case 'delete_morning_ritual':
            $ritualId = $input['ritual_id'] ?? 0;

            $stmt = $db->prepare("DELETE FROM morning_rituals WHERE id = ? AND user_id = ?");
            $stmt->execute([$ritualId, $userId]);

            echo json_encode(['success' => true]);
            break;

        case 'add_task_dependency':
            $taskId = $input['task_id'] ?? 0;
            $dependsOnTaskId = $input['depends_on_task_id'] ?? 0;

            if ($taskId == $dependsOnTaskId) {
                throw new Exception('Task cannot depend on itself');
            }

            // Check for circular dependencies
            $stmt = $db->prepare("
                SELECT COUNT(*) as cnt FROM task_dependencies
                WHERE task_id = ? AND depends_on_task_id = ?
            ");
            $stmt->execute([$dependsOnTaskId, $taskId]);
            if ($stmt->fetch()['cnt'] > 0) {
                throw new Exception('Circular dependency detected');
            }

            $stmt = $db->prepare("
                INSERT INTO task_dependencies (task_id, depends_on_task_id)
                VALUES (?, ?)
            ");
            $stmt->execute([$taskId, $dependsOnTaskId]);

            echo json_encode(['success' => true]);
            break;

        case 'get_task_dependencies':
            $taskId = $input['task_id'] ?? $_GET['task_id'] ?? 0;

            $stmt = $db->prepare("
                SELECT td.*, t.task_name, t.status
                FROM task_dependencies td
                JOIN tasks t ON td.depends_on_task_id = t.id
                WHERE td.task_id = ?
            ");
            $stmt->execute([$taskId]);
            $dependencies = $stmt->fetchAll();

            echo json_encode(['success' => true, 'dependencies' => $dependencies]);
            break;

        case 'remove_task_dependency':
            $dependencyId = $input['dependency_id'] ?? 0;

            $stmt = $db->prepare("DELETE FROM task_dependencies WHERE id = ?");
            $stmt->execute([$dependencyId]);

            echo json_encode(['success' => true]);
            break;

        case 'update_break_settings':
            $reminderEnabled = $input['reminder_enabled'] ?? 0;
            $intervalMinutes = $input['interval_minutes'] ?? 60;
            $breakDuration = $input['break_duration_minutes'] ?? 5;

            // Check if settings exist
            $stmt = $db->prepare("SELECT id FROM break_reminder_settings WHERE user_id = ?");
            $stmt->execute([$userId]);
            $existing = $stmt->fetch();

            if ($existing) {
                $stmt = $db->prepare("
                    UPDATE break_reminder_settings
                    SET reminder_enabled = ?, interval_minutes = ?, break_duration_minutes = ?
                    WHERE user_id = ?
                ");
                $stmt->execute([$reminderEnabled, $intervalMinutes, $breakDuration, $userId]);
            } else {
                $stmt = $db->prepare("
                    INSERT INTO break_reminder_settings (user_id, reminder_enabled, interval_minutes, break_duration_minutes)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$userId, $reminderEnabled, $intervalMinutes, $breakDuration]);
            }

            echo json_encode(['success' => true]);
            break;

        case 'get_break_settings':
            $stmt = $db->prepare("SELECT * FROM break_reminder_settings WHERE user_id = ?");
            $stmt->execute([$userId]);
            $settings = $stmt->fetch();

            if (!$settings) {
                $settings = [
                    'reminder_enabled' => 0,
                    'interval_minutes' => 60,
                    'break_duration_minutes' => 5
                ];
            }

            echo json_encode(['success' => true, 'settings' => $settings]);
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
