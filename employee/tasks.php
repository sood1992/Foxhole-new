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
$priorityFilter = $_GET['priority'] ?? 'all';
$dueDateFilter = $_GET['due_date'] ?? 'all';
$searchQuery = $_GET['search'] ?? '';
$appliedFilterId = $_GET['filter_id'] ?? null;

// Load saved filter if specified
if ($appliedFilterId) {
    $stmt = $db->prepare("SELECT filter_config FROM saved_filters WHERE id = ? AND user_id = ?");
    $stmt->execute([$appliedFilterId, $currentUser['id']]);
    $savedFilter = $stmt->fetch();

    if ($savedFilter) {
        $filterConfig = json_decode($savedFilter['filter_config'], true);
        $statusFilter = $filterConfig['status'] ?? 'all';
        $projectFilter = $filterConfig['project'] ?? 'all';
        $priorityFilter = $filterConfig['priority'] ?? 'all';
        $dueDateFilter = $filterConfig['due_date'] ?? 'all';
        $searchQuery = $filterConfig['search'] ?? '';
    }
}

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

if ($priorityFilter !== 'all') {
    $query .= " AND t.priority = ?";
    $params[] = $priorityFilter;
}

// Due date filters
if ($dueDateFilter === 'overdue') {
    $query .= " AND t.due_date < CURDATE() AND t.status != 'completed'";
} elseif ($dueDateFilter === 'today') {
    $query .= " AND DATE(t.due_date) = CURDATE()";
} elseif ($dueDateFilter === 'this_week') {
    $query .= " AND YEARWEEK(t.due_date, 1) = YEARWEEK(CURDATE(), 1)";
} elseif ($dueDateFilter === 'next_week') {
    $query .= " AND YEARWEEK(t.due_date, 1) = YEARWEEK(CURDATE(), 1) + 1";
} elseif ($dueDateFilter === 'this_month') {
    $query .= " AND YEAR(t.due_date) = YEAR(CURDATE()) AND MONTH(t.due_date) = MONTH(CURDATE())";
}

