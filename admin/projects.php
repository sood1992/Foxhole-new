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

// Get filter status if provided
$filterStatus = $_GET['status'] ?? null;
$pageTitle = 'All Projects';
if ($filterStatus === 'in_progress') {
    $pageTitle = 'In Progress Projects';
} elseif ($filterStatus === 'completed') {
    $pageTitle = 'Completed Projects';
} elseif ($filterStatus === 'planning') {
    $pageTitle = 'Planning Projects';
} elseif ($filterStatus === 'on_hold') {
    $pageTitle = 'On Hold Projects';
}

// Get all projects (with optional status filter)
$query = "
    SELECT p.*,
           u.full_name as manager_name,
           (SELECT COUNT(*) FROM tasks WHERE project_id = p.id) as task_count,
           (SELECT COUNT(*) FROM tasks WHERE project_id = p.id AND status = 'completed') as completed_tasks
    FROM projects p
    LEFT JOIN users u ON p.assigned_manager = u.id
";

if ($filterStatus) {
    $query .= " WHERE p.status = " . $db->quote($filterStatus);
}

$query .= "
    ORDER BY
        FIELD(p.status, 'in_progress', 'review', 'planning', 'on_hold', 'completed'),
        p.due_date ASC
";

$projects = $db->query($query)->fetchAll();

