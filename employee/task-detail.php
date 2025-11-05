<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !hasRole('employee')) {
    redirect('../login.php');
}

$db = getDBConnection();
$currentUser = getCurrentUser();

// Get task ID
$taskId = $_GET['id'] ?? null;

if (!$taskId) {
    redirect('tasks.php');
}

// Get task details
$stmt = $db->prepare("
    SELECT t.*, p.project_name, p.client_name, p.id as project_id,
           u.full_name as assigned_to_name,
           creator.full_name as created_by_name
    FROM tasks t
    JOIN projects p ON t.project_id = p.id
    LEFT JOIN users u ON t.assigned_to = u.id
    LEFT JOIN users creator ON t.created_by = creator.id
    WHERE t.id = ?
");
$stmt->execute([$taskId]);
$task = $stmt->fetch();

if (!$task) {
    redirect('tasks.php');
}

// Verify task is assigned to current user
if ($task['assigned_to'] != $currentUser['id']) {
    redirect('tasks.php');
}

// Get task comments
$stmt = $db->prepare("
    SELECT tc.*, u.full_name, u.job_title, u.role
    FROM task_comments tc
    JOIN users u ON tc.user_id = u.id
    WHERE tc.task_id = ?
    ORDER BY tc.created_at ASC
");
$stmt->execute([$taskId]);
$comments = $stmt->fetchAll();

// Check if task is currently being tracked
$stmt = $db->prepare("SELECT COUNT(*) as count FROM time_logs WHERE user_id = ? AND task_id = ? AND is_active = 1");
$stmt->execute([$currentUser['id'], $taskId]);
$isTracking = $stmt->fetch()['count'] > 0;

// Get task dependencies
$stmt = $db->prepare("
    SELECT td.*, t.task_name, t.status, p.project_name
    FROM task_dependencies td
    JOIN tasks t ON td.depends_on_task_id = t.id
    JOIN projects p ON t.project_id = p.id
    WHERE td.task_id = ?
");
$stmt->execute([$taskId]);
$dependencies = $stmt->fetchAll();

// Get available tasks for adding dependencies (same project, not this task, not already dependent)
$stmt = $db->prepare("
    SELECT t.id, t.task_name, t.status
    FROM tasks t
    WHERE t.project_id = ?
    AND t.id != ?
    AND t.id NOT IN (SELECT depends_on_task_id FROM task_dependencies WHERE task_id = ?)
");
$stmt->execute([$task['project_id'], $taskId, $taskId]);
$availableTasks = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($task['task_name']); ?> - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/vien-v3.css">

    <!-- Synto Dashboard Template Design -->
    <link rel="stylesheet" href="../assets/css/synto-design.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .task-detail-header {
            background: var(--bg-secondary);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            border: 2px solid var(--border-color);
        }

        .task-meta-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-top: 20px;
        }

        .task-meta-item {
            background: var(--bg-primary);
            padding: 12px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }

        .task-meta-label {
            font-size: 12px;
            color: var(--text-secondary);
            margin-bottom: 4px;
        }

        .task-meta-value {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .comments-section {
            background: var(--bg-secondary);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
        }

        .comment {
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 16px;
        }

        .comment.update {
            border-left: 4px solid var(--primary);
            background: var(--primary-light);
        }

        .comment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border-color);
        }

        .comment-author {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .comment-author-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        .comment-content {
            color: var(--text-primary);
            line-height: 1.6;
            white-space: pre-wrap;
        }

        .comment-form {
            background: var(--bg-primary);
            border: 2px solid var(--border-color);
            border-radius: 8px;
            padding: 16px;
        }

        .comment-form textarea {
            width: 100%;
            min-height: 100px;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            background: var(--bg-secondary);
            color: var(--text-primary);
            font-family: inherit;
            resize: vertical;
        }

        .action-buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <?php include '../includes/v3-employee-sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <?php include '../includes/v3-header.php'; ?>

            <div class="content-wrapper">
                <!-- Back Button -->
                <div style="margin-bottom: 16px;">
                    <a href="tasks.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Tasks
                    </a>
                </div>

                <!-- Task Header -->
                <div class="task-detail-header">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 16px;">
                        <div style="flex: 1;">
                            <h1 style="margin: 0 0 12px; color: var(--text-primary); font-size: 28px;">
                                <?php echo e($task['task_name']); ?>
                            </h1>
                            <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                                <span class="badge <?php echo getStatusClass($task['status']); ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $task['status'])); ?>
                                </span>
                                <span class="badge <?php echo getPriorityClass($task['priority']); ?>">
                                    <?php echo ucfirst($task['priority']); ?> Priority
                                </span>
                                <?php if (isOverdue($task['due_date'], $task['status'])): ?>
                                    <span class="badge" style="background: #ef4444; color: white;">⚠️ Overdue</span>
                                <?php endif; ?>
                                <?php if ($isTracking): ?>
                                    <span class="badge" style="background: linear-gradient(135deg, #667eea, #764ba2); color: white;">⏱️ Tracking Active</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="action-buttons">
                            <?php if ($task['status'] !== 'completed'): ?>
                                <?php if (!$isTracking): ?>
                                    <button onclick="startTimer(<?php echo $task['id']; ?>, <?php echo $task['project_id']; ?>)" class="btn btn-primary">
                                        <i class="fas fa-play"></i> Start Working
                                    </button>
                                <?php else: ?>
                                    <button onclick="stopTask(<?php echo $task['id']; ?>)" class="btn btn-warning">
                                        <i class="fas fa-stop"></i> Stop Working
                                    </button>
                                <?php endif; ?>

                                <?php if ($task['status'] !== 'review'): ?>
                                    <button onclick="submitForReview(<?php echo $task['id']; ?>)" class="btn btn-success">
                                        <i class="fas ri-check-line"></i> Submit for Review
                                    </button>
                                <?php endif; ?>

                                <button onclick="markAsCompleted(<?php echo $task['id']; ?>)" class="btn" style="background: #10b981; color: white;">
                                    <i class="fas ri-check-line-double"></i> Mark Completed
                                </button>
                            <?php else: ?>
                                <span style="color: var(--success); font-weight: 600; font-size: 18px;">
                                    <i class="fas ri-checkbox-circle-line"></i> Task Completed
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($task['description']): ?>
                    <div style="background: var(--bg-primary); padding: 16px; border-radius: 8px; margin-top: 16px; border: 1px solid var(--border-color);">
                        <strong style="color: var(--text-secondary); font-size: 12px; text-transform: uppercase;">Description</strong>
                        <p style="margin: 8px 0 0; color: var(--text-primary); line-height: 1.6;">
                            <?php echo nl2br(e($task['description'])); ?>
                        </p>
                    </div>
                    <?php endif; ?>

                    <!-- Task Meta Information -->
                    <div class="task-meta-grid">
                        <div class="task-meta-item">
                            <div class="task-meta-label">Project</div>
                            <div class="task-meta-value">📁 <?php echo e($task['project_name']); ?></div>
                        </div>

                        <?php if ($task['client_name']): ?>
                        <div class="task-meta-item">
                            <div class="task-meta-label">Client</div>
                            <div class="task-meta-value">👤 <?php echo e($task['client_name']); ?></div>
                        </div>
                        <?php endif; ?>

                        <?php if ($task['due_date']): ?>
                        <div class="task-meta-item">
                            <div class="task-meta-label">Due Date</div>
                            <div class="task-meta-value">📅 <?php echo date('M d, Y', strtotime($task['due_date'])); ?></div>
                        </div>
                        <?php endif; ?>

                        <?php if ($task['estimated_hours']): ?>
                        <div class="task-meta-item">
                            <div class="task-meta-label">Estimated Hours</div>
                            <div class="task-meta-value">⏰ <?php echo $task['estimated_hours']; ?>h</div>
                        </div>
                        <?php endif; ?>

                        <div class="task-meta-item">
                            <div class="task-meta-label">Time Logged</div>
                            <div class="task-meta-value">⏱️ <?php echo $task['actual_hours'] ?? '0'; ?>h</div>
                        </div>

                        <div class="task-meta-item">
                            <div class="task-meta-label">Created By</div>
                            <div class="task-meta-value">👨‍💼 <?php echo e($task['created_by_name']); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Task Dependencies Section -->
                <div class="comments-section" style="margin-bottom: 24px;">
                    <h3 style="margin: 0 0 20px; color: var(--text-primary);">
                        <i class="fas fa-link"></i> Task Dependencies
                    </h3>

                    <?php if (!empty($dependencies)): ?>
                    <div style="margin-bottom: 20px;">
                        <p style="color: var(--text-secondary); font-size: 14px; margin-bottom: 12px;">
                            This task depends on the following tasks:
                        </p>
                        <div id="dependenciesList">
                            <?php foreach ($dependencies as $dep): ?>
                            <div class="task-meta-item" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; padding: 12px;">
                                <div style="flex: 1;">
                                    <div style="font-weight: 600; color: var(--text-primary);">
                                        <?php echo e($dep['task_name']); ?>
                                    </div>
                                    <div style="font-size: 12px; color: var(--text-secondary); margin-top: 4px;">
                                        <?php echo e($dep['project_name']); ?> •
                                        <span class="badge <?php echo getStatusClass($dep['status']); ?>" style="font-size: 11px;">
                                            <?php echo ucfirst(str_replace('_', ' ', $dep['status'])); ?>
                                        </span>
                                    </div>
                                </div>
                                <button onclick="removeDependency(<?php echo $dep['id']; ?>)" class="btn btn-sm" style="background: #ef4444; color: white; padding: 6px 12px;">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($availableTasks)): ?>
                    <div class="comment-form">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-primary);">
                            Add Dependency
                        </label>
                        <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 12px;">
                            Mark this task as dependent on another task. This task should only be started after the dependent task is completed.
                        </p>
                        <select id="dependencyTaskId" class="form-control" style="margin-bottom: 12px;">
                            <option value="">Select a task...</option>
                            <?php foreach ($availableTasks as $availTask): ?>
                            <option value="<?php echo $availTask['id']; ?>">
                                <?php echo e($availTask['task_name']); ?> (<?php echo ucfirst($availTask['status']); ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <button onclick="addDependency()" class="btn btn-primary">
                            <i class="fas ri-add-line"></i> Add Dependency
                        </button>
                    </div>
                    <?php elseif (empty($dependencies)): ?>
                    <div style="text-align: center; padding: 20px; color: var(--text-secondary); background: var(--bg-primary); border-radius: 8px;">
                        <i class="fas fa-link" style="font-size: 32px; opacity: 0.3; margin-bottom: 8px;"></i>
                        <p>No dependencies for this task. No other tasks in this project available to add as dependencies.</p>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Comments Section -->
                <div class="comments-section">
                    <h3 style="margin: 0 0 20px; color: var(--text-primary);">
                        <i class="fas ri-chat-3-line"></i> Updates & Comments (<?php echo count($comments); ?>)
                    </h3>

                    <!-- Add Comment Form -->
                    <div class="comment-form" style="margin-bottom: 24px;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-primary);">
                            Add Update or Comment
                        </label>
                        <textarea id="commentText" placeholder="Share your progress, ask questions, or provide updates..."></textarea>
                        <div style="margin-top: 12px; display: flex; gap: 12px; align-items: center;">
                            <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                                <input type="checkbox" id="isUpdate" style="width: 18px; height: 18px;">
                                <span style="font-size: 14px; color: var(--text-secondary);">Mark as important update/milestone</span>
                            </label>
                            <div style="flex: 1;"></div>
                            <button onclick="addComment()" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> Post Comment
                            </button>
                        </div>
                    </div>

                    <!-- Comments List -->
                    <div id="commentsList">
                        <?php if (empty($comments)): ?>
                            <div style="text-align: center; padding: 40px; color: var(--text-secondary);">
                                <i class="fas ri-chat-3-line" style="font-size: 48px; opacity: 0.3; margin-bottom: 12px;"></i>
                                <p>No comments yet. Be the first to add an update!</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($comments as $comment): ?>
                            <div class="comment <?php echo $comment['is_update'] ? 'update' : ''; ?>">
                                <div class="comment-header">
                                    <div class="comment-author">
                                        <div class="comment-author-icon">
                                            <?php echo strtoupper(substr($comment['full_name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div style="font-weight: 600; color: var(--text-primary);">
                                                <?php echo e($comment['full_name']); ?>
                                                <?php if ($comment['is_update']): ?>
                                                    <span class="badge" style="background: var(--primary); color: white; margin-left: 8px; font-size: 11px;">
                                                        <i class="fas fa-flag"></i> MILESTONE
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <div style="font-size: 12px; color: var(--text-secondary);">
                                                <?php echo ucfirst($comment['role']); ?>
                                                <?php if ($comment['job_title']): ?>
                                                    • <?php echo e($comment['job_title']); ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div style="font-size: 12px; color: var(--text-secondary);">
                                        <?php echo timeAgo($comment['created_at']); ?>
                                    </div>
                                </div>
                                <div class="comment-content">
                                    <?php echo nl2br(e($comment['comment'])); ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/theme.js"></script>
    <script>
        // Task tracking functions
        function startTimer(taskId, projectId) {
            fetch('../api/time-tracking.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'start',
                    task_id: taskId,
                    project_id: projectId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage('Task tracking started!', 'success');
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    alert(data.message || 'Failed to start tracking');
                }
            })
            .catch(error => {
                alert('Error starting tracking');
                console.error(error);
            });
        }

        function stopTask(taskId) {
            if (confirm('Stop working on this task? Time will be automatically calculated.')) {
                const notes = prompt('Add notes for this work session (optional):');

                fetch('../api/time-tracking.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'stop_by_task',
                        task_id: taskId,
                        notes: notes || ''
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const minutes = data.total_duration_minutes || 0;
                        const hours = Math.floor(minutes / 60);
                        const mins = minutes % 60;
                        const timeStr = hours > 0 ? `${hours}h ${mins}m` : `${mins}m`;

                        showMessage(`Task stopped! Total time: ${timeStr}`, 'success');
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        alert(data.message || 'Failed to stop task');
                    }
                })
                .catch(error => {
                    alert('Error stopping task');
                    console.error(error);
                });
            }
        }

        function submitForReview(taskId) {
            if (confirm('Submit this task for review? Your PM will be notified.')) {
                fetch('../api/tasks.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'update_status',
                        task_id: taskId,
                        status: 'review'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showMessage('Task submitted for review! Points awarded: ' + (data.points_awarded || 0), 'success');
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        alert(data.message || 'Failed to submit task');
                    }
                })
                .catch(error => {
                    alert('Error submitting task');
                    console.error(error);
                });
            }
        }

        function markAsCompleted(taskId) {
            if (confirm('Mark this task as completed? This will finalize the task.')) {
                fetch('../api/tasks.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'update_status',
                        task_id: taskId,
                        status: 'completed'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let message = 'Task completed! 🎉';
                        if (data.points_awarded) {
                            message += ` You earned ${data.points_awarded} points!`;
                        }
                        if (data.new_badges && data.new_badges.length > 0) {
                            message += ` New badge unlocked: ${data.new_badges[0].name}!`;
                        }

                        showMessage(message, 'success');
                        setTimeout(() => window.location.reload(), 2000);
                    } else {
                        alert(data.message || 'Failed to complete task');
                    }
                })
                .catch(error => {
                    alert('Error completing task');
                    console.error(error);
                });
            }
        }

        function addComment() {
            const commentText = document.getElementById('commentText').value.trim();
            const isUpdate = document.getElementById('isUpdate').checked;

            if (!commentText) {
                alert('Please enter a comment');
                return;
            }

            fetch('../api/comments.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'add_task_comment',
                    task_id: <?php echo $taskId; ?>,
                    comment: commentText,
                    is_update: isUpdate ? 1 : 0
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage('Comment added successfully!', 'success');
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    alert(data.message || 'Failed to add comment');
                }
            })
            .catch(error => {
                alert('Error adding comment');
                console.error(error);
            });
        }

        function showMessage(text, type) {
            const message = document.createElement('div');
            const bgColor = type === 'success' ? '#10b981' : '#ef4444';
            message.style.cssText = `position: fixed; top: 20px; right: 20px; background: ${bgColor}; color: white; padding: 16px 24px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 9999; max-width: 400px;`;
            message.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${text}`;
            document.body.appendChild(message);
            setTimeout(() => message.remove(), 3000);
        }

        // Task dependency functions
        function addDependency() {
            const dependsOnTaskId = document.getElementById('dependencyTaskId').value;

            if (!dependsOnTaskId) {
                alert('Please select a task');
                return;
            }

            fetch('../api/productivity.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'add_task_dependency',
                    task_id: <?php echo $taskId; ?>,
                    depends_on_task_id: dependsOnTaskId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage('Dependency added successfully!', 'success');
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    alert(data.message || 'Failed to add dependency');
                }
            })
            .catch(error => {
                alert('Error adding dependency');
                console.error(error);
            });
        }

        function removeDependency(dependencyId) {
            if (!confirm('Remove this dependency?')) return;

            fetch('../api/productivity.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'remove_task_dependency',
                    dependency_id: dependencyId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage('Dependency removed!', 'success');
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    alert(data.message || 'Failed to remove dependency');
                }
            })
            .catch(error => {
                alert('Error removing dependency');
                console.error(error);
            });
        }
    </script>

    <!-- Synto Dashboard Interactions -->
    <script src="../assets/js/synto-interactions.js"></script>
</body>
</html>
