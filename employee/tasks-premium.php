<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !hasRole('employee')) {
    redirect('../login.php');
}

$db = getDBConnection();
$currentUser = getCurrentUser();

// Get view preference
$view = $_GET['view'] ?? 'kanban'; // kanban, list, calendar

// Get filter parameters
$statusFilter = $_GET['status'] ?? 'all';
$projectFilter = $_GET['project'] ?? 'all';
$priorityFilter = $_GET['priority'] ?? 'all';

// Get my tasks with filters
$query = "
    SELECT t.*, p.project_name, p.client_name, p.status as project_status,
           (SELECT COUNT(*) FROM task_dependencies WHERE task_id = t.id) as dependencies_count,
           (SELECT SUM(duration_minutes) FROM time_logs WHERE task_id = t.id AND end_time IS NOT NULL) as time_spent
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

if ($priorityFilter !== 'all') {
    $query .= " AND t.priority = ?";
    $params[] = $priorityFilter;
}

$query .= " ORDER BY FIELD(t.status, 'in_progress', 'todo', 'review', 'blocked', 'completed'), t.priority DESC, t.due_date ASC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$tasks = $stmt->fetchAll();

// Group tasks by status for Kanban view
$tasksByStatus = [
    'todo' => [],
    'in_progress' => [],
    'review' => [],
    'completed' => []
];

foreach ($tasks as $task) {
    $status = $task['status'];
    if (isset($tasksByStatus[$status])) {
        $tasksByStatus[$status][] = $task;
    } elseif ($status === 'blocked') {
        $tasksByStatus['todo'][] = $task; // Show blocked in todo column
    } else {
        $tasksByStatus['completed'][] = $task;
    }
}

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
    'todo' => count($tasksByStatus['todo']),
    'in_progress' => count($tasksByStatus['in_progress']),
    'review' => count($tasksByStatus['review']),
    'completed' => count($tasksByStatus['completed'])
];

// Calculate completion rate
$totalTasks = array_sum($stats);
$completionRate = $totalTasks > 0 ? round(($stats['completed'] / $totalTasks) * 100) : 0;

