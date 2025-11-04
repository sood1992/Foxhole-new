<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !hasRole('employee')) {
    redirect('../login.php');
}

$db = getDBConnection();
$currentUser = getCurrentUser();

// Get filter parameters
$statusFilter = $_GET['status'] ?? 'all';
$projectFilter = $_GET['project'] ?? 'all';

// Get my tasks with filters
$query = "
    SELECT t.*, p.project_name, p.client_name, p.status as project_status
    FROM tasks t
    JOIN projects p ON t.project_id = p.id
    WHERE t.assigned_to = ?
";

$params = [$currentUser['id']];

if ($statusFilter !== 'all') {
    $query .= " AND t.status = ?";
    $params[] = $statusFilter;
}

if ($projectFilter !== 'all') {
    $query .= " AND t.project_id = ?";
    $params[] = $projectFilter;
}

$query .= " ORDER BY FIELD(t.status, 'in_progress', 'todo', 'review', 'blocked', 'completed'), t.priority DESC, t.due_date ASC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$tasks = $stmt->fetchAll();

// Get my projects for filter
$myProjects = $db->prepare("
    SELECT DISTINCT p.id, p.project_name
    FROM projects p
    JOIN tasks t ON p.id = t.project_id
    WHERE t.assigned_to = ?
    ORDER BY p.project_name
");
$myProjects->execute([$currentUser['id']]);
$projects = $myProjects->fetchAll();

// Get statistics
$stats = [
    'todo' => 0,
    'in_progress' => 0,
    'review' => 0,
    'completed_today' => 0
];

foreach ($tasks as $task) {
    if ($task['status'] === 'todo') $stats['todo']++;
    if ($task['status'] === 'in_progress') $stats['in_progress']++;
    if ($task['status'] === 'review') $stats['review']++;
    if ($task['status'] === 'completed' && date('Y-m-d', strtotime($task['completed_date'])) === date('Y-m-d')) {
        $stats['completed_today']++;
    }
}

// Get all active task IDs (for multiple simultaneous tasks)
$stmt = $db->prepare("SELECT DISTINCT task_id FROM time_logs WHERE user_id = ? AND is_active = 1");
$stmt->execute([$currentUser['id']]);
$activeTaskIds = array_column($stmt->fetchAll(), 'task_id');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Tasks V3 - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/vien-v3.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <?php include '../includes/v3-employee-sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <?php include '../includes/v3-header.php'; ?>

            <div class="content-wrapper">
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success" style="margin-bottom: 24px;">
                        <?php
                        echo e($_SESSION['success_message']);
                        unset($_SESSION['success_message']);
                        ?>
                    </div>
                <?php endif; ?>

                <!-- Quick Stats -->
                <div class="stats-grid">
                    <div class="dashboard-card">
                        <div class="card-icon gradient-blue">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                        <div class="card-content">
                            <div class="card-label">To Do</div>
                            <div class="card-value"><?php echo $stats['todo']; ?></div>
                        </div>
                    </div>

                    <div class="dashboard-card">
                        <div class="card-icon gradient-orange">
                            <i class="fas fa-spinner"></i>
                        </div>
                        <div class="card-content">
                            <div class="card-label">In Progress</div>
                            <div class="card-value"><?php echo $stats['in_progress']; ?></div>
                        </div>
                    </div>

                    <div class="dashboard-card">
                        <div class="card-icon gradient-purple">
                            <i class="fas fa-eye"></i>
                        </div>
                        <div class="card-content">
                            <div class="card-label">In Review</div>
                            <div class="card-value"><?php echo $stats['review']; ?></div>
                        </div>
                    </div>

                    <div class="dashboard-card">
                        <div class="card-icon gradient-green">
                            <i class="fas fa-check-double"></i>
                        </div>
                        <div class="card-content">
                            <div class="card-label">Done Today</div>
                            <div class="card-value"><?php echo $stats['completed_today']; ?></div>
                        </div>
                    </div>
                </div>

                <!-- Active Tasks Alert -->
                <?php if (!empty($activeTaskIds)): ?>
                <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 16px 20px; border-radius: 12px; margin-bottom: 24px; box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <i class="fas fa-clock" style="font-size: 24px;"></i>
                        <div style="flex: 1;">
                            <strong style="font-size: 15px;">Background Tracking Active</strong>
                            <p style="margin: 4px 0 0; opacity: 0.9; font-size: 13px;">
                                Currently working on <?php echo count($activeTaskIds); ?> <?php echo count($activeTaskIds) == 1 ? 'task' : 'tasks'; ?>. Time is being tracked automatically in the background.
                            </p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Filters -->
                <div class="dashboard-card">
                    <div class="card-body">
                        <form method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; align-items: end;">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label>Filter by Status</label>
                                <select name="status" class="form-control" onchange="this.form.submit()">
                                    <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                                    <option value="todo" <?php echo $statusFilter === 'todo' ? 'selected' : ''; ?>>To Do</option>
                                    <option value="in_progress" <?php echo $statusFilter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                    <option value="review" <?php echo $statusFilter === 'review' ? 'selected' : ''; ?>>In Review</option>
                                    <option value="blocked" <?php echo $statusFilter === 'blocked' ? 'selected' : ''; ?>>Blocked</option>
                                    <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                </select>
                            </div>

                            <div class="form-group" style="margin-bottom: 0;">
                                <label>Filter by Project</label>
                                <select name="project" class="form-control" onchange="this.form.submit()">
                                    <option value="all" <?php echo $projectFilter === 'all' ? 'selected' : ''; ?>>All Projects</option>
                                    <?php foreach ($projects as $proj): ?>
                                        <option value="<?php echo $proj['id']; ?>" <?php echo $projectFilter == $proj['id'] ? 'selected' : ''; ?>>
                                            <?php echo e($proj['project_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <a href="tasks.php" class="btn btn-secondary">Clear Filters</a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Tasks List -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3>Tasks (<?php echo count($tasks); ?>)</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($tasks)): ?>
                            <div style="text-align: center; padding: 60px 20px; color: var(--text-secondary);">
                                <div style="font-size: 64px; margin-bottom: 16px;">📋</div>
                                <h3>No tasks found</h3>
                                <p>You don't have any tasks matching these filters.</p>
                            </div>
                        <?php else: ?>
                            <div class="task-list">
                                <?php foreach ($tasks as $task): ?>
                                <?php
                                    // Check if this specific task is currently being worked on
                                    $isTaskActive = in_array($task['id'], $activeTaskIds);
                                    $isOverdue = isOverdue($task['due_date'], $task['status']);
                                ?>
                                <div class="task-item <?php echo $isOverdue ? 'overdue' : ''; ?>">
                                    <div class="task-item-header">
                                        <div style="flex: 1;">
                                            <div class="task-item-title">
                                                <a href="task-detail.php?id=<?php echo $task['id']; ?>" style="color: inherit; text-decoration: none;">
                                                    <?php echo e($task['task_name']); ?>
                                                </a>
                                            </div>
                                            <div class="task-item-meta">
                                                <span>📁 <?php echo e($task['project_name']); ?></span>
                                                <?php if ($task['client_name']): ?>
                                                    <span>👤 <?php echo e($task['client_name']); ?></span>
                                                <?php endif; ?>
                                                <span class="badge <?php echo getStatusClass($task['status']); ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $task['status'])); ?>
                                                </span>
                                                <span class="badge <?php echo getPriorityClass($task['priority']); ?>">
                                                    <?php echo ucfirst($task['priority']); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="task-item-actions">
                                            <a href="task-detail.php?id=<?php echo $task['id']; ?>" class="btn btn-secondary btn-sm">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            <?php if ($task['status'] !== 'completed'): ?>
                                                <?php if (!$isTaskActive): ?>
                                                    <button onclick="startTimer(<?php echo $task['id']; ?>, <?php echo $task['project_id']; ?>)"
                                                            class="btn btn-success btn-sm">
                                                        ▶️ Start Working
                                                    </button>
                                                <?php else: ?>
                                                    <button onclick="stopTask(<?php echo $task['id']; ?>)"
                                                            class="btn btn-warning btn-sm">
                                                        ⏹ Stop Working
                                                    </button>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <?php if ($task['description']): ?>
                                    <div style="margin-top: 12px; padding: 12px; background: var(--bg-tertiary); border-radius: var(--radius-sm); font-size: 14px;">
                                        <?php echo nl2br(e($task['description'])); ?>
                                    </div>
                                    <?php endif; ?>

                                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 16px; margin-top: 16px;">
                                        <div>
                                            <div style="font-size: 12px; color: var(--text-secondary);">Estimated Hours</div>
                                            <div style="font-size: 16px; font-weight: 600;">
                                                <?php echo $task['estimated_hours'] ? number_format($task['estimated_hours'], 1) . 'h' : 'N/A'; ?>
                                            </div>
                                        </div>
                                        <div>
                                            <div style="font-size: 12px; color: var(--text-secondary);">Actual Hours</div>
                                            <div style="font-size: 16px; font-weight: 600;">
                                                <?php echo formatHours($task['actual_hours'] ?? 0); ?>h
                                            </div>
                                        </div>
                                        <div>
                                            <div style="font-size: 12px; color: var(--text-secondary);">Due Date</div>
                                            <div style="font-size: 14px; font-weight: 500;">
                                                <?php
                                                if ($task['due_date']) {
                                                    echo date('M d, Y', strtotime($task['due_date']));
                                                    if ($isOverdue) {
                                                        echo ' <span style="color: var(--red);">⚠️ Overdue</span>';
                                                    }
                                                } else {
                                                    echo 'No deadline';
                                                }
                                                ?>
                                            </div>
                                        </div>
                                        <?php if ($task['status'] === 'completed' && $task['completed_date']): ?>
                                        <div>
                                            <div style="font-size: 12px; color: var(--text-secondary);">Completed</div>
                                            <div style="font-size: 14px; font-weight: 500; color: var(--green);">
                                                ✓ <?php echo date('M d, Y', strtotime($task['completed_date'])); ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script>
        // Background task tracking functions (no visible timer)
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
                    // Show success message
                    const message = document.createElement('div');
                    message.style.cssText = 'position: fixed; top: 20px; right: 20px; background: #10b981; color: white; padding: 16px 24px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 9999;';
                    message.innerHTML = '<i class="fas fa-check-circle"></i> Task started - tracking in background';
                    document.body.appendChild(message);
                    setTimeout(() => {
                        message.remove();
                        window.location.reload();
                    }, 1500);
                } else {
                    alert(data.message || 'Failed to start task');
                }
            })
            .catch(error => {
                alert('Error starting task');
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

                        alert(`Task stopped! Total time: ${timeStr}`);
                        window.location.reload();
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
    </script>
    <script src="../assets/js/theme.js"></script>
</body>
</html>
