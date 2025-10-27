<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || (!hasRole('admin') && !hasRole('manager'))) {
    redirect('../login.php');
}

$db = getDBConnection();
$currentUser = getCurrentUser();

// Handle project creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_project'])) {
    try {
        $stmt = $db->prepare("
            INSERT INTO projects (project_name, client_name, assigned_manager, budget, start_date, due_date, status, priority, description, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $_POST['project_name'],
            $_POST['client_name'],
            $_POST['assigned_manager'] ?: null,
            $_POST['budget'] ?: 0,
            $_POST['start_date'],
            $_POST['due_date'],
            $_POST['status'],
            $_POST['priority'],
            $_POST['description']
        ]);
        $successMessage = "Project created successfully!";
    } catch (Exception $e) {
        $errorMessage = "Error creating project: " . $e->getMessage();
    }
}

// Get all projects
$projects = $db->query("
    SELECT p.*,
           u.full_name as manager_name,
           (SELECT COUNT(*) FROM tasks WHERE project_id = p.id) as task_count,
           (SELECT COUNT(*) FROM tasks WHERE project_id = p.id AND status = 'completed') as completed_tasks
    FROM projects p
    LEFT JOIN users u ON p.assigned_manager = u.id
    ORDER BY
        FIELD(p.status, 'in_progress', 'review', 'planning', 'on_hold', 'completed'),
        p.due_date ASC
")->fetchAll();

// Get managers for assignment
$managers = $db->query("SELECT id, full_name FROM users WHERE role IN ('admin', 'manager') AND is_active = 1 ORDER BY full_name")->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projects - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/ultra-premium.css">
    <?php include '../includes/quick-actions-assets.php'; ?>
</head>
<body>
    <div class="dashboard">
        <?php include '../includes/admin-sidebar.php'; ?>

        <main class="main-content">
            <div class="topbar">
                <h1>📁 Projects</h1>
                <div class="topbar-actions">
                    <?php include '../includes/global-search-assets.php'; ?>
                    <?php include '../includes/notifications-dropdown.php'; ?>
                </div>
            </div>

            <div class="content">
                <?php if (isset($successMessage)): ?>
                    <div class="alert alert-success"><?php echo e($successMessage); ?></div>
                <?php endif; ?>
                <?php if (isset($errorMessage)): ?>
                    <div class="alert alert-error"><?php echo e($errorMessage); ?></div>
                <?php endif; ?>

                <!-- Create Project Form -->
                <div class="card" style="margin-bottom: var(--space-6);">
                    <div class="card-header">
                        <h3>+ Create New Project</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: var(--space-4);">
                            <div class="form-group">
                                <label for="project_name">Project Name *</label>
                                <input type="text" id="project_name" name="project_name" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label for="client_name">Client Name</label>
                                <input type="text" id="client_name" name="client_name" class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="assigned_manager">Assign Manager</label>
                                <select id="assigned_manager" name="assigned_manager" class="form-control">
                                    <option value="">Unassigned</option>
                                    <?php foreach ($managers as $manager): ?>
                                        <option value="<?php echo $manager['id']; ?>"><?php echo e($manager['full_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="budget">Budget ($)</label>
                                <input type="number" id="budget" name="budget" class="form-control" step="0.01">
                            </div>
                            <div class="form-group">
                                <label for="start_date">Start Date *</label>
                                <input type="date" id="start_date" name="start_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="due_date">Due Date *</label>
                                <input type="date" id="due_date" name="due_date" class="form-control" value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="status">Status</label>
                                <select id="status" name="status" class="form-control">
                                    <option value="planning">Planning</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="review">Review</option>
                                    <option value="on_hold">On Hold</option>
                                    <option value="completed">Completed</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="priority">Priority</label>
                                <select id="priority" name="priority" class="form-control">
                                    <option value="low">Low</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                            </div>
                            <div class="form-group" style="grid-column: span 2;">
                                <label for="description">Description</label>
                                <textarea id="description" name="description" class="form-control" rows="3"></textarea>
                            </div>
                            <div style="grid-column: span 2;">
                                <button type="submit" name="create_project" class="btn btn-primary">Create Project</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Projects List -->
                <div class="card">
                    <div class="card-header">
                        <h3>All Projects (<?php echo count($projects); ?>)</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($projects)): ?>
                            <p style="text-align: center; color: var(--text-secondary); padding: var(--space-10);">
                                No projects created yet.
                            </p>
                        <?php else: ?>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Project Name</th>
                                        <th>Client</th>
                                        <th>Manager</th>
                                        <th>Status</th>
                                        <th>Priority</th>
                                        <th>Progress</th>
                                        <th>Due Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($projects as $project): ?>
                                    <?php
                                    $completion = $project['task_count'] > 0 ?
                                        round(($project['completed_tasks'] / $project['task_count']) * 100) : 0;
                                    ?>
                                    <tr class="<?php echo isOverdue($project['due_date'], $project['status']) ? 'overdue' : ''; ?>">
                                        <td><strong><?php echo e($project['project_name']); ?></strong></td>
                                        <td><?php echo e($project['client_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo e($project['manager_name'] ?? 'Unassigned'); ?></td>
                                        <td>
                                            <span class="badge <?php echo getStatusClass($project['status']); ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $project['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo getPriorityClass($project['priority']); ?>">
                                                <?php echo ucfirst($project['priority']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="progress-bar-container">
                                                <div class="progress-bar" style="width: <?php echo $completion; ?>%"></div>
                                            </div>
                                            <small style="color: var(--text-secondary);">
                                                <?php echo $completion; ?>% (<?php echo $project['completed_tasks']; ?>/<?php echo $project['task_count']; ?>)
                                            </small>
                                        </td>
                                        <td>
                                            <?php if ($project['due_date']): ?>
                                                <?php echo date('M d, Y', strtotime($project['due_date'])); ?>
                                                <?php if (isOverdue($project['due_date'], $project['status'])): ?>
                                                    <br><span style="color: var(--status-blocked); font-size: var(--font-xs);">⚠️ Overdue</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span style="color: var(--text-tertiary);">No deadline</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="project-detail.php?id=<?php echo $project['id']; ?>" class="btn btn-primary btn-sm">
                                                View Details
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
        </main>
    </div>

    <script src="../assets/js/theme.js"></script>
</body>
</html>