// Check active timer
$activeTimeLog = getActiveTimeLog($currentUser['id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Tasks - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/ultra-premium.css">
    <style>
    /* Task-specific premium styles */
    .tasks-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: var(--space-8);
    }

    .tasks-views {
        display: flex;
        gap: var(--space-2);
        background: var(--bg-tertiary);
        padding: var(--space-1);
        border-radius: var(--radius-md);
    }

    .view-btn {
        padding: var(--space-2) var(--space-4);
        border: none;
        background: transparent;
        color: var(--text-secondary);
        font-size: var(--font-sm);
        font-weight: 600;
        border-radius: var(--radius-sm);
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .view-btn.active {
        background: var(--bg-secondary);
        color: var(--primary);
        box-shadow: var(--shadow-xs);
    }

    .tasks-filters {
        display: flex;
        gap: var(--space-4);
        margin-bottom: var(--space-8);
        padding: var(--space-6);
        background: var(--bg-secondary);
        border: 1px solid var(--border-light);
        border-radius: var(--radius-xl);
    }

    .filter-group {
        flex: 1;
    }

    .filter-group label {
        display: block;
        font-size: var(--font-xs);
        font-weight: 700;
        color: var(--text-tertiary);
        text-transform: uppercase;
        letter-spacing: 0.8px;
        margin-bottom: var(--space-2);
    }

    .filter-group select {
        width: 100%;
        padding: var(--space-2) var(--space-3);
        border: 1px solid var(--border);
        border-radius: var(--radius-md);
        font-size: var(--font-sm);
        background: var(--bg-primary);
        color: var(--text-primary);
    }

    /* Kanban Board */
    .kanban-board {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: var(--space-6);
        min-height: 600px;
    }

    .kanban-column {
        background: var(--bg-tertiary);
        border-radius: var(--radius-xl);
        padding: var(--space-4);
        min-height: 400px;
    }

    .kanban-column-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: var(--space-3) var(--space-4);
        margin-bottom: var(--space-4);
        background: var(--bg-secondary);
        border-radius: var(--radius-lg);
    }

    .kanban-column-title {
        font-size: var(--font-sm);
        font-weight: 700;
        color: var(--text-primary);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .kanban-column-count {
        background: var(--count-bg);
        color: var(--count-color);
        padding: var(--space-1) var(--space-3);
        border-radius: var(--radius-full);
        font-size: var(--font-xs);
        font-weight: 700;
    }

    .kanban-column.todo {
        --count-bg: rgba(245, 158, 11, 0.15);
        --count-color: #d97706;
    }

    .kanban-column.in-progress {
        --count-bg: rgba(59, 130, 246, 0.15);
        --count-color: #2563eb;
    }

    .kanban-column.review {
        --count-bg: rgba(139, 92, 246, 0.15);
        --count-color: #7c3aed;
    }

    .kanban-column.completed {
        --count-bg: rgba(16, 185, 129, 0.15);
        --count-color: #059669;
    }

    .kanban-cards {
        display: flex;
        flex-direction: column;
        gap: var(--space-3);
    }

    /* Task Card - Premium */
    .task-card {
        background: var(--bg-secondary);
        border: 1px solid var(--border-light);
        border-radius: var(--radius-lg);
        padding: var(--space-4);
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
    }

    .task-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
        border-color: var(--primary);
    }

    .task-card-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: var(--space-3);
    }

    .task-priority {
        width: 24px;
        height: 24px;
        border-radius: var(--radius-sm);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: var(--font-xs);
    }

    .task-priority.low {
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.15) 0%, rgba(5, 150, 105, 0.15) 100%);
        color: #059669;
    }

    .task-priority.medium {
        background: linear-gradient(135deg, rgba(6, 182, 212, 0.15) 0%, rgba(8, 145, 178, 0.15) 100%);
        color: #0891b2;
    }

    .task-priority.high {
        background: linear-gradient(135deg, rgba(245, 158, 11, 0.15) 0%, rgba(217, 119, 6, 0.15) 100%);
        color: #d97706;
    }

    .task-priority.urgent {
        background: linear-gradient(135deg, rgba(239, 68, 68, 0.15) 0%, rgba(220, 38, 38, 0.15) 100%);
        color: #dc2626;
    }

    .task-title {
        font-size: var(--font-sm);
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: var(--space-2);
        line-height: 1.4;
    }

    .task-project {
        font-size: var(--font-xs);
        color: var(--text-tertiary);
        margin-bottom: var(--space-3);
    }

    .task-meta {
        display: flex;
        gap: var(--space-4);
        margin-bottom: var(--space-3);
        font-size: var(--font-xs);
        color: var(--text-secondary);
    }

    .task-meta-item {
        display: flex;
        align-items: center;
        gap: var(--space-1);
    }

    .task-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-top: var(--space-3);
        border-top: 1px solid var(--border-light);
    }

    .task-actions {
        display: flex;
        gap: var(--space-2);
    }

    .task-action-btn {
        padding: var(--space-2) var(--space-3);
        border: 1px solid var(--border);
        background: var(--bg-primary);
        color: var(--text-primary);
        font-size: var(--font-xs);
        font-weight: 600;
        border-radius: var(--radius-sm);
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .task-action-btn:hover {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
    }

    .task-due-date {
        font-size: var(--font-xs);
        color: var(--text-tertiary);
    }

    .task-due-date.overdue {
        color: #dc2626;
        font-weight: 600;
    }

    /* Empty State */
    .empty-column {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: var(--space-10);
        color: var(--text-tertiary);
        text-align: center;
    }

    .empty-column-icon {
        font-size: 48px;
        margin-bottom: var(--space-4);
        opacity: 0.5;
    }

    .empty-column-text {
        font-size: var(--font-sm);
    }

    /* Active Timer Banner */
    .active-timer-banner {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: var(--space-4);
        border-radius: var(--radius-lg);
        margin-bottom: var(--space-6);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .timer-info {
        display: flex;
        align-items: center;
        gap: var(--space-4);
    }

    .timer-pulse {
        width: 12px;
        height: 12px;
        background: white;
        border-radius: 50%;
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }

    /* Responsive */
    @media (max-width: 1200px) {
        .kanban-board {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 768px) {
        .kanban-board {
            grid-template-columns: 1fr;
        }
    }
    </style>
    <?php include '../includes/quick-actions-assets.php'; ?>
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
                <a href="tasks-premium.php" class="active">
                    <span class="icon">✓</span>
                    My Tasks
                </a>
                <a href="calendar.php">
                    <span class="icon">📅</span>
                    Calendar
                </a>
                <a href="chat.php">
                    <span class="icon">💬</span>
                    Team Chat
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
                <h1>✓ My Tasks</h1>
                <div class="topbar-actions">
                    <?php include '../includes/notifications-dropdown.php'; ?>
                </div>
            </div>

            <div class="content">
                <!-- Active Timer Banner -->
                <?php if ($activeTimeLog): ?>
                <div class="active-timer-banner">
                    <div class="timer-info">
                        <div class="timer-pulse"></div>
                        <span>Timer active on task</span>
                    </div>
                    <button class="btn btn-secondary btn-sm" onclick="location.href='index.php#timer'">View Timer</button>
                </div>
                <?php endif; ?>

                <!-- Stats -->
                <div class="stats-grid">
                    <div class="stat-card orange">
                        <div class="stat-card-header">
                            <div>
                                <div class="stat-label">To Do</div>
                                <div class="stat-value"><?php echo $stats['todo']; ?></div>
                                <div class="stat-change">Pending tasks</div>
                            </div>
                            <div class="stat-icon">📝</div>
                        </div>
                    </div>

                    <div class="stat-card blue">
                        <div class="stat-card-header">
                            <div>
                                <div class="stat-label">In Progress</div>
                                <div class="stat-value"><?php echo $stats['in_progress']; ?></div>
                                <div class="stat-change">Working on</div>
                            </div>
                            <div class="stat-icon">🚀</div>
                        </div>
                    </div>

                    <div class="stat-card purple">
                        <div class="stat-card-header">
                            <div>
                                <div class="stat-label">In Review</div>
                                <div class="stat-value"><?php echo $stats['review']; ?></div>
                                <div class="stat-change">Awaiting feedback</div>
                            </div>
                            <div class="stat-icon">👀</div>
                        </div>
                    </div>

                    <div class="stat-card green">
                        <div class="stat-card-header">
                            <div>
                                <div class="stat-label">Completed</div>
                                <div class="stat-value"><?php echo $stats['completed']; ?></div>
                                <div class="stat-change"><?php echo $completionRate; ?>% completion</div>
                            </div>
                            <div class="stat-icon">✅</div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <form class="tasks-filters" method="GET">
                    <div class="filter-group">
                        <label>Project</label>
                        <select name="project" onchange="this.form.submit()">
                            <option value="all">All Projects</option>
                            <?php foreach ($projects as $project): ?>
                            <option value="<?php echo $project['id']; ?>" <?php echo $projectFilter == $project['id'] ? 'selected' : ''; ?>>
                                <?php echo e($project['project_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Priority</label>
                        <select name="priority" onchange="this.form.submit()">
                            <option value="all">All Priorities</option>
                            <option value="low" <?php echo $priorityFilter == 'low' ? 'selected' : ''; ?>>Low</option>
                            <option value="medium" <?php echo $priorityFilter == 'medium' ? 'selected' : ''; ?>>Medium</option>
                            <option value="high" <?php echo $priorityFilter == 'high' ? 'selected' : ''; ?>>High</option>
                            <option value="urgent" <?php echo $priorityFilter == 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                        </select>
                    </div>
                    <input type="hidden" name="view" value="<?php echo $view; ?>">
                </form>

                <!-- Kanban Board -->
                <div class="kanban-board">
                    <!-- To Do Column -->
                    <div class="kanban-column todo">
                        <div class="kanban-column-header">
                            <span class="kanban-column-title">📝 To Do</span>
                            <span class="kanban-column-count"><?php echo count($tasksByStatus['todo']); ?></span>
                        </div>
                        <div class="kanban-cards">
                            <?php if (empty($tasksByStatus['todo'])): ?>
                                <div class="empty-column">
                                    <div class="empty-column-icon">🎉</div>
                                    <div class="empty-column-text">No pending tasks</div>
                                </div>
                            <?php else: ?>
                                <?php foreach ($tasksByStatus['todo'] as $task): ?>
                                    <?php include '../includes/task-card.php'; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- In Progress Column -->
                    <div class="kanban-column in-progress">
                        <div class="kanban-column-header">
                            <span class="kanban-column-title">🚀 In Progress</span>
                            <span class="kanban-column-count"><?php echo count($tasksByStatus['in_progress']); ?></span>
                        </div>
                        <div class="kanban-cards">
                            <?php if (empty($tasksByStatus['in_progress'])): ?>
                                <div class="empty-column">
                                    <div class="empty-column-icon">💼</div>
                                    <div class="empty-column-text">Start working on tasks</div>
                                </div>
                            <?php else: ?>
                                <?php foreach ($tasksByStatus['in_progress'] as $task): ?>
                                    <?php include '../includes/task-card.php'; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- In Review Column -->
                    <div class="kanban-column review">
                        <div class="kanban-column-header">
                            <span class="kanban-column-title">👀 In Review</span>
                            <span class="kanban-column-count"><?php echo count($tasksByStatus['review']); ?></span>
                        </div>
                        <div class="kanban-cards">
                            <?php if (empty($tasksByStatus['review'])): ?>
                                <div class="empty-column">
                                    <div class="empty-column-icon">📋</div>
                                    <div class="empty-column-text">No tasks in review</div>
                                </div>
                            <?php else: ?>
                                <?php foreach ($tasksByStatus['review'] as $task): ?>
                                    <?php include '../includes/task-card.php'; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Completed Column -->
                    <div class="kanban-column completed">
                        <div class="kanban-column-header">
                            <span class="kanban-column-title">✅ Completed</span>
                            <span class="kanban-column-count"><?php echo count($tasksByStatus['completed']); ?></span>
                        </div>
                        <div class="kanban-cards">
                            <?php if (empty($tasksByStatus['completed'])): ?>
                                <div class="empty-column">
                                    <div class="empty-column-icon">🎯</div>
                                    <div class="empty-column-text">Complete tasks to see them here</div>
                                </div>
                            <?php else: ?>
                                <?php foreach (array_slice($tasksByStatus['completed'], 0, 5) as $task): ?>
                                    <?php include '../includes/task-card.php'; ?>
                                <?php endforeach; ?>
                                <?php if (count($tasksByStatus['completed']) > 5): ?>
                                    <div style="text-align: center; padding: var(--space-4); color: var(--text-tertiary); font-size: var(--font-xs);">
                                        +<?php echo count($tasksByStatus['completed']) - 5; ?> more completed
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/theme.js"></script>
    <script>
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
                alert('Timer started!');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }
    </script>
</body>
</html>
