<?php
/**
 * TASK DEPENDENCY WORKFLOW AUTOMATION
 *
 * Automatically handles task dependencies when task statuses change
 * Implements the workflow: Creative Team → Edit Team (automatic progression)
 */

/**
 * Handle task status update and check dependencies
 * Call this function whenever a task status is updated
 *
 * @param int $taskId The task that was updated
 * @param string $newStatus The new status of the task
 * @param PDO $db Database connection
 * @return array Results of dependency processing
 */
function handleTaskDependencies($taskId, $newStatus, $db) {
    $results = [
        'dependent_tasks_updated' => 0,
        'notifications_sent' => 0,
        'blocked_tasks' => [],
        'unlocked_tasks' => []
    ];

    try {
        // If task is completed, check for dependent tasks
        if ($newStatus === 'completed') {
            // Find all tasks that depend on this task (finish-to-start)
            $dependentTasksStmt = $db->prepare("
                SELECT t.*, td.dependency_type, u.email, u.full_name,
                       prerequisite.task_name as prerequisite_name
                FROM task_dependencies td
                JOIN tasks t ON td.task_id = t.id
                JOIN tasks prerequisite ON td.depends_on_task_id = prerequisite.id
                LEFT JOIN users u ON t.assigned_to = u.id
                WHERE td.depends_on_task_id = ?
                AND td.dependency_type = 'finish_to_start'
                AND t.status = 'blocked'
            ");
            $dependentTasksStmt->execute([$taskId]);
            $dependentTasks = $dependentTasksStmt->fetchAll();

            foreach ($dependentTasks as $depTask) {
                // Check if ALL prerequisites for this task are completed
                $prerequisitesStmt = $db->prepare("
                    SELECT COUNT(*) as total,
                           SUM(CASE WHEN prerequisite.status = 'completed' THEN 1 ELSE 0 END) as completed
                    FROM task_dependencies td
                    JOIN tasks prerequisite ON td.depends_on_task_id = prerequisite.id
                    WHERE td.task_id = ?
                    AND td.dependency_type = 'finish_to_start'
                ");
                $prerequisitesStmt->execute([$depTask['id']]);
                $prerequisites = $prerequisitesStmt->fetch();

                // If all prerequisites are completed, unlock this task
                if ($prerequisites['total'] == $prerequisites['completed']) {
                    // Update task status from 'blocked' to 'todo'
                    $updateStmt = $db->prepare("
                        UPDATE tasks
                        SET status = 'todo'
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$depTask['id']]);

                    $results['dependent_tasks_updated']++;
                    $results['unlocked_tasks'][] = [
                        'task_id' => $depTask['id'],
                        'task_name' => $depTask['task_name'],
                        'assigned_to' => $depTask['full_name']
                    ];

                    // Send notification to assigned user
                    if ($depTask['email'] && function_exists('sendTaskUnlockedNotification')) {
                        sendTaskUnlockedNotification($depTask['id'], $depTask['prerequisite_name']);
                        $results['notifications_sent']++;
                    }
                }
            }
        }

        // If task is blocked, record it
        if ($newStatus === 'blocked') {
            $results['blocked_tasks'][] = $taskId;
        }

        return $results;

    } catch (PDOException $e) {
        error_log("Dependency handler error: " . $e->getMessage());
        return $results;
    }
}

/**
 * Automatically block tasks that have incomplete prerequisites
 * Run this when a new dependency is created
 *
 * @param int $taskId The task with dependencies
 * @param PDO $db Database connection
 */
function autoBlockTaskWithDependencies($taskId, $db) {
    try {
        // Check if this task has any incomplete prerequisites
        $prerequisitesStmt = $db->prepare("
            SELECT COUNT(*) as incomplete
            FROM task_dependencies td
            JOIN tasks prerequisite ON td.depends_on_task_id = prerequisite.id
            WHERE td.task_id = ?
            AND td.dependency_type = 'finish_to_start'
            AND prerequisite.status != 'completed'
        ");
        $prerequisitesStmt->execute([$taskId]);
        $result = $prerequisitesStmt->fetch();

        // If there are incomplete prerequisites, set task to blocked
        if ($result['incomplete'] > 0) {
            $updateStmt = $db->prepare("
                UPDATE tasks
                SET status = 'blocked'
                WHERE id = ?
                AND status NOT IN ('completed', 'in_progress')
            ");
            $updateStmt->execute([$taskId]);

            return true;
        }

        return false;

    } catch (PDOException $e) {
        error_log("Auto-block task error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get all tasks that are blocking a specific task
 *
 * @param int $taskId The task to check
 * @param PDO $db Database connection
 * @return array List of blocking tasks
 */
function getBlockingTasks($taskId, $db) {
    try {
        $stmt = $db->prepare("
            SELECT prerequisite.*, td.dependency_type
            FROM task_dependencies td
            JOIN tasks prerequisite ON td.depends_on_task_id = prerequisite.id
            WHERE td.task_id = ?
            AND prerequisite.status != 'completed'
            ORDER BY prerequisite.due_date ASC
        ");
        $stmt->execute([$taskId]);
        return $stmt->fetchAll();

    } catch (PDOException $e) {
        error_log("Get blocking tasks error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get all tasks that depend on a specific task
 *
 * @param int $taskId The task to check
 * @param PDO $db Database connection
 * @return array List of dependent tasks
 */
function getDependentTasks($taskId, $db) {
    try {
        $stmt = $db->prepare("
            SELECT t.*, td.dependency_type, u.full_name as assigned_to_name
            FROM task_dependencies td
            JOIN tasks t ON td.task_id = t.id
            LEFT JOIN users u ON t.assigned_to = u.id
            WHERE td.depends_on_task_id = ?
            ORDER BY t.due_date ASC
        ");
        $stmt->execute([$taskId]);
        return $stmt->fetchAll();

    } catch (PDOException $e) {
        error_log("Get dependent tasks error: " . $e->getMessage());
        return [];
    }
}

/**
 * Send notification when a task is unlocked
 *
 * @param int $taskId The unlocked task
 * @param string $prerequisiteName The name of the completed prerequisite
 */
function sendTaskUnlockedNotification($taskId, $prerequisiteName) {
    // This would integrate with your email system
    // For now, just log it
    error_log("Task {$taskId} unlocked after completion of {$prerequisiteName}");

    // You can integrate with email-functions.php here
    // Example:
    // require_once __DIR__ . '/email-functions.php';
    // $db = getDBConnection();
    // ... send email notification
}

/**
 * Check if a task can be started (has no incomplete dependencies)
 *
 * @param int $taskId The task to check
 * @param PDO $db Database connection
 * @return bool True if task can be started
 */
function canTaskBeStarted($taskId, $db) {
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*) as incomplete_dependencies
            FROM task_dependencies td
            JOIN tasks prerequisite ON td.depends_on_task_id = prerequisite.id
            WHERE td.task_id = ?
            AND td.dependency_type = 'finish_to_start'
            AND prerequisite.status != 'completed'
        ");
        $stmt->execute([$taskId]);
        $result = $stmt->fetch();

        return $result['incomplete_dependencies'] == 0;

    } catch (PDOException $e) {
        error_log("Can task be started error: " . $e->getMessage());
        return true; // Default to allowing if error
    }
}

/**
 * WORKFLOW EXAMPLE:
 *
 * When creating a task with dependencies:
 * 1. Create the task
 * 2. Create the dependency record
 * 3. Call autoBlockTaskWithDependencies($taskId, $db)
 *
 * When updating a task status:
 * 1. Update the task status
 * 2. Call handleTaskDependencies($taskId, $newStatus, $db)
 * 3. This will automatically unlock dependent tasks if prerequisites are met
 *
 * Example:
 * - Creative Team completes "Design Homepage" (Task A)
 * - handleTaskDependencies(A, 'completed', $db) is called
 * - Finds "Edit Homepage" (Task B) depends on Task A
 * - Checks if all Task B prerequisites are complete
 * - If yes, updates Task B from 'blocked' to 'todo'
 * - Sends notification to Edit Team member
 * - Edit Team can now start their task!
 */
?>