// Search query
if (!empty($searchQuery)) {
    $query .= " AND (t.task_name LIKE ? OR t.description LIKE ?)";
    $searchTerm = '%' . $searchQuery . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
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

// Get saved filters
$savedFiltersStmt = $db->prepare("
    SELECT id, filter_name, filter_config, is_favorite
    FROM saved_filters
    WHERE user_id = ?
    ORDER BY is_favorite DESC, filter_name ASC
");
$savedFiltersStmt->execute([$currentUser['id']]);
$savedFilters = $savedFiltersStmt->fetchAll();

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
    <title>My Tasks V3 - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/vien-v3.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .tasks-layout {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 24px;
        }

        .filters-sidebar {
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            padding: 20px;
            height: fit-content;
            position: sticky;
            top: 24px;
        }

        .filter-section {
            margin-bottom: 24px;
        }

        .filter-section-title {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--text-secondary);
            margin-bottom: 12px;
            letter-spacing: 0.5px;
        }

        .filter-item {
            padding: 10px 12px;
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 4px;
            font-size: 14px;
        }

        .filter-item:hover {
            background: var(--bg-tertiary);
        }

        .filter-item.active {
            background: var(--primary);
            color: white;
        }

        .filter-item-icon {
            margin-right: 8px;
        }

        .filter-item-name {
            flex: 1;
        }

        .filter-item-count {
            font-size: 12px;
            opacity: 0.7;
        }

        .filter-actions {
            opacity: 0;
            transition: opacity 0.2s;
        }

        .filter-item:hover .filter-actions {
            opacity: 1;
        }

        .btn-filter-action {
            background: none;
            border: none;
            padding: 4px 8px;
            cursor: pointer;
            color: inherit;
            font-size: 12px;
        }

        @media (max-width: 968px) {
            .tasks-layout {
                grid-template-columns: 1fr;
            }

            .filters-sidebar {
                position: static;
            }
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
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success" style="margin-bottom: 24px;">
                        <?php
                        echo e($_SESSION['success_message']);
                        unset($_SESSION['success_message']);
                        ?>
                    </div>
                <?php endif; ?>

                <!-- Active Timer Alert -->
                <?php if ($activeTimeLog): ?>
                <div class="alert alert-info">
                    ⏱️ Timer is running for task: <strong><?php echo e($activeTimeLog['task_name'] ?? 'Unknown'); ?></strong>
                    <a href="index.php" style="margin-left: 10px;">Go to Dashboard to stop</a>
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

                <!-- Tasks Layout with Filters Sidebar -->
                <div class="tasks-layout">
                    <!-- Filters Sidebar -->
                    <div class="filters-sidebar">
                        <!-- Quick Filters -->
                        <div class="filter-section">
                            <div class="filter-section-title">Quick Filters</div>
                            <a href="tasks.php" class="filter-item <?php echo !$appliedFilterId && $statusFilter === 'all' && $priorityFilter === 'all' && $dueDateFilter === 'all' ? 'active' : ''; ?>">
                                <span><i class="fas fa-list filter-item-icon"></i><span class="filter-item-name">All Tasks</span></span>
                                <span class="filter-item-count"><?php echo count($tasks); ?></span>
                            </a>
                            <a href="?priority=high" class="filter-item <?php echo $priorityFilter === 'high' ? 'active' : ''; ?>">
                                <span><i class="fas fa-exclamation-circle filter-item-icon" style="color: var(--red);"></i><span class="filter-item-name">High Priority</span></span>
                            </a>
                            <a href="?priority=urgent" class="filter-item <?php echo $priorityFilter === 'urgent' ? 'active' : ''; ?>">
                                <span><i class="fas fa-fire filter-item-icon" style="color: var(--orange);"></i><span class="filter-item-name">Urgent</span></span>
                            </a>
                            <a href="?due_date=overdue" class="filter-item <?php echo $dueDateFilter === 'overdue' ? 'active' : ''; ?>">
                                <span><i class="fas fa-exclamation-triangle filter-item-icon" style="color: var(--red);"></i><span class="filter-item-name">Overdue</span></span>
                            </a>
                            <a href="?due_date=today" class="filter-item <?php echo $dueDateFilter === 'today' ? 'active' : ''; ?>">
                                <span><i class="fas fa-calendar-day filter-item-icon"></i><span class="filter-item-name">Due Today</span></span>
                            </a>
                            <a href="?due_date=this_week" class="filter-item <?php echo $dueDateFilter === 'this_week' ? 'active' : ''; ?>">
                                <span><i class="fas fa-calendar-week filter-item-icon"></i><span class="filter-item-name">Due This Week</span></span>
                            </a>
                            <a href="?status=blocked" class="filter-item <?php echo $statusFilter === 'blocked' ? 'active' : ''; ?>">
                                <span><i class="fas fa-ban filter-item-icon" style="color: var(--orange);"></i><span class="filter-item-name">Blocked Tasks</span></span>
                            </a>
                            <a href="?status=in_progress" class="filter-item <?php echo $statusFilter === 'in_progress' ? 'active' : ''; ?>">
                                <span><i class="fas fa-spinner filter-item-icon" style="color: var(--blue);"></i><span class="filter-item-name">In Progress</span></span>
                            </a>
                        </div>

                        <!-- Saved Filters -->
                        <?php if (!empty($savedFilters)): ?>
                        <div class="filter-section">
                            <div class="filter-section-title" style="display: flex; justify-content: space-between; align-items: center;">
                                <span>My Smart Lists</span>
                                <button onclick="openSaveFilterModal()" class="btn-filter-action" title="Create new filter">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                            <?php foreach ($savedFilters as $filter): ?>
                            <div class="filter-item <?php echo $appliedFilterId == $filter['id'] ? 'active' : ''; ?>"
                                 onclick="window.location.href='?filter_id=<?php echo $filter['id']; ?>'">
                                <span>
                                    <i class="<?php echo $filter['is_favorite'] ? 'fas fa-star' : 'far fa-bookmark'; ?> filter-item-icon"></i>
                                    <span class="filter-item-name"><?php echo e($filter['filter_name']); ?></span>
                                </span>
                                <span class="filter-actions">
                                    <button onclick="event.stopPropagation(); toggleFavorite(<?php echo $filter['id']; ?>)"
                                            class="btn-filter-action" title="Toggle favorite">
                                        <i class="fas fa-star"></i>
                                    </button>
                                    <button onclick="event.stopPropagation(); deleteFilter(<?php echo $filter['id']; ?>)"
                                            class="btn-filter-action" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <!-- Advanced Filters Button -->
                        <button onclick="openAdvancedFilters()" class="btn btn-secondary" style="width: 100%; margin-top: 12px;">
                            <i class="fas fa-filter"></i> Advanced Filters
                        </button>

                        <?php if (!empty($savedFilters)): ?>
                        <?php else: ?>
                        <div style="text-align: center; padding: 20px 0; color: var(--text-secondary);">
                            <div style="font-size: 32px; margin-bottom: 8px;">📋</div>
                            <p style="font-size: 12px; margin: 0;">Create custom filters to organize your tasks</p>
                            <button onclick="openSaveFilterModal()" class="btn btn-primary btn-sm" style="margin-top: 12px;">
                                <i class="fas fa-plus"></i> Create Filter
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Tasks Content -->
                    <div>
                        <!-- Search and Actions Bar -->
                        <div class="dashboard-card" style="margin-bottom: 24px;">
                            <div class="card-body" style="padding: 16px;">
                                <form method="GET" style="display: flex; gap: 12px; align-items: center;">
                                    <div style="flex: 1;">
                                        <input type="text" name="search" class="form-control"
                                               placeholder="Search tasks..."
                                               value="<?php echo e($searchQuery); ?>">
                                    </div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i> Search
                                    </button>
                                    <?php if (!empty($searchQuery) || $statusFilter !== 'all' || $priorityFilter !== 'all' || $dueDateFilter !== 'all' || $projectFilter !== 'all'): ?>
                                    <a href="tasks.php" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> Clear
                                    </a>
                                    <button type="button" onclick="openSaveFilterModal()" class="btn btn-success">
                                        <i class="fas fa-save"></i> Save Filter
                                    </button>
                                    <?php endif; ?>
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
                    </div> <!-- End tasks content -->
                </div> <!-- End tasks-layout -->
            </div> <!-- End content-wrapper -->
        </div> <!-- End main-content -->
    </div> <!-- End app-container -->

    <!-- Save Filter Modal -->
    <div id="saveFilterModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 9999; align-items: center; justify-content: center;">
        <div style="background: var(--bg-secondary); border-radius: var(--radius-lg); padding: 32px; max-width: 500px; width: 90%;">
            <h3 style="margin-bottom: 24px;">Save Current Filter</h3>
            <div class="form-group">
                <label>Filter Name</label>
                <input type="text" id="filterName" class="form-control" placeholder="e.g., High Priority This Week">
            </div>
            <div class="form-group">
                <label>
                    <input type="checkbox" id="filterFavorite"> Mark as favorite
                </label>
            </div>
            <div style="display: flex; gap: 12px; margin-top: 24px;">
                <button onclick="saveCurrentFilter()" class="btn btn-success" style="flex: 1;">
                    <i class="fas fa-save"></i> Save Filter
                </button>
                <button onclick="closeSaveFilterModal()" class="btn btn-secondary">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Advanced Filters Modal -->
    <div id="advancedFiltersModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 9999; align-items: center; justify-content: center;">
        <div style="background: var(--bg-secondary); border-radius: var(--radius-lg); padding: 32px; max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto;">
            <h3 style="margin-bottom: 24px;">Advanced Filters</h3>
            <form id="advancedFilterForm">
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="form-control">
                        <option value="all">All Statuses</option>
                        <option value="todo" <?php echo $statusFilter === 'todo' ? 'selected' : ''; ?>>To Do</option>
                        <option value="in_progress" <?php echo $statusFilter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                        <option value="review" <?php echo $statusFilter === 'review' ? 'selected' : ''; ?>>In Review</option>
                        <option value="blocked" <?php echo $statusFilter === 'blocked' ? 'selected' : ''; ?>>Blocked</option>
                        <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Priority</label>
                    <select name="priority" class="form-control">
                        <option value="all">All Priorities</option>
                        <option value="low" <?php echo $priorityFilter === 'low' ? 'selected' : ''; ?>>Low</option>
                        <option value="medium" <?php echo $priorityFilter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                        <option value="high" <?php echo $priorityFilter === 'high' ? 'selected' : ''; ?>>High</option>
                        <option value="urgent" <?php echo $priorityFilter === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Project</label>
                    <select name="project" class="form-control">
                        <option value="all">All Projects</option>
                        <?php foreach ($projects as $proj): ?>
                            <option value="<?php echo $proj['id']; ?>" <?php echo $projectFilter == $proj['id'] ? 'selected' : ''; ?>>
                                <?php echo e($proj['project_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Due Date</label>
                    <select name="due_date" class="form-control">
                        <option value="all">Any Time</option>
                        <option value="overdue" <?php echo $dueDateFilter === 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                        <option value="today" <?php echo $dueDateFilter === 'today' ? 'selected' : ''; ?>>Due Today</option>
                        <option value="this_week" <?php echo $dueDateFilter === 'this_week' ? 'selected' : ''; ?>>Due This Week</option>
                        <option value="next_week" <?php echo $dueDateFilter === 'next_week' ? 'selected' : ''; ?>>Due Next Week</option>
                        <option value="this_month" <?php echo $dueDateFilter === 'this_month' ? 'selected' : ''; ?>>Due This Month</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Search Keywords</label>
                    <input type="text" name="search" class="form-control" placeholder="Search in task name or description" value="<?php echo e($searchQuery); ?>">
                </div>

                <div style="display: flex; gap: 12px; margin-top: 24px;">
                    <button type="button" onclick="applyAdvancedFilters()" class="btn btn-primary" style="flex: 1;">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                    <button type="button" onclick="closeAdvancedFilters()" class="btn btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
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

        // Modal functions
        function openSaveFilterModal() {
            document.getElementById('saveFilterModal').style.display = 'flex';
        }

        function closeSaveFilterModal() {
            document.getElementById('saveFilterModal').style.display = 'none';
            document.getElementById('filterName').value = '';
            document.getElementById('filterFavorite').checked = false;
        }

        function openAdvancedFilters() {
            document.getElementById('advancedFiltersModal').style.display = 'flex';
        }

        function closeAdvancedFilters() {
            document.getElementById('advancedFiltersModal').style.display = 'none';
        }

        // Save current filter
        async function saveCurrentFilter() {
            const filterName = document.getElementById('filterName').value.trim();
            const isFavorite = document.getElementById('filterFavorite').checked ? 1 : 0;

            if (!filterName) {
                alert('Please enter a filter name');
                return;
            }

            // Get current filter parameters from URL
            const urlParams = new URLSearchParams(window.location.search);
            const filterConfig = {
                status: urlParams.get('status') || 'all',
                priority: urlParams.get('priority') || 'all',
                project: urlParams.get('project') || 'all',
                due_date: urlParams.get('due_date') || 'all',
                search: urlParams.get('search') || ''
            };

            try {
                const response = await fetch('../api/saved-filters.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'create',
                        filter_name: filterName,
                        filter_config: filterConfig,
                        is_favorite: isFavorite
                    })
                });

                const data = await response.json();

                if (data.success) {
                    alert('Filter saved successfully!');
                    closeSaveFilterModal();
                    window.location.reload();
                } else {
                    alert(data.message || 'Failed to save filter');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Failed to save filter');
            }
        }

        // Apply advanced filters
        function applyAdvancedFilters() {
            const form = document.getElementById('advancedFilterForm');
            const formData = new FormData(form);
            const params = new URLSearchParams();

            for (const [key, value] of formData.entries()) {
                if (value && value !== 'all') {
                    params.append(key, value);
                }
            }

            window.location.href = 'tasks.php?' + params.toString();
        }

        // Toggle favorite
        async function toggleFavorite(filterId) {
            try {
                const response = await fetch('../api/saved-filters.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'toggle_favorite',
                        filter_id: filterId
                    })
                });

                const data = await response.json();

                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.message || 'Failed to update filter');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Failed to update filter');
            }
        }

        // Delete filter
        async function deleteFilter(filterId) {
            if (!confirm('Are you sure you want to delete this filter?')) {
                return;
            }

            try {
                const response = await fetch('../api/saved-filters.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'delete',
                        filter_id: filterId
                    })
                });

                const data = await response.json();

                if (data.success) {
                    window.location.href = 'tasks.php';
                } else {
                    alert(data.message || 'Failed to delete filter');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Failed to delete filter');
            }
        }

        // Close modals when clicking outside
        document.getElementById('saveFilterModal')?.addEventListener('click', function(e) {
            if (e.target === this) closeSaveFilterModal();
        });

        document.getElementById('advancedFiltersModal')?.addEventListener('click', function(e) {
            if (e.target === this) closeAdvancedFilters();
        });

        // ESC key to close modals
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeSaveFilterModal();
                closeAdvancedFilters();
            }
        });
    </script>
    <script src="../assets/js/theme.js"></script>
</body>
</html>
