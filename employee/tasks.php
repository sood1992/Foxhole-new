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

    <!-- Synto Dashboard Template Design -->
    <link rel="stylesheet" href="../assets/css/synto-design.css">
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
                            <i class="fas ri-loader-2-line"></i>
                        </div>
                        <div class="card-content">
                            <div class="card-label">In Progress</div>
                            <div class="card-value"><?php echo $stats['in_progress']; ?></div>
                        </div>
                    </div>

                    <div class="dashboard-card">
                        <div class="card-icon gradient-purple">
                            <i class="fas ri-eye-line"></i>
                        </div>
                        <div class="card-content">
                            <div class="card-label">In Review</div>
                            <div class="card-value"><?php echo $stats['review']; ?></div>
                        </div>
                    </div>

                    <div class="dashboard-card">
                        <div class="card-icon gradient-green">
                            <i class="fas ri-check-line-double"></i>
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
                        <i class="fas ri-time-line" style="font-size: 24px;"></i>
                        <div style="flex: 1;">
                            <strong style="font-size: 15px;">Background Tracking Active</strong>
                            <p style="margin: 4px 0 0; opacity: 0.9; font-size: 13px;">
                                Currently working on <?php echo count($activeTaskIds); ?> <?php echo count($activeTaskIds) == 1 ? 'task' : 'tasks'; ?>. Time is being tracked automatically in the background.
                            </p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- PRODUCTIVITY TOOLS - INTEGRATED INTO TASKS -->
                <div class="stats-card" style="margin-bottom: 24px; background: linear-gradient(135deg, #F9FAFB 0%, #FFFFFF 100%);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <div>
                            <h3 style="margin: 0; color: var(--synto-text-primary); font-size: 18px; font-weight: 600;">
                                <i class="ri-tools-line" style="color: var(--synto-primary);"></i> Productivity Tools
                            </h3>
                            <p style="margin: 4px 0 0; color: var(--synto-text-secondary); font-size: 13px;">
                                Boost your efficiency with powerful task management tools
                            </p>
                        </div>
                        <button onclick="toggleProductivityTools()" style="background: var(--synto-bg); border: 1px solid var(--synto-border); padding: 8px 12px; border-radius: 8px; cursor: pointer; color: var(--synto-text-secondary); transition: all 0.2s;" id="productivityToggle">
                            <i class="ri-arrow-down-s-line" id="productivityToggleIcon"></i>
                        </button>
                    </div>

                    <div id="productivityToolsContainer" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px;">
                        <!-- Daily Planning -->
                        <a href="daily-planning.php" style="text-decoration: none;">
                            <div style="padding: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px; transition: transform 0.2s, box-shadow 0.2s; cursor: pointer; box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 4px 16px rgba(102, 126, 234, 0.4)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(102, 126, 234, 0.3)';">
                                <i class="ri-calendar-check-line" style="font-size: 32px; color: white; display: block; margin-bottom: 12px;"></i>
                                <h4 style="margin: 0 0 6px; color: white; font-size: 16px; font-weight: 600;">Daily Planning</h4>
                                <p style="margin: 0; color: rgba(255,255,255,0.9); font-size: 13px; line-height: 1.4;">Plan your day with MIT (Most Important Tasks)</p>
                            </div>
                        </a>

                        <!-- Morning Ritual -->
                        <a href="morning-ritual.php" style="text-decoration: none;">
                            <div style="padding: 20px; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); border-radius: 12px; transition: transform 0.2s, box-shadow 0.2s; cursor: pointer; box-shadow: 0 2px 8px rgba(245, 87, 108, 0.3);" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 4px 16px rgba(245, 87, 108, 0.4)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(245, 87, 108, 0.3)';">
                                <i class="ri-sun-line" style="font-size: 32px; color: white; display: block; margin-bottom: 12px;"></i>
                                <h4 style="margin: 0 0 6px; color: white; font-size: 16px; font-weight: 600;">Morning Ritual</h4>
                                <p style="margin: 0; color: rgba(255,255,255,0.9); font-size: 13px; line-height: 1.4;">Start your day with structured routines</p>
                            </div>
                        </a>

                        <!-- Eisenhower Matrix -->
                        <a href="eisenhower-matrix.php" style="text-decoration: none;">
                            <div style="padding: 20px; background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); border-radius: 12px; transition: transform 0.2s, box-shadow 0.2s; cursor: pointer; box-shadow: 0 2px 8px rgba(79, 172, 254, 0.3);" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 4px 16px rgba(79, 172, 254, 0.4)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(79, 172, 254, 0.3)';">
                                <i class="ri-layout-grid-line" style="font-size: 32px; color: white; display: block; margin-bottom: 12px;"></i>
                                <h4 style="margin: 0 0 6px; color: white; font-size: 16px; font-weight: 600;">Eisenhower Matrix</h4>
                                <p style="margin: 0; color: rgba(255,255,255,0.9); font-size: 13px; line-height: 1.4;">Prioritize by urgency and importance</p>
                            </div>
                        </a>

                        <!-- Pomodoro Timer -->
                        <a href="pomodoro.php" style="text-decoration: none;">
                            <div style="padding: 20px; background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); border-radius: 12px; transition: transform 0.2s, box-shadow 0.2s; cursor: pointer; box-shadow: 0 2px 8px rgba(250, 112, 154, 0.3);" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 4px 16px rgba(250, 112, 154, 0.4)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(250, 112, 154, 0.3)';">
                                <i class="ri-timer-line" style="font-size: 32px; color: white; display: block; margin-bottom: 12px;"></i>
                                <h4 style="margin: 0 0 6px; color: white; font-size: 16px; font-weight: 600;">Pomodoro Timer</h4>
                                <p style="margin: 0; color: rgba(255,255,255,0.9); font-size: 13px; line-height: 1.4;">Work in focused 25-minute intervals</p>
                            </div>
                        </a>

                        <!-- Time Boxing -->
                        <a href="time-boxing.php" style="text-decoration: none;">
                            <div style="padding: 20px; background: linear-gradient(135deg, #30cfd0 0%, #330867 100%); border-radius: 12px; transition: transform 0.2s, box-shadow 0.2s; cursor: pointer; box-shadow: 0 2px 8px rgba(48, 207, 208, 0.3);" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 4px 16px rgba(48, 207, 208, 0.4)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(48, 207, 208, 0.3)';">
                                <i class="ri-calendar-event-line" style="font-size: 32px; color: white; display: block; margin-bottom: 12px;"></i>
                                <h4 style="margin: 0 0 6px; color: white; font-size: 16px; font-weight: 600;">Time Boxing</h4>
                                <p style="margin: 0; color: rgba(255,255,255,0.9); font-size: 13px; line-height: 1.4;">Allocate specific time blocks to tasks</p>
                            </div>
                        </a>

                        <!-- Focus Mode -->
                        <a href="focus-mode.php" style="text-decoration: none;">
                            <div style="padding: 20px; background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); border-radius: 12px; transition: transform 0.2s, box-shadow 0.2s; cursor: pointer; box-shadow: 0 2px 8px rgba(168, 237, 234, 0.3);" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 4px 16px rgba(168, 237, 234, 0.4)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(168, 237, 234, 0.3)';">
                                <i class="ri-focus-3-line" style="font-size: 32px; color: #667eea; display: block; margin-bottom: 12px;"></i>
                                <h4 style="margin: 0 0 6px; color: #667eea; font-size: 16px; font-weight: 600;">Focus Mode</h4>
                                <p style="margin: 0; color: #764ba2; font-size: 13px; line-height: 1.4;">Minimize distractions and deep work</p>
                            </div>
                        </a>

                        <!-- Goals Tracking -->
                        <a href="goals.php" style="text-decoration: none;">
                            <div style="padding: 20px; background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); border-radius: 12px; transition: transform 0.2s, box-shadow 0.2s; cursor: pointer; box-shadow: 0 2px 8px rgba(67, 233, 123, 0.3);" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 4px 16px rgba(67, 233, 123, 0.4)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(67, 233, 123, 0.3)';">
                                <i class="ri-flag-line" style="font-size: 32px; color: white; display: block; margin-bottom: 12px;"></i>
                                <h4 style="margin: 0 0 6px; color: white; font-size: 16px; font-weight: 600;">Goals Tracking</h4>
                                <p style="margin: 0; color: rgba(255,255,255,0.9); font-size: 13px; line-height: 1.4;">Set and track your long-term goals</p>
                            </div>
                        </a>

                        <!-- Weekly Review -->
                        <a href="weekly-review.php" style="text-decoration: none;">
                            <div style="padding: 20px; background: linear-gradient(135deg, #ff9a56 0%, #ff6a88 100%); border-radius: 12px; transition: transform 0.2s, box-shadow 0.2s; cursor: pointer; box-shadow: 0 2px 8px rgba(255, 154, 86, 0.3);" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 4px 16px rgba(255, 154, 86, 0.4)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(255, 154, 86, 0.3)';">
                                <i class="ri-article-line" style="font-size: 32px; color: white; display: block; margin-bottom: 12px;"></i>
                                <h4 style="margin: 0 0 6px; color: white; font-size: 16px; font-weight: 600;">Weekly Review</h4>
                                <p style="margin: 0; color: rgba(255,255,255,0.9); font-size: 13px; line-height: 1.4;">Reflect and plan for the week ahead</p>
                            </div>
                        </a>
                    </div>
                </div>

                <script>
                function toggleProductivityTools() {
                    const container = document.getElementById('productivityToolsContainer');
                    const icon = document.getElementById('productivityToggleIcon');

                    if (container.style.display === 'none') {
                        container.style.display = 'grid';
                        icon.className = 'ri-arrow-down-s-line';
                    } else {
                        container.style.display = 'none';
                        icon.className = 'ri-arrow-right-s-line';
                    }
                }
                </script>

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

                <!-- Tasks List - SYNTO MODERN CARD DESIGN -->
                <div style="margin-top: 32px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h2 style="margin: 0; color: var(--synto-text-primary); font-size: 24px; font-weight: 600;">
                            <i class="ri-task-line" style="color: var(--synto-primary);"></i> My Tasks
                            <span style="color: var(--synto-text-secondary); font-size: 18px; font-weight: 400;">(<?php echo count($tasks); ?>)</span>
                        </h2>
                    </div>

                    <?php if (empty($tasks)): ?>
                        <div class="stats-card" style="text-align: center; padding: 80px 20px;">
                            <div style="font-size: 72px; margin-bottom: 20px; opacity: 0.5;">📋</div>
                            <h3 style="color: var(--synto-text-primary); margin: 0 0 8px;">No tasks found</h3>
                            <p style="color: var(--synto-text-secondary); margin: 0;">You don't have any tasks matching these filters.</p>
                        </div>
                    <?php else: ?>
                        <div style="display: grid; gap: 20px;">
                            <?php foreach ($tasks as $task): ?>
                            <?php
                                // Check if this specific task is currently being worked on
                                // A task is "active" if it has an active time log OR if status is in_progress
                                $isTaskActive = in_array($task['id'], $activeTaskIds) || $task['status'] === 'in_progress';
                                $isOverdue = isOverdue($task['due_date'], $task['status']);

                                // Get priority color
                                $priorityColors = [
                                    'urgent' => '#EF4444',
                                    'high' => '#F59E0B',
                                    'medium' => '#3B82F6',
                                    'low' => '#10B981'
                                ];
                                $priorityColor = $priorityColors[$task['priority']] ?? '#6B7280';
                            ?>
                            <!-- SYNTO TASK CARD -->
                            <div class="stats-card" style="position: relative; border-left: 4px solid <?php echo $priorityColor; ?>; <?php echo $isTaskActive ? 'background: linear-gradient(to right, #EFF6FF 0%, #FFFFFF 100%);' : ''; ?>">
                                <!-- Active Indicator -->
                                <?php if ($isTaskActive): ?>
                                <div style="position: absolute; top: 16px; right: 16px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);">
                                    <i class="ri-time-line" style="font-size: 14px;"></i> WORKING NOW
                                </div>
                                <?php endif; ?>

                                <!-- Task Header -->
                                <div style="display: flex; gap: 16px; align-items: start; margin-bottom: 16px;">
                                    <!-- Priority Icon -->
                                    <div style="width: 48px; height: 48px; border-radius: 12px; background: <?php echo $priorityColor; ?>15; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                        <i class="ri-checkbox-line" style="font-size: 24px; color: <?php echo $priorityColor; ?>;"></i>
                                    </div>

                                    <!-- Task Content -->
                                    <div style="flex: 1; min-width: 0;">
                                        <h3 style="margin: 0 0 8px; font-size: 18px; font-weight: 600; color: var(--synto-text-primary);">
                                            <a href="task-detail.php?id=<?php echo $task['id']; ?>" style="color: inherit; text-decoration: none; transition: color 0.2s;">
                                                <?php echo e($task['task_name']); ?>
                                            </a>
                                        </h3>

                                        <div style="display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 12px;">
                                            <!-- Project Badge -->
                                            <span style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; background: var(--synto-bg); border: 1px solid var(--synto-border); border-radius: 6px; font-size: 13px; color: var(--synto-text-secondary);">
                                                <i class="ri-folder-line" style="font-size: 14px;"></i>
                                                <?php echo e($task['project_name']); ?>
                                            </span>

                                            <!-- Client Badge -->
                                            <?php if ($task['client_name']): ?>
                                            <span style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; background: var(--synto-bg); border: 1px solid var(--synto-border); border-radius: 6px; font-size: 13px; color: var(--synto-text-secondary);">
                                                <i class="ri-user-line" style="font-size: 14px;"></i>
                                                <?php echo e($task['client_name']); ?>
                                            </span>
                                            <?php endif; ?>

                                            <!-- Status Badge -->
                                            <span class="badge <?php echo getStatusClass($task['status']); ?>" style="font-weight: 500;">
                                                <?php echo ucfirst(str_replace('_', ' ', $task['status'])); ?>
                                            </span>

                                            <!-- Priority Badge -->
                                            <span style="padding: 4px 12px; background: <?php echo $priorityColor; ?>15; color: <?php echo $priorityColor; ?>; border-radius: 6px; font-size: 12px; font-weight: 600; text-transform: uppercase;">
                                                <?php echo $task['priority']; ?>
                                            </span>

                                            <!-- Overdue Warning -->
                                            <?php if ($isOverdue): ?>
                                            <span style="padding: 4px 12px; background: #FEF2F2; color: #EF4444; border-radius: 6px; font-size: 12px; font-weight: 600;">
                                                <i class="ri-error-warning-line"></i> OVERDUE
                                            </span>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Task Description -->
                                        <?php if ($task['description']): ?>
                                        <div style="margin-bottom: 16px; padding: 12px; background: var(--synto-bg); border-radius: 8px; font-size: 14px; line-height: 1.6; color: var(--synto-text-secondary);">
                                            <?php echo nl2br(e(substr($task['description'], 0, 200))); ?>
                                            <?php if (strlen($task['description']) > 200): ?>...<?php endif; ?>
                                        </div>
                                        <?php endif; ?>

                                        <!-- Task Metrics -->
                                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 16px; padding: 16px; background: var(--synto-bg); border-radius: 8px; margin-bottom: 16px;">
                                            <!-- Estimated Hours -->
                                            <div>
                                                <div style="font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: var(--synto-text-tertiary); margin-bottom: 4px;">
                                                    <i class="ri-time-line"></i> Estimated
                                                </div>
                                                <div style="font-size: 18px; font-weight: 700; color: var(--synto-text-primary);">
                                                    <?php echo $task['estimated_hours'] ? number_format($task['estimated_hours'], 1) . 'h' : 'N/A'; ?>
                                                </div>
                                            </div>

                                            <!-- Actual Hours -->
                                            <div>
                                                <div style="font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: var(--synto-text-tertiary); margin-bottom: 4px;">
                                                    <i class="ri-timer-line"></i> Actual
                                                </div>
                                                <div style="font-size: 18px; font-weight: 700; color: var(--synto-primary);">
                                                    <?php echo formatHours($task['actual_hours'] ?? 0); ?>h
                                                </div>
                                            </div>

                                            <!-- Due Date -->
                                            <div>
                                                <div style="font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: var(--synto-text-tertiary); margin-bottom: 4px;">
                                                    <i class="ri-calendar-line"></i> Due Date
                                                </div>
                                                <div style="font-size: 14px; font-weight: 600; color: <?php echo $isOverdue ? '#EF4444' : 'var(--synto-text-primary)'; ?>;">
                                                    <?php echo $task['due_date'] ? date('M d, Y', strtotime($task['due_date'])) : 'No deadline'; ?>
                                                </div>
                                            </div>

                                            <!-- Completion -->
                                            <?php if ($task['status'] === 'completed' && $task['completed_date']): ?>
                                            <div>
                                                <div style="font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: var(--synto-text-tertiary); margin-bottom: 4px;">
                                                    <i class="ri-checkbox-circle-line"></i> Completed
                                                </div>
                                                <div style="font-size: 14px; font-weight: 600; color: var(--synto-success);">
                                                    <?php echo date('M d, Y', strtotime($task['completed_date'])); ?>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Action Buttons -->
                                        <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                                            <a href="task-detail.php?id=<?php echo $task['id']; ?>"
                                               style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background: var(--synto-bg); border: 1px solid var(--synto-border); border-radius: 8px; color: var(--synto-text-primary); text-decoration: none; font-weight: 500; transition: all 0.2s; font-size: 14px;">
                                                <i class="ri-eye-line" style="font-size: 16px;"></i> View Details
                                            </a>

                                            <?php if ($task['status'] !== 'completed'): ?>
                                                <?php if (!$isTaskActive): ?>
                                                    <button onclick="startTimer(<?php echo $task['id']; ?>, <?php echo $task['project_id']; ?>)"
                                                            style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background: linear-gradient(135deg, #10B981 0%, #059669 100%); border: none; border-radius: 8px; color: white; font-weight: 600; cursor: pointer; transition: all 0.2s; font-size: 14px; box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);">
                                                        <i class="ri-play-circle-line" style="font-size: 18px;"></i> Start Working
                                                    </button>
                                                <?php else: ?>
                                                    <button onclick="stopTask(<?php echo $task['id']; ?>)"
                                                            style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%); border: none; border-radius: 8px; color: white; font-weight: 600; cursor: pointer; transition: all 0.2s; font-size: 14px; box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);">
                                                        <i class="ri-stop-circle-line" style="font-size: 18px;"></i> Stop Working
                                                    </button>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
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
                    message.innerHTML = '<i class="fas ri-checkbox-circle-line"></i> Task started - tracking in background';
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

    <!-- Synto Dashboard Interactions -->
    <script src="../assets/js/synto-interactions.js"></script>
</body>
</html>
