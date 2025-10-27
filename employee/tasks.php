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

// Check active timer
$activeTimeLog = getActiveTimeLog($currentUser['id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Tasks - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/premium-theme.css">
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>Foxhole</h2>
                <div class="user-role">Employee Panel</div>
            </div>

            <nav class="sidebar-nav">
                <a href="index.php">
                    <span class="icon">📊</span>
                    Dashboard
                </a>
                <a href="tasks.php" class="active">
                    <span class="icon">✓</span>
                    My Tasks
                </a>
                <a href="time-logs.php">
                    <span class="icon">⏱️</span>
                    Time Logs
                </a>
                <a href="my-stats.php">
                    <span class="icon">📈</span>
                    My Statistics
                </a>
            </nav>

            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($currentUser['full_name'], 0, 1)); ?>
                    </div>
                    <div class="user-details">
                        <h4><?php echo e($currentUser['full_name']); ?></h4>
                        <p><?php echo e($currentUser['job_title'] ?? 'Employee'); ?></p>
                    </div>
                </div>
                <a href="../logout.php" class="btn btn-secondary btn-block btn-sm">Logout</a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="topbar">
                <h1>My Tasks</h1>
                <div class="topbar-actions">
                    <span style="color: var(--text-secondary); font-size: 14px;">
                        <?php echo date('l, F j, Y'); ?>
                    </span>
                </div>
            </div>

            <div class="content">
                <!-- Quick Stats -->
                <div class="stats-grid">
                    <div class="stat-card blue">
                        <div class="stat-card-header">
                            <div>
                                <div class="stat-label">To Do</div>
                                <div class="stat-value"><?php echo $stats['todo']; ?></div>
                            </div>
                            <div class="stat-icon">📋</div>
                        </div>
                    </div>

                    <div class="stat-card orange">
                        <div class="stat-card-header">
                            <div>
                                <div class="stat-label">In Progress</div>
                                <div class="stat-value"><?php echo $stats['in_progress']; ?></div>
                            </div>
                            <div class="stat-icon">⚙️</div>
                        </div>
                    </div>

                    <div class="stat-card purple">
                        <div class="stat-card-header">
                            <div>
                                <div class="stat-label">In Review</div>
                                <div class="stat-value"><?php echo $stats['review']; ?></div>
                            </div>
                            <div class="stat-icon">👁️</div>
                        </div>
                    </div>

                    <div class="stat-card green">
                        <div class="stat-card-header">
                            <div>
                                <div class="stat-label">Done Today</div>
                                <div class="stat-value"><?php echo $stats['completed_today']; ?></div>
                            </div>
                            <div class="stat-icon">✅</div>
                        </div>
                    </div>
                </div>

                <!-- Active Timer Alert -->
                <?php if ($activeTimeLog): ?>
                <div class="alert alert-info">
                    ⏱️ Timer is running for task: <strong><?php echo e($activeTimeLog['task_name'] ?? 'Unknown'); ?></strong>
                    <a href="index.php" style="margin-left: 10px;">Go to Dashboard to stop</a>
                </div>
                <?php endif; ?>

                <!-- Filters -->
                <div class="card">
                    <div class="card-body">
                        <form method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; align-items: end;">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label>Filter by Status</label>
                                <select name="status" onchange="this.form.submit()">
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
                                <select name="project" onchange="this.form.submit()">
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
                <div class="card">
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
                                    $canStartTimer = !$activeTimeLog || $activeTimeLog['task_id'] != $task['id'];
                                    $isOverdue = isOverdue($task['due_date'], $task['status']);
                                ?>
                                <div class="task-item <?php echo $isOverdue ? 'overdue' : ''; ?>">
                                    <div class="task-item-header">
                                        <div style="flex: 1;">
                                            <div class="task-item-title"><?php echo e($task['task_name']); ?></div>
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
                                            <?php if ($task['status'] !== 'completed'): ?>
                                                <?php if ($canStartTimer): ?>
                                                    <button onclick="startTimer(<?php echo $task['id']; ?>, <?php echo $task['project_id']; ?>)"
                                                            class="btn btn-success btn-sm">
                                                        ▶️ Start
                                                    </button>
                                                <?php else: ?>
                                                    <span class="badge status-progress">⏱️ Active</span>
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
        </main>
    </div>

    <script src="../assets/js/main.js"></script>
    <script>
        function startTimer(taskId, projectId) {
            if (confirm('Start tracking time for this task?')) {
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
                        window.location.href = 'index.php';
                    } else {
                        alert(data.message || 'Failed to start timer');
                    }
                })
                .catch(error => {
                    alert('Error starting timer');
                    console.error(error);
                });
            }
        }
    </script>
    <script src="../assets/js/theme.js"></script>
</body>
</html>
