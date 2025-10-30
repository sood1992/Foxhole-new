<?php
// Helper Functions

// Format time duration
function formatDuration($minutes) {
    if ($minutes < 60) {
        return round($minutes) . 'm';
    }
    $hours = floor($minutes / 60);
    $mins = $minutes % 60;
    return $hours . 'h ' . round($mins) . 'm';
}

// Format hours with decimal
function formatHours($minutes) {
    return number_format($minutes / 60, 2);
}

// Get status badge class
function getStatusClass($status) {
    $classes = [
        'todo' => 'status-todo',
        'in_progress' => 'status-progress',
        'review' => 'status-review',
        'completed' => 'status-completed',
        'blocked' => 'status-blocked',
        'on_hold' => 'status-hold',
        'planning' => 'status-planning'
    ];
    return $classes[$status] ?? 'status-default';
}

// Get priority badge class
function getPriorityClass($priority) {
    $classes = [
        'low' => 'priority-low',
        'medium' => 'priority-medium',
        'high' => 'priority-high',
        'urgent' => 'priority-urgent'
    ];
    return $classes[$priority] ?? 'priority-medium';
}

// Calculate progress percentage
function calculateProgress($status) {
    $progress = [
        'planning' => 10,
        'todo' => 10,
        'in_progress' => 50,
        'review' => 80,
        'completed' => 100,
        'on_hold' => 0,
        'blocked' => 0
    ];
    return $progress[$status] ?? 0;
}

// Time ago function
function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;

    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . ' minutes ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';

    return date('M d, Y', $timestamp);
}

// Sanitize output
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Get user's active time log with task and project details
function getActiveTimeLog($userId) {
    $db = getDBConnection();
    $stmt = $db->prepare("
        SELECT tl.*, t.task_name, p.project_name
        FROM time_logs tl
        LEFT JOIN tasks t ON tl.task_id = t.id
        LEFT JOIN projects p ON tl.project_id = p.id
        WHERE tl.user_id = ? AND tl.is_active = 1
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

// Calculate total hours for project
function getProjectTotalHours($projectId) {
    $db = getDBConnection();
    $stmt = $db->prepare("
        SELECT SUM(duration_minutes) as total_minutes
        FROM time_logs
        WHERE project_id = ? AND end_time IS NOT NULL
    ");
    $stmt->execute([$projectId]);
    $result = $stmt->fetch();
    return formatHours($result['total_minutes'] ?? 0);
}

// Get task completion percentage for project
function getProjectCompletionRate($projectId) {
    $db = getDBConnection();
    $stmt = $db->prepare("
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
        FROM tasks
        WHERE project_id = ?
    ");
    $stmt->execute([$projectId]);
    $result = $stmt->fetch();

    if ($result['total'] == 0) return 0;
    return round(($result['completed'] / $result['total']) * 100);
}

// Get employee productivity stats
function getEmployeeStats($userId, $startDate = null, $endDate = null) {
    $db = getDBConnection();

    $dateFilter = "";
    $params = [$userId];

    if ($startDate && $endDate) {
        $dateFilter = "AND DATE(start_time) BETWEEN ? AND ?";
        $params[] = $startDate;
        $params[] = $endDate;
    }

    $stmt = $db->prepare("
        SELECT
            COUNT(DISTINCT project_id) as projects_worked,
            COUNT(DISTINCT task_id) as tasks_worked,
            SUM(duration_minutes) as total_minutes,
            AVG(duration_minutes) as avg_session_minutes
        FROM time_logs
        WHERE user_id = ? AND end_time IS NOT NULL $dateFilter
    ");
    $stmt->execute($params);
    return $stmt->fetch();
}

// Check if date is overdue
function isOverdue($dueDate, $status) {
    if ($status === 'completed') return false;
    if (!$dueDate) return false;
    return strtotime($dueDate) < strtotime('today');
}

// Create notification
function createNotification($userId, $title, $message, $type = 'system', $relatedType = null, $relatedId = null) {
    $db = getDBConnection();
    $stmt = $db->prepare("
        INSERT INTO notifications (user_id, title, message, type, related_type, related_id)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    return $stmt->execute([$userId, $title, $message, $type, $relatedType, $relatedId]);
}

// Notify user about task assignment
function notifyTaskAssignment($taskId, $assignedToId, $assignedByName, $taskName) {
    return createNotification(
        $assignedToId,
        'New Task Assigned',
        "$assignedByName assigned you to: $taskName",
        'task',
        'task',
        $taskId
    );
}

// Notify about approaching deadline
function notifyUpcomingDeadline($userId, $taskName, $dueDate) {
    return createNotification(
        $userId,
        'Upcoming Deadline',
        "$taskName is due on $dueDate",
        'deadline',
        'task',
        null
    );
}

// Notify about comment/mention
function notifyMention($userId, $mentionedByName, $entityType, $entityId) {
    return createNotification(
        $userId,
        'You were mentioned',
        "$mentionedByName mentioned you in a comment",
        'mention',
        $entityType,
        $entityId
    );
}