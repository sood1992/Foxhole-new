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

// Get status filter from query parameter
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Build query based on filter
$query = "
    SELECT p.*,
           u.full_name as manager_name,
           (SELECT COUNT(*) FROM tasks WHERE project_id = p.id) as task_count,
           (SELECT COUNT(*) FROM tasks WHERE project_id = p.id AND status = 'completed') as completed_tasks
    FROM projects p
    LEFT JOIN users u ON p.assigned_manager = u.id
";

// Add WHERE clause if status filter is applied
if ($statusFilter !== 'all') {
    $query .= " WHERE p.status = " . $db->quote($statusFilter);
}

$query .= " ORDER BY
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
                <div style="margin-bottom: 30px;">
                    <h1 style="margin-bottom: 8px;">Projects</h1>
                    <p style="color: var(--text-secondary); font-size: 14px; margin: 0;">
                        Manage all projects, assign managers, and track progress
                    </p>
                </div>

                <!-- Tab Navigation -->
                <div class="tab-nav" style="margin-bottom: 30px;">
                    <a href="projects.php" class="tab-link <?php echo ($statusFilter === 'all') ? 'active' : ''; ?>">
                        <i class="fas fa-list"></i> All Projects
                    </a>
                    <a href="projects.php?status=in_progress" class="tab-link <?php echo ($statusFilter === 'in_progress') ? 'active' : ''; ?>">
                        <i class="fas fa-spinner"></i> In Progress
                    </a>
                    <a href="projects.php?status=completed" class="tab-link <?php echo ($statusFilter === 'completed') ? 'active' : ''; ?>">
                        <i class="fas fa-check-circle"></i> Completed
                    </a>
                    <a href="#create" class="tab-link" id="createProjectTab">
                        <i class="fas fa-plus"></i> Create Project
                    </a>
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

                <!-- Create Project Form -->
                <div class="card" style="margin-bottom: 30px;">
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

                <!-- Projects List -->
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3 style="margin: 0;">
                                <?php
                                if ($statusFilter === 'in_progress') {
                                    echo 'In Progress Projects';
                                } elseif ($statusFilter === 'completed') {
                                    echo 'Completed Projects';
                                } else {
                                    echo 'All Projects';
                                }
                                ?> (<?php echo count($projects); ?>)
                            </h3>
                            <p style="font-size: 13px; color: var(--text-secondary); margin: 4px 0 0 0;">
                                <?php
                                if ($statusFilter === 'in_progress') {
                                    echo 'Projects currently in progress';
                                } elseif ($statusFilter === 'completed') {
                                    echo 'Successfully completed projects';
                                } else {
                                    echo 'View and manage all projects in the system';
                                }
                                ?>
                            </p>
                        </div>
                        <button id="bulkDeleteProjectsBtn" class="btn btn-danger btn-sm" style="display: none;">
                            <i class="fas fa-trash"></i> Delete Selected (<span id="selectedProjectCount">0</span>)
                        </button>
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
                                            <th style="width: 40px;">
                                                <input type="checkbox" id="selectAllProjects" style="cursor: pointer;">
                                            </th>
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
                                                <input type="checkbox" class="project-checkbox" value="<?php echo $project['id']; ?>" style="cursor: pointer;">
                                            </td>
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
        // Get the create form and projects list sections
        const createFormSection = document.querySelectorAll('.card')[0]; // First card is create form
        const projectsListSection = document.querySelectorAll('.card')[1]; // Second card is projects list

        // Check URL parameters and hash
        const urlParams = new URLSearchParams(window.location.search);
        const statusFilter = urlParams.get('status');
        const hash = window.location.hash;

        // Function to show/hide sections
        function showSection(section) {
            if (section === 'create') {
                createFormSection.style.display = 'block';
                projectsListSection.style.display = 'none';
                // Update active tab
                document.querySelectorAll('.tab-link').forEach(t => t.classList.remove('active'));
                document.getElementById('createProjectTab').classList.add('active');
            } else {
                createFormSection.style.display = 'none';
                projectsListSection.style.display = 'block';
            }
        }

        // Determine which section to show on page load
        if (hash === '#create') {
            showSection('create');
        } else if (statusFilter || urlParams.toString() === '') {
            // Show projects list if there's a status filter or no parameters (all projects)
            showSection('list');
        } else {
            showSection('list'); // Default to list view
        }

        // Handle "Create Project" tab click
        const createProjectTab = document.getElementById('createProjectTab');
        if (createProjectTab) {
            createProjectTab.addEventListener('click', function(e) {
                e.preventDefault();
                showSection('create');
                window.history.pushState({}, '', window.location.pathname + '#create');
            });
        }

        // Handle other tab clicks (show projects list)
        document.querySelectorAll('.tab-link[href^="projects.php"]').forEach(tab => {
            tab.addEventListener('click', function(e) {
                // Let the default navigation happen, but ensure we show the list
                setTimeout(() => showSection('list'), 100);
            });
        });

        // Bulk delete functionality for projects
        const selectAllProjectsCheckbox = document.getElementById('selectAllProjects');
        const projectCheckboxes = document.querySelectorAll('.project-checkbox');
        const bulkDeleteProjectsBtn = document.getElementById('bulkDeleteProjectsBtn');
        const selectedProjectCountSpan = document.getElementById('selectedProjectCount');

        if (selectAllProjectsCheckbox && projectCheckboxes.length > 0) {
            // Select/Deselect All Projects
            selectAllProjectsCheckbox.addEventListener('change', function() {
                projectCheckboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
                updateBulkDeleteProjectsButton();
            });

            // Update bulk delete button visibility and count
            projectCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    updateBulkDeleteProjectsButton();

                    // Update select all checkbox state
                    const allChecked = Array.from(projectCheckboxes).every(cb => cb.checked);
                    const noneChecked = Array.from(projectCheckboxes).every(cb => !cb.checked);
                    selectAllProjectsCheckbox.checked = allChecked;
                    selectAllProjectsCheckbox.indeterminate = !allChecked && !noneChecked;
                });
            });

            // Bulk delete button click
            bulkDeleteProjectsBtn.addEventListener('click', function() {
                const checkedBoxes = document.querySelectorAll('.project-checkbox:checked');
                document.getElementById('deleteProjectCount').textContent = checkedBoxes.length;
                document.getElementById('bulkDeleteProjectsModal').style.display = 'flex';
            });
        }

        function updateBulkDeleteProjectsButton() {
            const checkedBoxes = document.querySelectorAll('.project-checkbox:checked');
            const count = checkedBoxes.length;

            if (count > 0) {
                bulkDeleteProjectsBtn.style.display = 'inline-flex';
                selectedProjectCountSpan.textContent = count;
            } else {
                bulkDeleteProjectsBtn.style.display = 'none';
            }
        }

        // Auto-hide alerts after 5 seconds
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            }, 5000);
        });
    });

    function closeBulkDeleteProjectsModal() {
        document.getElementById('bulkDeleteProjectsModal').style.display = 'none';
    }

    function confirmBulkDeleteProjects() {
        const checkedBoxes = document.querySelectorAll('.project-checkbox:checked');
        const projectIds = Array.from(checkedBoxes).map(cb => cb.value);

        if (projectIds.length === 0) {
            closeBulkDeleteProjectsModal();
            return;
        }

        // Show loading state
        const deleteBtn = event.target.closest('button');
        deleteBtn.disabled = true;
        deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';

        // Send delete request
        fetch('../api/bulk-delete-projects.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ project_ids: projectIds })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Reload page to show updated list
                window.location.href = 'projects.php?deleted=' + data.deleted_count;
            } else {
                alert('Error: ' + (data.message || 'Failed to delete projects'));
                deleteBtn.disabled = false;
                deleteBtn.innerHTML = '<i class="fas fa-trash"></i> Delete Projects';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while deleting projects');
            deleteBtn.disabled = false;
            deleteBtn.innerHTML = '<i class="fas fa-trash"></i> Delete Projects';
        });
    }
    </script>

    <!-- Bulk Delete Projects Modal -->
    <div id="bulkDeleteProjectsModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 10000; align-items: center; justify-content: center;">
        <div class="card" style="max-width: 500px; margin: 20px;">
            <div class="card-header">
                <h3 style="margin: 0; color: var(--danger);"><i class="fas fa-exclamation-triangle"></i> Confirm Bulk Delete</h3>
            </div>
            <div class="card-body">
                <p>Are you sure you want to delete <strong id="deleteProjectCount">0</strong> selected project(s)?</p>
                <p style="color: var(--danger); font-size: 13px; margin-top: 10px;">
                    <i class="fas fa-info-circle"></i> Warning: This will also delete all tasks, time logs, and related data for these projects. This action cannot be undone.
                </p>
            </div>
            <div class="card-footer" style="display: flex; gap: 10px; justify-content: flex-end;">
                <button onclick="closeBulkDeleteProjectsModal()" class="btn btn-outline">Cancel</button>
                <button onclick="confirmBulkDeleteProjects()" class="btn btn-danger">
                    <i class="fas fa-trash"></i> Delete Projects
                </button>
            </div>
        </div>
    </div>
</body>
</html>
