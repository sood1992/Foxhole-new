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

// Timer removed - employees now use simple status updates
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
                                            <?php if ($task['status'] === 'todo'): ?>
                                                <button onclick="startWorking(<?php echo $task['id']; ?>)"
                                                        class="btn btn-success btn-sm">
                                                    🚀 Start Working
                                                </button>
                                            <?php elseif ($task['status'] === 'in_progress'): ?>
                                                <button onclick="openCommentModal(<?php echo $task['id']; ?>, '<?php echo addslashes($task['task_name']); ?>')"
                                                        class="btn btn-secondary btn-sm" style="margin-right: 8px;">
                                                    💬 Add Progress
                                                </button>
                                                <button onclick="submitForReview(<?php echo $task['id']; ?>, '<?php echo addslashes($task['task_name']); ?>')"
                                                        class="btn btn-primary btn-sm">
                                                    ✅ Submit for Review
                                                </button>
                                            <?php elseif ($task['status'] === 'review'): ?>
                                                <span class="badge status-review">👀 In Review</span>
                                            <?php elseif ($task['status'] === 'completed'): ?>
                                                <span class="badge status-completed">✅ Completed</span>
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

    <!-- Comment Modal -->
    <div id="commentModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: var(--bg-primary); padding: var(--space-6); border-radius: var(--radius-lg); width: 90%; max-width: 500px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-4);">
                <h3 style="margin: 0;" id="commentModalTitle">Add Progress Comment</h3>
                <button onclick="closeCommentModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: var(--text-secondary);">&times;</button>
            </div>

            <div style="margin-bottom: var(--space-4);">
                <label style="display: block; margin-bottom: var(--space-2); font-weight: 500;">Your Progress Update</label>
                <textarea id="progressComment" rows="4" placeholder="Describe what you've accomplished, any blockers, or next steps..." style="width: 100%; padding: var(--space-2); border: 1px solid var(--border-color); border-radius: var(--radius-md); background: var(--bg-secondary);"></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: var(--space-3);">
                <button type="button" onclick="closeCommentModal()" class="btn btn-secondary">Cancel</button>
                <button type="button" onclick="addProgressComment()" class="btn btn-primary">Add Comment</button>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script>
        let currentTaskId = null;

        // Start working on a task
        function startWorking(taskId) {
            if (confirm('Start working on this task? Time tracking will begin automatically.')) {
                fetch('../api/task-status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'start_working',
                        task_id: taskId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        location.reload();
                    } else {
                        alert(data.message || 'Failed to start working');
                    }
                })
                .catch(error => {
                    alert('Error: ' + error.message);
                });
            }
        }

        // Open comment modal
        function openCommentModal(taskId, taskName) {
            currentTaskId = taskId;
            document.getElementById('commentModalTitle').textContent = 'Add Progress for: ' + taskName;
            document.getElementById('progressComment').value = '';
            document.getElementById('commentModal').style.display = 'flex';
        }

        // Close comment modal
        function closeCommentModal() {
            document.getElementById('commentModal').style.display = 'none';
            currentTaskId = null;
        }

        // Add progress comment
        function addProgressComment() {
            const comment = document.getElementById('progressComment').value.trim();

            if (!comment) {
                alert('Please enter a comment');
                return;
            }

            fetch('../api/task-status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'add_progress_comment',
                    task_id: currentTaskId,
                    comment: comment
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Progress comment added!');
                    closeCommentModal();
                } else {
                    alert(data.message || 'Failed to add comment');
                }
            })
            .catch(error => {
                alert('Error: ' + error.message);
            });
        }

        // Submit for review with optional comment
        function submitForReview(taskId, taskName) {
            const comment = prompt(`Submit "${taskName}" for review?\n\nOptional: Add a completion note or summary:`, '');

            if (comment !== null) { // null means cancelled
                fetch('../api/task-status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'submit_for_review',
                        task_id: taskId,
                        comment: comment
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        location.reload();
                    } else {
                        alert(data.message || 'Failed to submit for review');
                    }
                })
                .catch(error => {
                    alert('Error: ' + error.message);
                });
            }
        }

        // Close modal on outside click
        document.getElementById('commentModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeCommentModal();
            }
        });
    </script>
    <script src="../assets/js/theme.js"></script>
</body>
</html>
