<?php
/**
 * Task Event Hooks
 * Automatically trigger actions when tasks are created, updated, or deleted
 */

require_once __DIR__ . '/gamification-functions.php';

// Hook: After task status is updated
function hookTaskStatusUpdate($taskId, $oldStatus, $newStatus, $userId) {
    // If task was just completed, process gamification
    if ($newStatus === 'completed' && $oldStatus !== 'completed') {
        processTaskCompletion($taskId, $userId);

        // Also update completed_date
        $db = getDBConnection();
        $db->prepare("UPDATE tasks SET completed_date = NOW() WHERE id = ?")->execute([$taskId]);
    }
}

// Hook: After task is created
function hookTaskCreated($taskId, $assignedTo, $createdBy) {
    // Could send notification here
    if ($assignedTo != $createdBy) {
        $db = getDBConnection();
        $task = $db->prepare("SELECT task_name FROM tasks WHERE id = ?")->execute([$taskId]);
        // notifyTaskAssignment($taskId, $assignedTo, $createdBy, $task['task_name']);
    }
}

// Helper function to update task status with hooks
function updateTaskStatus($taskId, $newStatus, $userId) {
    $db = getDBConnection();

    // Get old status
    $stmt = $db->prepare("SELECT status, assigned_to FROM tasks WHERE id = ?");
    $stmt->execute([$taskId]);
    $task = $stmt->fetch();

    if (!$task) {
        return false;
    }

    $oldStatus = $task['status'];
    $assignedTo = $task['assigned_to'];

    // Update status
    $update = $db->prepare("UPDATE tasks SET status = ?, updated_at = NOW() WHERE id = ?");
    $update->execute([$newStatus, $taskId]);

    // Trigger hooks
    hookTaskStatusUpdate($taskId, $oldStatus, $newStatus, $assignedTo);

    return true;
}
