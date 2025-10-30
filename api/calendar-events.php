<?php
/**
 * Calendar Events API
 * Shoot schedule, edits, reviews, meetings
 */

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
$userRole = $_SESSION['role'];

// GET - Fetch calendar events
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $start = $_GET['start'] ?? null;
    $end = $_GET['end'] ?? null;
    $projectId = $_GET['project_id'] ?? null;
    $eventType = $_GET['event_type'] ?? null;

    $query = "
        SELECT ce.*,
               p.project_name,
               t.task_name,
               u.full_name as created_by_name
        FROM calendar_events ce
        LEFT JOIN projects p ON ce.project_id = p.id
        LEFT JOIN tasks t ON ce.task_id = t.id
        JOIN users u ON ce.created_by = u.id
        WHERE 1=1
    ";

    $params = [];

    // Filter by date range
    if ($start && $end) {
        $query .= " AND ce.start_datetime >= ? AND ce.start_datetime <= ?";
        $params[] = $start;
        $params[] = $end;
    }

    // Filter by project
    if ($projectId) {
        $query .= " AND ce.project_id = ?";
        $params[] = $projectId;
    }

    // Filter by event type
    if ($eventType) {
        $query .= " AND ce.event_type = ?";
        $params[] = $eventType;
    }

    // Filter by user access for employees
    if ($userRole === 'employee') {
        $query .= " AND (FIND_IN_SET(?, ce.assigned_users) OR ce.created_by = ? OR ce.assigned_users IS NULL OR ce.assigned_users = '')";
        $params[] = $userId;
        $params[] = $userId;
    }

    $query .= " ORDER BY ce.start_datetime ASC";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format for FullCalendar
    $formattedEvents = [];
    foreach ($events as $event) {
        $formattedEvents[] = [
            'id' => $event['id'],
            'title' => $event['event_title'],
            'start' => $event['start_datetime'],
            'end' => $event['end_datetime'],
            'allDay' => (bool)$event['all_day'],
            'backgroundColor' => $event['color'],
            'borderColor' => $event['color'],
            'extendedProps' => [
                'type' => $event['event_type'],
                'description' => $event['description'],
                'location' => $event['location'],
                'project_id' => $event['project_id'],
                'project_name' => $event['project_name'],
                'task_id' => $event['task_id'],
                'task_name' => $event['task_name'],
                'assigned_users' => $event['assigned_users'],
                'equipment_needed' => $event['equipment_needed'],
                'notes' => $event['notes'],
                'status' => $event['status'],
                'created_by' => $event['created_by_name']
            ]
        ];
    }

    echo json_encode(['success' => true, 'events' => $formattedEvents]);
    exit;
}

// POST - Create new event
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    $eventType = $input['event_type'] ?? 'other';
    $eventTitle = $input['event_title'] ?? null;
    $startDatetime = $input['start_datetime'] ?? null;
    $endDatetime = $input['end_datetime'] ?? null;
    $projectId = $input['project_id'] ?? null;
    $taskId = $input['task_id'] ?? null;
    $description = $input['description'] ?? null;
    $location = $input['location'] ?? null;
    $assignedUsers = $input['assigned_users'] ?? null; // Comma-separated IDs
    $equipmentNeeded = $input['equipment_needed'] ?? null;
    $notes = $input['notes'] ?? null;
    $allDay = $input['all_day'] ?? 0;

    if (!$eventTitle || !$startDatetime || !$endDatetime) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Title, start, and end datetime are required']);
        exit;
    }

    // Get default color for event type
    $stmt = $db->prepare("SELECT color FROM event_type_colors WHERE event_type = ?");
    $stmt->execute([$eventType]);
    $colorResult = $stmt->fetch(PDO::FETCH_ASSOC);
    $color = $colorResult['color'] ?? '#1990ff';

    try {
        $stmt = $db->prepare("
            INSERT INTO calendar_events
            (project_id, task_id, event_type, event_title, description, location,
             start_datetime, end_datetime, all_day, color, assigned_users,
             equipment_needed, notes, created_by, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'scheduled')
        ");

        $stmt->execute([
            $projectId,
            $taskId,
            $eventType,
            $eventTitle,
            $description,
            $location,
            $startDatetime,
            $endDatetime,
            $allDay,
            $color,
            $assignedUsers,
            $equipmentNeeded,
            $notes,
            $userId
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Event created successfully',
            'event_id' => $db->lastInsertId()
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// PUT - Update event
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    $eventId = $input['event_id'] ?? null;

    if (!$eventId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Event ID required']);
        exit;
    }

    // Check permission
    $stmt = $db->prepare("SELECT created_by FROM calendar_events WHERE id = ?");
    $stmt->execute([$eventId]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Event not found']);
        exit;
    }

    // Only creator or admin/manager can edit
    if ($event['created_by'] != $userId && !in_array($userRole, ['admin', 'manager'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        exit;
    }

    $updates = [];
    $params = [];

    if (isset($input['event_title'])) {
        $updates[] = "event_title = ?";
        $params[] = $input['event_title'];
    }
    if (isset($input['event_type'])) {
        $updates[] = "event_type = ?";
        $params[] = $input['event_type'];

        // Update color based on type
        $stmt = $db->prepare("SELECT color FROM event_type_colors WHERE event_type = ?");
        $stmt->execute([$input['event_type']]);
        $colorResult = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($colorResult) {
            $updates[] = "color = ?";
            $params[] = $colorResult['color'];
        }
    }
    if (isset($input['description'])) {
        $updates[] = "description = ?";
        $params[] = $input['description'];
    }
    if (isset($input['location'])) {
        $updates[] = "location = ?";
        $params[] = $input['location'];
    }
    if (isset($input['start_datetime'])) {
        $updates[] = "start_datetime = ?";
        $params[] = $input['start_datetime'];
    }
    if (isset($input['end_datetime'])) {
        $updates[] = "end_datetime = ?";
        $params[] = $input['end_datetime'];
    }
    if (isset($input['assigned_users'])) {
        $updates[] = "assigned_users = ?";
        $params[] = $input['assigned_users'];
    }
    if (isset($input['equipment_needed'])) {
        $updates[] = "equipment_needed = ?";
        $params[] = $input['equipment_needed'];
    }
    if (isset($input['notes'])) {
        $updates[] = "notes = ?";
        $params[] = $input['notes'];
    }
    if (isset($input['status'])) {
        $updates[] = "status = ?";
        $params[] = $input['status'];
    }
    if (isset($input['all_day'])) {
        $updates[] = "all_day = ?";
        $params[] = $input['all_day'];
    }

    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No updates provided']);
        exit;
    }

    $params[] = $eventId;
    $query = "UPDATE calendar_events SET " . implode(", ", $updates) . " WHERE id = ?";

    $stmt = $db->prepare($query);
    $stmt->execute($params);

    echo json_encode(['success' => true, 'message' => 'Event updated successfully']);
    exit;
}

// DELETE - Remove event
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $input = json_decode(file_get_contents('php://input'), true);
    $eventId = $input['event_id'] ?? null;

    if (!$eventId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Event ID required']);
        exit;
    }

    // Check permission
    $stmt = $db->prepare("SELECT created_by FROM calendar_events WHERE id = ?");
    $stmt->execute([$eventId]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Event not found']);
        exit;
    }

    // Only creator or admin/manager can delete
    if ($event['created_by'] != $userId && !in_array($userRole, ['admin', 'manager'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        exit;
    }

    $stmt = $db->prepare("DELETE FROM calendar_events WHERE id = ?");
    $stmt->execute([$eventId]);

    echo json_encode(['success' => true, 'message' => 'Event deleted successfully']);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
?>
