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
    // GET: Retrieve messages
    if ($method === 'GET') {
        $channelType = $_GET['channel_type'] ?? 'team';
        $channelId = isset($_GET['channel_id']) ? intval($_GET['channel_id']) : null;
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
        $since = isset($_GET['since']) ? $_GET['since'] : null;

        $sql = "
            SELECT cm.*,
                   u.full_name as sender_name,
                   u.job_title as sender_title
            FROM chat_messages cm
            JOIN users u ON cm.sender_id = u.id
            WHERE cm.channel_type = ?
        ";

        $params = [$channelType];

        if ($channelId !== null) {
            $sql .= " AND cm.channel_id = ?";
            $params[] = $channelId;
        } else {
            $sql .= " AND cm.channel_id IS NULL";
        }

        if ($since) {
            $sql .= " AND cm.created_at > ?";
            $params[] = $since;
        }

        $sql .= " ORDER BY cm.created_at DESC LIMIT ?";
        $params[] = $limit;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $messages = array_reverse($stmt->fetchAll());

        echo json_encode([
            'success' => true,
            'messages' => $messages
        ]);
    }

    // POST: Send message
    elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $message = trim($data['message'] ?? '');
        $channelType = $data['channel_type'] ?? 'team';
        $channelId = isset($data['channel_id']) ? intval($data['channel_id']) : null;

        if (empty($message)) {
            throw new Exception('Message cannot be empty');
        }

        $stmt = $db->prepare("
            INSERT INTO chat_messages (sender_id, channel_type, channel_id, message)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$currentUser['id'], $channelType, $channelId, $message]);

        $messageId = $db->lastInsertId();

        // Get the created message
        $stmt = $db->prepare("
            SELECT cm.*,
                   u.full_name as sender_name,
                   u.job_title as sender_title
            FROM chat_messages cm
            JOIN users u ON cm.sender_id = u.id
            WHERE cm.id = ?
        ");
        $stmt->execute([$messageId]);
        $newMessage = $stmt->fetch();

        echo json_encode([
            'success' => true,
            'message' => $newMessage
        ]);
    }

    // PUT: Edit message
    elseif ($method === 'PUT') {
        $data = json_decode(file_get_contents('php://input'), true);
        $messageId = $data['message_id'] ?? 0;
        $newMessage = trim($data['message'] ?? '');

        if (empty($newMessage)) {
            throw new Exception('Message cannot be empty');
        }

        // Verify ownership
        $stmt = $db->prepare("SELECT sender_id FROM chat_messages WHERE id = ?");
        $stmt->execute([$messageId]);
        $msg = $stmt->fetch();

        if (!$msg || $msg['sender_id'] != $currentUser['id']) {
            throw new Exception('Unauthorized to edit this message');
        }

        $stmt = $db->prepare("
            UPDATE chat_messages
            SET message = ?, is_edited = 1
            WHERE id = ? AND sender_id = ?
        ");
        $stmt->execute([$newMessage, $messageId, $currentUser['id']]);

        echo json_encode(['success' => true, 'message' => 'Message updated']);
    }

    // DELETE: Delete message
    elseif ($method === 'DELETE') {
        $data = json_decode(file_get_contents('php://input'), true);
        $messageId = $data['message_id'] ?? 0;

        // Verify ownership
        $stmt = $db->prepare("SELECT sender_id FROM chat_messages WHERE id = ?");
        $stmt->execute([$messageId]);
        $msg = $stmt->fetch();

        if (!$msg || $msg['sender_id'] != $currentUser['id']) {
            throw new Exception('Unauthorized to delete this message');
        }

        $stmt = $db->prepare("DELETE FROM chat_messages WHERE id = ? AND sender_id = ?");
        $stmt->execute([$messageId, $currentUser['id']]);

        echo json_encode(['success' => true, 'message' => 'Message deleted']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
