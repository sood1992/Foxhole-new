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
        case 'start':
            $taskId = $input['task_id'] ?? null;
            $sessionType = $input['session_type'] ?? 'work';
            $duration = $input['duration_minutes'] ?? 25;

            $stmt = $db->prepare("
                INSERT INTO pomodoro_sessions (user_id, task_id, session_type, duration_minutes, start_time)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$userId, $taskId, $sessionType, $duration]);

            echo json_encode(['success' => true, 'session_id' => $db->lastInsertId()]);
            break;

        case 'complete':
            $stmt = $db->prepare("
                UPDATE pomodoro_sessions
                SET end_time = NOW(), completed = 1
                WHERE user_id = ?
                AND end_time IS NULL
                ORDER BY start_time DESC
                LIMIT 1
            ");
            $stmt->execute([$userId]);

            echo json_encode(['success' => true]);
            break;

        case 'get_today_stats':
            $stmt = $db->prepare("
                SELECT
                    COUNT(*) as total_sessions,
                    SUM(CASE WHEN completed = 1 THEN duration_minutes ELSE 0 END) / 60 as total_hours
                FROM pomodoro_sessions
                WHERE user_id = ? AND DATE(start_time) = CURDATE()
            ");
            $stmt->execute([$userId]);
            $stats = $stmt->fetch();

            $stmt = $db->prepare("SELECT streak_days FROM user_points WHERE user_id = ?");
            $stmt->execute([$userId]);
            $streak = $stmt->fetch()['streak_days'] ?? 0;

            echo json_encode([
                'success' => true,
                'total_sessions' => $stats['total_sessions'] ?? 0,
                'total_hours' => round($stats['total_hours'] ?? 0, 1),
                'current_streak' => $streak
            ]);
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
