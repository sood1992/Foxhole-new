<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !hasRole('manager')) {
    redirect('../login.php');
}

$db = getDBConnection();
$currentUser = getCurrentUser();

// Get users for assignment dropdown
$usersStmt = $db->query("
    SELECT id, full_name
    FROM users
    WHERE role IN ('employee', 'manager') AND is_active = 1
    ORDER BY full_name
");
$users = $usersStmt->fetchAll();

// Get manager's projects for dropdown
$projectsStmt = $db->prepare("
    SELECT id, project_name
    FROM projects
    WHERE assigned_manager = ?
    ORDER BY project_name
");
$projectsStmt->execute([$currentUser['id']]);
$managerProjects = $projectsStmt->fetchAll();

// Get all tasks for manager's projects
$stmt = $db->prepare("
    SELECT t.*, p.project_name, u.full_name as assigned_to_name
    FROM tasks t
    JOIN projects p ON t.project_id = p.id
    LEFT JOIN users u ON t.assigned_to = u.id
    WHERE p.assigned_manager = ?
    ORDER BY
        FIELD(t.status, 'blocked', 'in_progress', 'review', 'todo', 'completed'),
        t.priority = 'urgent' DESC,
        t.priority = 'high' DESC,
        t.due_date ASC
");
$stmt->execute([$currentUser['id']]);
$tasks = $stmt->fetchAll();

// Group by status
$tasksByStatus = [
    'todo' => [],
    'in_progress' => [],
    'review' => [],
    'blocked' => [],
    'completed' => []
];

foreach ($tasks as $task) {
    if (isset($tasksByStatus[$task['status']])) {
        $tasksByStatus[$task['status']][] = $task;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tasks V3 - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/vien-v3.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include '../includes/quick-actions-assets.php'; ?>
</head>
<body>
    <div class="app-container">
        <?php include '../includes/v3-manager-sidebar.php'; ?>

        <div class="main-content">
            <?php include '../includes/v3-header.php'; ?>

            <div class="content-wrapper">
                <!-- Task Stats -->
                <div class="stats-grid" style="margin-bottom: var(--space-6);">
                    <div class="stat-card orange">
                        <div class="stat-card-header">
                            <div>
                                <div class="stat-label">To Do</div>
                                <div class="stat-value"><?php echo count($tasksByStatus['todo']); ?></div>
                            </div>
                            <div class="stat-icon">📝</div>
                        </div>
                    </div>
                    <div class="stat-card blue">
                        <div class="stat-card-header">
                            <div>
                                <div class="stat-label">In Progress</div>
                                <div class="stat-value"><?php echo count($tasksByStatus['in_progress']); ?></div>
                            </div>
                            <div class="stat-icon">🔄</div>
                        </div>
                    </div>
                    <div class="stat-card purple">
                        <div class="stat-card-header">
                            <div>
                                <div class="stat-label">In Review</div>
                                <div class="stat-value"><?php echo count($tasksByStatus['review']); ?></div>
                            </div>
                            <div class="stat-icon">👀</div>
                        </div>
                    </div>
                    <div class="stat-card green">
                        <div class="stat-card-header">
                            <div>
                                <div class="stat-label">Completed</div>
                                <div class="stat-value"><?php echo count($tasksByStatus['completed']); ?></div>
                            </div>
                            <div class="stat-icon">✅</div>
                        </div>
                    </div>
                </div>

                <!-- All Tasks Table -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3>All Tasks</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($tasks)): ?>
                            <p style="text-align: center; color: var(--text-secondary); padding: var(--space-10);">
                                No tasks found
                            </p>
                        <?php else: ?>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Task</th>
                                        <th>Project</th>
                                        <th>Assigned To</th>
                                        <th>Priority</th>
                                        <th>Status</th>
                                        <th>Due Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tasks as $task): ?>
                                    <tr class="<?php echo isOverdue($task['due_date'], $task['status']) ? 'overdue' : ''; ?>">
                                        <td>
                                            <strong><?php echo e($task['task_name']); ?></strong>
                                            <?php if ($task['description']): ?>
                                                <br><small style="color: var(--text-secondary);">
                                                    <?php echo e(substr($task['description'], 0, 50)) . (strlen($task['description']) > 50 ? '...' : ''); ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo e($task['project_name']); ?></td>
                                        <td><?php echo e($task['assigned_to_name'] ?? 'Unassigned'); ?></td>
                                        <td>
                                            <span class="badge <?php echo getPriorityClass($task['priority']); ?>">
                                                <?php echo ucfirst($task['priority']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo getStatusClass($task['status']); ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $task['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($task['due_date']): ?>
                                                <?php echo date('M d, Y', strtotime($task['due_date'])); ?>
                                                <?php if (isOverdue($task['due_date'], $task['status'])): ?>
                                                    <br><span style="color: var(--status-blocked); font-size: var(--font-xs);">⚠️ Overdue</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span style="color: var(--text-tertiary);">No due date</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div style="display: flex; gap: var(--space-2);">
                                                <button onclick="editTask(<?php echo $task['id']; ?>)" class="btn btn-secondary btn-sm">
                                                    Edit
                                                </button>
                                                <button onclick="deleteTask(<?php echo $task['id']; ?>, '<?php echo addslashes($task['task_name']); ?>')" class="btn btn-danger btn-sm">
                                                    Delete
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Task Modal -->
    <div id="editTaskModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: var(--bg-primary); padding: var(--space-6); border-radius: var(--radius-lg); width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-4);">
                <h3 style="margin: 0;">Edit Task</h3>
                <button onclick="closeTaskModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: var(--text-secondary);">&times;</button>
            </div>

            <form id="editTaskForm" onsubmit="saveTask(event)">
                <input type="hidden" id="edit_task_id">

                <div style="margin-bottom: var(--space-4);">
                    <label style="display: block; margin-bottom: var(--space-2); font-weight: 500;">Task Name *</label>
                    <input type="text" id="edit_task_name" required style="width: 100%; padding: var(--space-2); border: 1px solid var(--border-color); border-radius: var(--radius-md); background: var(--bg-secondary);">
                </div>

                <div style="margin-bottom: var(--space-4);">
                    <label style="display: block; margin-bottom: var(--space-2); font-weight: 500;">Description</label>
                    <textarea id="edit_task_description" rows="3" style="width: 100%; padding: var(--space-2); border: 1px solid var(--border-color); border-radius: var(--radius-md); background: var(--bg-secondary);"></textarea>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4); margin-bottom: var(--space-4);">
                    <div>
                        <label style="display: block; margin-bottom: var(--space-2); font-weight: 500;">Project</label>
                        <select id="edit_task_project" disabled style="width: 100%; padding: var(--space-2); border: 1px solid var(--border-color); border-radius: var(--radius-md); background: var(--bg-secondary); opacity: 0.6;">
                            <?php foreach ($managerProjects as $project): ?>
                                <option value="<?php echo $project['id']; ?>"><?php echo e($project['project_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color: var(--text-secondary); font-size: var(--font-xs);">Project cannot be changed</small>
                    </div>

                    <div>
                        <label style="display: block; margin-bottom: var(--space-2); font-weight: 500;">Assigned To</label>
                        <select id="edit_task_assigned_to" style="width: 100%; padding: var(--space-2); border: 1px solid var(--border-color); border-radius: var(--radius-md); background: var(--bg-secondary);">
                            <option value="">Unassigned</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>"><?php echo e($user['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4); margin-bottom: var(--space-4);">
                    <div>
                        <label style="display: block; margin-bottom: var(--space-2); font-weight: 500;">Status</label>
                        <select id="edit_task_status" style="width: 100%; padding: var(--space-2); border: 1px solid var(--border-color); border-radius: var(--radius-md); background: var(--bg-secondary);">
                            <option value="todo">To Do</option>
                            <option value="in_progress">In Progress</option>
                            <option value="review">In Review</option>
                            <option value="completed">Completed</option>
                            <option value="blocked">Blocked</option>
                        </select>
                    </div>

                    <div>
                        <label style="display: block; margin-bottom: var(--space-2); font-weight: 500;">Priority</label>
                        <select id="edit_task_priority" style="width: 100%; padding: var(--space-2); border: 1px solid var(--border-color); border-radius: var(--radius-md); background: var(--bg-secondary);">
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4); margin-bottom: var(--space-4);">
                    <div>
                        <label style="display: block; margin-bottom: var(--space-2); font-weight: 500;">Due Date</label>
                        <input type="date" id="edit_task_due_date" style="width: 100%; padding: var(--space-2); border: 1px solid var(--border-color); border-radius: var(--radius-md); background: var(--bg-secondary);">
                    </div>

                    <div>
                        <label style="display: block; margin-bottom: var(--space-2); font-weight: 500;">Estimated Hours</label>
                        <input type="number" id="edit_task_estimated_hours" min="0" step="0.5" style="width: 100%; padding: var(--space-2); border: 1px solid var(--border-color); border-radius: var(--radius-md); background: var(--bg-secondary);">
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: var(--space-3); margin-top: var(--space-6);">
                    <button type="button" onclick="closeTaskModal()" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/theme.js"></script>
    <script>
        // Store task data for editing
        const tasksData = <?php echo json_encode($tasks); ?>;

        // Edit Task
        function editTask(taskId) {
            const task = tasksData.find(t => t.id == taskId);
            if (!task) {
                alert('Task not found');
                return;
            }

            document.getElementById('edit_task_id').value = task.id;
            document.getElementById('edit_task_name').value = task.task_name;
            document.getElementById('edit_task_description').value = task.description || '';
            document.getElementById('edit_task_project').value = task.project_id;
            document.getElementById('edit_task_assigned_to').value = task.assigned_to || '';
            document.getElementById('edit_task_status').value = task.status;
            document.getElementById('edit_task_priority').value = task.priority;
            document.getElementById('edit_task_due_date').value = task.due_date || '';
            document.getElementById('edit_task_estimated_hours').value = task.estimated_hours || '';

            document.getElementById('editTaskModal').style.display = 'flex';
        }

        // Save Task
        async function saveTask(event) {
            event.preventDefault();

            const taskData = {
                id: parseInt(document.getElementById('edit_task_id').value),
                task_name: document.getElementById('edit_task_name').value,
                description: document.getElementById('edit_task_description').value,
                assigned_to: parseInt(document.getElementById('edit_task_assigned_to').value) || 0,
                status: document.getElementById('edit_task_status').value,
                priority: document.getElementById('edit_task_priority').value,
                due_date: document.getElementById('edit_task_due_date').value,
                estimated_hours: parseFloat(document.getElementById('edit_task_estimated_hours').value) || 0
            };

            try {
                const response = await fetch('../api/tasks.php', {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(taskData)
                });

                const data = await response.json();

                if (data.success) {
                    alert('Task updated successfully!');
                    location.reload();
                } else {
                    alert('Error updating task: ' + data.message);
                }
            } catch (error) {
                alert('Error updating task: ' + error.message);
            }
        }

        // Delete Task
        async function deleteTask(taskId, taskName) {
            if (!confirm(`Are you sure you want to delete "${taskName}"?\n\nThis action cannot be undone. All time logs and comments for this task will also be deleted.`)) {
                return;
            }

            try {
                const response = await fetch('../api/tasks.php', {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ id: taskId })
                });

                const data = await response.json();

                if (data.success) {
                    alert('Task deleted successfully!');
                    location.reload();
                } else {
                    alert('Error deleting task: ' + data.message);
                }
            } catch (error) {
                alert('Error deleting task: ' + error.message);
            }
        }

        // Close Modal
        function closeTaskModal() {
            document.getElementById('editTaskModal').style.display = 'none';
        }

        // Close modal on outside click
        document.getElementById('editTaskModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeTaskModal();
            }
        });
    </script>
</body>
</html>
