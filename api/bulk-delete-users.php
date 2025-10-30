<?php
/**
 * Bulk Delete Users API
 * Admin only - Delete multiple users at once
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors, we'll return them as JSON
ini_set('log_errors', 1);

header('Content-Type: application/json');
require_once '../config/config.php';
require_once '../includes/functions.php';

// Check authentication and authorization
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Not logged in',
        'debug' => [
            'session_id' => session_id(),
            'session_user_id' => $_SESSION['user_id'] ?? null,
            'session_role' => $_SESSION['role'] ?? null
        ]
    ]);
    exit;
}

if (!hasRole('admin')) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access - Admin role required',
        'debug' => [
            'your_role' => $_SESSION['role'] ?? null,
            'has_admin_role' => hasRole('admin')
        ]
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $db = getDBConnection();
    $currentUser = getCurrentUser();

    // Get JSON input
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    $userIds = $input['user_ids'] ?? [];

    // Validate input
    if (empty($userIds) || !is_array($userIds)) {
        echo json_encode([
            'success' => false,
            'message' => 'No users selected',
            'debug' => [
                'raw_input' => $rawInput,
                'parsed_input' => $input,
                'user_ids' => $userIds
            ]
        ]);
        exit;
    }

    // Prevent deleting own account
    $currentUserId = $currentUser['id'];
    if (in_array($currentUserId, $userIds)) {
        echo json_encode(['success' => false, 'message' => 'You cannot delete your own account']);
        exit;
    }

    // Start transaction
    $db->beginTransaction();

    $deletedCount = 0;
    $errors = [];

    foreach ($userIds as $userId) {
        // Validate user ID
        if (!is_numeric($userId)) {
            $errors[] = "Invalid user ID: $userId (not numeric)";
            error_log("Bulk delete: Invalid user ID - $userId");
            continue;
        }

        // Check if user exists
        $stmt = $db->prepare("SELECT id, username, role FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $errors[] = "User ID $userId not found in database";
            error_log("Bulk delete: User $userId not found");
            continue;
        }

        error_log("Bulk delete: Processing user {$user['username']} (ID: $userId)");

        // Prevent deleting admin users (optional safety measure)
        // Uncomment this if you want to prevent deleting other admins
        // if ($user['role'] === 'admin') {
        //     $errors[] = "Cannot delete admin user: {$user['username']}";
        //     continue;
        // }

        try {
            // Delete related data first to maintain referential integrity

            // 1. Delete time logs
            $db->prepare("DELETE FROM time_logs WHERE user_id = ?")->execute([$userId]);

            // 2. Update tasks to unassign this user (set assigned_to to NULL)
            $db->prepare("UPDATE tasks SET assigned_to = NULL WHERE assigned_to = ?")->execute([$userId]);

            // 3. Update projects to unassign this manager (set assigned_manager to NULL)
            $db->prepare("UPDATE projects SET assigned_manager = NULL WHERE assigned_manager = ?")->execute([$userId]);

            // 4. Delete notifications for this user
            $db->prepare("DELETE FROM notifications WHERE user_id = ?")->execute([$userId]);

            // 5. Delete activity logs for this user
            $db->prepare("DELETE FROM activity_log WHERE user_id = ?")->execute([$userId]);

            // 6. Delete client feedback assigned to this user
            $db->prepare("UPDATE client_feedback SET assigned_to = NULL WHERE assigned_to = ?")->execute([$userId]);

            // 7. Finally, delete the user
            $deleteStmt = $db->prepare("DELETE FROM users WHERE id = ?");
            $deleteStmt->execute([$userId]);

            // 8. Log the activity
            logActivity(
                'delete',
                'user',
                $userId,
                "Deleted user: {$user['username']} ({$user['role']})",
                [
                    'username' => $user['username'],
                    'role' => $user['role'],
                    'bulk_delete' => true
                ]
            );

            $deletedCount++;

        } catch (Exception $e) {
            $errors[] = "Failed to delete user {$user['username']}: " . $e->getMessage();
        }
    }

    // Commit transaction
    $db->commit();

    if ($deletedCount > 0) {
        echo json_encode([
            'success' => true,
            'deleted_count' => $deletedCount,
            'message' => "$deletedCount user(s) deleted successfully",
            'errors' => $errors
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No users were deleted',
            'errors' => $errors,
            'debug' => [
                'user_ids_count' => count($userIds),
                'deleted_count' => $deletedCount,
                'current_user_id' => $currentUserId
            ]
        ]);
    }

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
