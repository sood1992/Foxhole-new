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
$currentUser = getCurrentUser();
$method = $_SERVER['REQUEST_METHOD'];

try {
    // GET: Retrieve notifications
    if ($method === 'GET') {
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
        $unreadOnly = isset($_GET['unread_only']) && $_GET['unread_only'] === 'true';

        $sql = "
            SELECT n.*,
                   u.full_name as actor_name
            FROM notifications n
            LEFT JOIN users u ON n.related_id = u.id AND n.type = 'mention'
            WHERE n.user_id = ?
        ";

        if ($unreadOnly) {
            $sql .= " AND n.is_read = 0";
        }

        $sql .= " ORDER BY n.created_at DESC LIMIT ?";

        $stmt = $db->prepare($sql);
        $stmt->execute([$currentUser['id'], $limit]);
        $notifications = $stmt->fetchAll();

        // Get unread count
        $unreadStmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
        $unreadStmt->execute([$currentUser['id']]);
        $unreadCount = $unreadStmt->fetch()['count'];

        echo json_encode([
            'success' => true,
            'notifications' => $notifications,
            'unread_count' => $unreadCount
        ]);
    }

    // POST: Mark as read
    elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $action = $data['action'] ?? '';

        if ($action === 'mark_read') {
            $notificationId = $data['notification_id'] ?? 0;

            $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
            $stmt->execute([$notificationId, $currentUser['id']]);

            echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
        }
        elseif ($action === 'mark_all_read') {
            $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
            $stmt->execute([$currentUser['id']]);

            echo json_encode(['success' => true, 'message' => 'All notifications marked as read']);
        }
        elseif ($action === 'create') {
            // Create notification (used by system)
            $userId = $data['user_id'] ?? 0;
            $title = $data['title'] ?? '';
            $message = $data['message'] ?? '';
            $type = $data['type'] ?? 'system';
            $relatedType = $data['related_type'] ?? null;
            $relatedId = $data['related_id'] ?? null;

            $stmt = $db->prepare("
                INSERT INTO notifications (user_id, title, message, type, related_type, related_id)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$userId, $title, $message, $type, $relatedType, $relatedId]);

            echo json_encode(['success' => true, 'message' => 'Notification created']);
        }
    }

    // DELETE: Remove notification
    elseif ($method === 'DELETE') {
        $data = json_decode(file_get_contents('php://input'), true);
        $notificationId = $data['notification_id'] ?? 0;

        $stmt = $db->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
        $stmt->execute([$notificationId, $currentUser['id']]);

        echo json_encode(['success' => true, 'message' => 'Notification deleted']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
