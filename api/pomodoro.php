<?php
require_once '../config/config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = getDBConnection();
$currentUser = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action === 'stats') {
        // Get today's pomodoro stats
        $stmt = $db->prepare("
            SELECT
                COUNT(*) as sessions_today,
                SUM(duration_minutes) as total_minutes_today
            FROM pomodoro_sessions
            WHERE user_id = ?
                AND DATE(started_at) = CURDATE()
                AND completed = 1
        ");
        $stmt->execute([$currentUser['id']]);
        $stats = $stmt->fetch();

        echo json_encode([
            'success' => true,
            'sessions_today' => $stats['sessions_today'] ?? 0,
            'total_minutes_today' => $stats['total_minutes_today'] ?? 0
        ]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    try {
        switch ($action) {
            case 'start':
                $sessionType = $input['session_type'] ?? 'work';
                $durationMinutes = intval($input['duration_minutes'] ?? 25);
                $taskId = intval($input['task_id'] ?? 0) ?: null;

                $stmt = $db->prepare("
                    INSERT INTO pomodoro_sessions (user_id, task_id, session_type, duration_minutes, started_at)
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$currentUser['id'], $taskId, $sessionType, $durationMinutes]);

                echo json_encode([
                    'success' => true,
                    'session_id' => $db->lastInsertId()
                ]);
                break;

            case 'complete':
                // Mark last session as completed
                $stmt = $db->prepare("
                    UPDATE pomodoro_sessions
                    SET completed = 1, completed_at = NOW()
                    WHERE user_id = ?
                        AND completed = 0
                    ORDER BY started_at DESC
                    LIMIT 1
                ");
                $stmt->execute([$currentUser['id']]);

                echo json_encode(['success' => true]);
                break;

            default:
                throw new Exception('Invalid action');
        }
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