// Get managers for assignment
$managers = $db->query("SELECT id, full_name FROM users WHERE role IN ('admin', 'manager') AND is_active = 1 ORDER BY full_name")->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projects - <?php echo SITE_NAME; ?> V3</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/vien-v3.css">
</head>
<body>
    <div class="app-container">
        <?php include '../includes/v3-admin-sidebar.php'; ?>

        <div class="main-content">
            <?php include '../includes/v3-header.php'; ?>

            <div class="content-wrapper">
                <!-- Page Title -->
                <div style="margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h1 style="margin-bottom: 8px;"><?php echo $pageTitle; ?></h1>
                        <p style="color: var(--text-secondary); font-size: 14px; margin: 0;">
                            <?php if ($filterStatus): ?>
                                Viewing <?php echo strtolower($pageTitle); ?>
                                <a href="projects.php" style="margin-left: 10px; color: var(--primary);">
                                    <i class="fas fa-arrow-left"></i> View All Projects
                                </a>
                            <?php else: ?>
                                Manage all projects, assign managers, and track progress
                            <?php endif; ?>
                        </p>
                    </div>
                    <?php if (!$filterStatus): ?>
                    <a href="#create-form" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Create Project
                    </a>
                    <?php endif; ?>
                </div>

                <?php if (isset($successMessage)): ?>
                    <div class="alert alert-success" style="margin-bottom: 20px;">
                        <i class="fas fa-check-circle"></i> <?php echo e($successMessage); ?>
                    </div>
                <?php endif; ?>
                <?php if (isset($errorMessage)): ?>
                    <div class="alert alert-error" style="margin-bottom: 20px;">
                        <i class="fas fa-exclamation-circle"></i> <?php echo e($errorMessage); ?>
                    </div>
                <?php endif; ?>

                <?php if (!$filterStatus): ?>
                <!-- Create Project Form -->
                <div class="card" id="create-form" style="margin-bottom: 30px;">
                    <div class="card-header">
                        <div>
                            <h3 style="margin: 0;">Create New Project</h3>
                            <p style="font-size: 13px; color: var(--text-secondary); margin: 4px 0 0 0;">
                                Fill in the details below to create a new project
                            </p>
                        </div>
                    </div>
                    <div class="card-body">
                        <form method="POST" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
                            <div class="form-group">
                                <label>Project Name <span class="required">*</span></label>
                                <input type="text" name="project_name" class="form-control" placeholder="Enter project name" required>
                            </div>
                            <div class="form-group">
                                <label>Client Name</label>
                                <input type="text" name="client_name" class="form-control" placeholder="Enter client name">
                            </div>
                            <div class="form-group">
                                <label>Assign Manager</label>
                                <select name="assigned_manager" class="form-control">
                                    <option value="">Unassigned</option>
                                    <?php foreach ($managers as $manager): ?>
                                        <option value="<?php echo $manager['id']; ?>"><?php echo e($manager['full_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Budget ($)</label>
                                <input type="number" name="budget" class="form-control" step="0.01" placeholder="0.00">
                            </div>
                            <div class="form-group">
                                <label>Start Date <span class="required">*</span></label>
                                <input type="date" name="start_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Due Date <span class="required">*</span></label>
                                <input type="date" name="due_date" class="form-control" value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Status</label>
                                <select name="status" class="form-control">
                                    <option value="planning">Planning</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="review">Review</option>
                                    <option value="on_hold">On Hold</option>
                                    <option value="completed">Completed</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Priority</label>
                                <select name="priority" class="form-control">
                                    <option value="low">Low</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                            </div>
                            <div class="form-group" style="grid-column: span 2;">
                                <label>Description</label>
                                <textarea name="description" class="form-control" rows="3" placeholder="Enter project description"></textarea>
                            </div>
                            <div style="grid-column: span 2;">
                                <button type="submit" name="create_project" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Create Project
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Projects List -->
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3 style="margin: 0;"><?php echo $pageTitle; ?> (<?php echo count($projects); ?>)</h3>
                            <p style="font-size: 13px; color: var(--text-secondary); margin: 4px 0 0 0;">
                                View and manage all projects in the system
                            </p>
                        </div>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <?php if (empty($projects)): ?>
                            <div style="text-align: center; padding: 60px 20px;">
                                <i class="fas fa-folder-open" style="font-size: 48px; color: var(--text-tertiary); margin-bottom: 16px;"></i>
                                <p style="color: var(--text-secondary); margin: 0;">No projects created yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="data-table-container" style="border: none; box-shadow: none;">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th class="sortable">Project Name</th>
                                            <th class="sortable">Client</th>
                                            <th class="sortable">Manager</th>
                                            <th>Status</th>
                                            <th>Priority</th>
                                            <th>Progress</th>
                                            <th class="sortable">Due Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($projects as $project): ?>
                                        <?php
                                        $completion = $project['task_count'] > 0 ?
                                            round(($project['completed_tasks'] / $project['task_count']) * 100) : 0;
                                        $isOverdue = isOverdue($project['due_date'], $project['status']);
                                        ?>
                                        <tr>
                                            <td>
                                                <strong style="color: var(--heading-color);"><?php echo e($project['project_name']); ?></strong>
                                            </td>
                                            <td><?php echo e($project['client_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo e($project['manager_name'] ?? 'Unassigned'); ?></td>
                                            <td>
                                                <?php
                                                $statusClass = 'badge-info';
                                                if ($project['status'] === 'completed') $statusClass = 'badge-success';
                                                elseif ($project['status'] === 'in_progress') $statusClass = 'badge-primary';
                                                elseif ($project['status'] === 'on_hold') $statusClass = 'badge-warning';
                                                elseif ($project['status'] === 'planning') $statusClass = 'badge-info';
                                                ?>
                                                <span class="badge <?php echo $statusClass; ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $project['status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php
                                                $priorityClass = 'badge-info';
                                                if ($project['priority'] === 'urgent') $priorityClass = 'badge-danger';
                                                elseif ($project['priority'] === 'high') $priorityClass = 'badge-warning';
                                                elseif ($project['priority'] === 'medium') $priorityClass = 'badge-primary';
                                                elseif ($project['priority'] === 'low') $priorityClass = 'badge-success';
                                                ?>
                                                <span class="badge <?php echo $priorityClass; ?>">
                                                    <?php echo ucfirst($project['priority']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 8px;">
                                                    <div style="flex: 1; height: 6px; background: var(--border-light); border-radius: 3px; overflow: hidden;">
                                                        <div style="width: <?php echo $completion; ?>%; height: 100%;
                                                                    background: linear-gradient(90deg, #17b06b 0%, #14d48f 100%);
                                                                    border-radius: 3px; transition: width 300ms ease;"></div>
                                                    </div>
                                                    <span style="font-size: 12px; font-weight: 600; color: var(--text-primary); min-width: 45px;">
                                                        <?php echo $completion; ?>%
                                                    </span>
                                                </div>
                                                <div style="font-size: 11px; color: var(--text-secondary); margin-top: 4px;">
                                                    <?php echo $project['completed_tasks']; ?>/<?php echo $project['task_count']; ?> tasks
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($project['due_date']): ?>
                                                    <div style="<?php echo $isOverdue ? 'color: var(--danger);' : ''; ?>">
                                                        <?php echo date('M d, Y', strtotime($project['due_date'])); ?>
                                                        <?php if ($isOverdue): ?>
                                                            <div style="font-size: 11px; margin-top: 2px;">
                                                                <i class="fas fa-exclamation-triangle"></i> Overdue
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span style="color: var(--text-tertiary);">No deadline</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="project-detail.php?id=<?php echo $project['id']; ?>" class="btn btn-primary btn-sm">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Auto-hide alerts after 5 seconds
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            }, 5000);
        });
    });
    </script>
</body>
</html>
