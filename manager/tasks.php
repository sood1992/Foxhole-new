<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !hasRole('manager')) {
    redirect('../login.php');
}

$db = getDBConnection();
$currentUser = getCurrentUser();

// Get all tasks for manager's projects (including multi-manager assignments)
$stmt = $db->prepare("
    SELECT DISTINCT t.*, p.project_name, u.full_name as assigned_to_name
    FROM tasks t
    JOIN projects p ON t.project_id = p.id
    LEFT JOIN users u ON t.assigned_to = u.id
    LEFT JOIN project_managers pm ON p.id = pm.project_id
    WHERE p.assigned_manager = ? OR pm.manager_id = ?
    ORDER BY
        FIELD(t.status, 'blocked', 'in_progress', 'review', 'todo', 'completed'),
        t.priority = 'urgent' DESC,
        t.priority = 'high' DESC,
        t.due_date ASC
");
$stmt->execute([$currentUser['id'], $currentUser['id']]);
$tasks = $stmt->fetchAll();

// Get all employees for assignment dropdowns
$employees = $db->query("
    SELECT id, full_name, job_title
    FROM users
    WHERE role IN ('employee', 'manager') AND is_active = 1
    ORDER BY full_name
")->fetchAll();

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
                                        <td>
                                            <select class="form-control quick-update"
                                                    data-task-id="<?php echo $task['id']; ?>"
                                                    data-field="assigned_to"
                                                    style="min-width: 150px;">
                                                <?php foreach ($employees as $employee): ?>
                                                    <option value="<?php echo $employee['id']; ?>"
                                                            <?php echo $task['assigned_to'] == $employee['id'] ? 'selected' : ''; ?>>
                                                        <?php echo e($employee['full_name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <select class="form-control quick-update"
                                                    data-task-id="<?php echo $task['id']; ?>"
                                                    data-field="priority"
                                                    style="min-width: 120px;">
                                                <option value="low" <?php echo $task['priority'] == 'low' ? 'selected' : ''; ?>>
                                                    Low
                                                </option>
                                                <option value="medium" <?php echo $task['priority'] == 'medium' ? 'selected' : ''; ?>>
                                                    Medium
                                                </option>
                                                <option value="high" <?php echo $task['priority'] == 'high' ? 'selected' : ''; ?>>
                                                    High
                                                </option>
                                                <option value="urgent" <?php echo $task['priority'] == 'urgent' ? 'selected' : ''; ?>>
                                                    Urgent
                                                </option>
                                            </select>
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
                                            <a href="edit-task.php?id=<?php echo $task['id']; ?>" class="btn btn-primary btn-sm">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
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

    <script src="../assets/js/theme.js"></script>
    <script>
        // Quick update functionality for inline editing
        document.addEventListener('DOMContentLoaded', function() {
            const quickUpdateSelects = document.querySelectorAll('.quick-update');

            quickUpdateSelects.forEach(select => {
                select.addEventListener('change', async function() {
                    const taskId = this.dataset.taskId;
                    const field = this.dataset.field;
                    const value = this.value;
                    const originalValue = this.dataset.originalValue || this.value;

                    // Store original value for rollback
                    if (!this.dataset.originalValue) {
                        this.dataset.originalValue = originalValue;
                    }

                    // Disable the select while updating
                    this.disabled = true;
                    this.style.opacity = '0.6';

                    try {
                        const response = await fetch('../api/tasks.php', {
                            method: 'PATCH',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                task_id: taskId,
                                field: field,
                                value: value
                            })
                        });

                        const data = await response.json();

                        if (data.success) {
                            // Update the original value
                            this.dataset.originalValue = value;

                            // Show success feedback
                            this.style.borderColor = '#10b981';
                            setTimeout(() => {
                                this.style.borderColor = '';
                            }, 1000);

                            // Show toast notification
                            showToast('success', data.message || 'Updated successfully');
                        } else {
                            // Rollback to original value
                            this.value = this.dataset.originalValue;
                            showToast('error', data.message || 'Update failed');
                        }
                    } catch (error) {
                        console.error('Error updating task:', error);
                        // Rollback to original value
                        this.value = this.dataset.originalValue;
                        showToast('error', 'Failed to update. Please try again.');
                    } finally {
                        // Re-enable the select
                        this.disabled = false;
                        this.style.opacity = '1';
                    }
                });
            });
        });

        // Simple toast notification function
        function showToast(type, message) {
            const toast = document.createElement('div');
            toast.className = `alert alert-${type === 'success' ? 'success' : 'error'}`;
            toast.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 9999;
                min-width: 250px;
                animation: slideIn 0.3s ease-out;
            `;
            toast.textContent = message;

            document.body.appendChild(toast);

            setTimeout(() => {
                toast.style.animation = 'slideOut 0.3s ease-out';
                setTimeout(() => {
                    document.body.removeChild(toast);
                }, 300);
            }, 3000);
        }

        // Add animation styles
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from {
                    transform: translateX(400px);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
            @keyframes slideOut {
                from {
                    transform: translateX(0);
                    opacity: 1;
                }
                to {
                    transform: translateX(400px);
                    opacity: 0;
                }
            }
            .quick-update {
                cursor: pointer;
                transition: border-color 0.3s ease;
            }
            .quick-update:hover {
                border-color: #667eea;
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>
