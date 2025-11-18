<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !hasRole('manager')) {
    redirect('../login.php');
}

$db = getDBConnection();
$currentUser = getCurrentUser();

// Get manager's projects
$stmt = $db->prepare("
    SELECT p.*,
           (SELECT COUNT(*) FROM tasks WHERE project_id = p.id) as task_count,
           (SELECT COUNT(*) FROM tasks WHERE project_id = p.id AND status = 'completed') as completed_tasks
    FROM projects p
    WHERE p.assigned_manager = ?
    ORDER BY
        FIELD(p.status, 'in_progress', 'review', 'planning', 'on_hold', 'completed'),
        p.due_date ASC
");
$stmt->execute([$currentUser['id']]);
$projects = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Projects V3 - <?php echo SITE_NAME; ?></title>
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
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3>Projects Overview</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($projects)): ?>
                            <p style="text-align: center; color: var(--text-secondary); padding: var(--space-10);">
                                No projects assigned to you yet.
                            </p>
                        <?php else: ?>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Project Name</th>
                                        <th>Client</th>
                                        <th>Status</th>
                                        <th>Priority</th>
                                        <th>Progress</th>
                                        <th>Due Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($projects as $project): ?>
                                    <tr>
                                        <td><strong><?php echo e($project['project_name']); ?></strong></td>
                                        <td><?php echo e($project['client_name'] ?? 'N/A'); ?></td>
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
                                            <?php
                                            $completion = $project['task_count'] > 0 ?
                                                round(($project['completed_tasks'] / $project['task_count']) * 100) : 0;
                                            ?>
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
                                                    <br><span style="color: var(--status-blocked);">⚠️ Overdue</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span style="color: var(--text-tertiary);">No deadline</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div style="display: flex; gap: var(--space-2);">
                                                <a href="../admin/project-detail.php?id=<?php echo $project['id']; ?>" class="btn btn-primary btn-sm">
                                                    View
                                                </a>
                                                <button onclick="editProject(<?php echo $project['id']; ?>)" class="btn btn-secondary btn-sm">
                                                    Edit
                                                </button>
                                                <button onclick="deleteProject(<?php echo $project['id']; ?>, '<?php echo addslashes($project['project_name']); ?>')" class="btn btn-danger btn-sm">
                                                    Delete
                                                </button>
                                            </div>
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

    <!-- Edit Project Modal -->
    <div id="editProjectModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: var(--bg-primary); padding: var(--space-6); border-radius: var(--radius-lg); width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-4);">
                <h3 style="margin: 0;">Edit Project</h3>
                <button onclick="closeEditModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: var(--text-secondary);">&times;</button>
            </div>

            <form id="editProjectForm" onsubmit="saveProject(event)">
                <input type="hidden" id="edit_project_id">

                <div style="margin-bottom: var(--space-4);">
                    <label style="display: block; margin-bottom: var(--space-2); font-weight: 500;">Project Name *</label>
                    <input type="text" id="edit_project_name" required style="width: 100%; padding: var(--space-2); border: 1px solid var(--border-color); border-radius: var(--radius-md); background: var(--bg-secondary);">
                </div>

                <div style="margin-bottom: var(--space-4);">
                    <label style="display: block; margin-bottom: var(--space-2); font-weight: 500;">Client Name</label>
                    <input type="text" id="edit_client_name" style="width: 100%; padding: var(--space-2); border: 1px solid var(--border-color); border-radius: var(--radius-md); background: var(--bg-secondary);">
                </div>

                <div style="margin-bottom: var(--space-4);">
                    <label style="display: block; margin-bottom: var(--space-2); font-weight: 500;">Description</label>
                    <textarea id="edit_description" rows="3" style="width: 100%; padding: var(--space-2); border: 1px solid var(--border-color); border-radius: var(--radius-md); background: var(--bg-secondary);"></textarea>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4); margin-bottom: var(--space-4);">
                    <div>
                        <label style="display: block; margin-bottom: var(--space-2); font-weight: 500;">Status</label>
                        <select id="edit_status" style="width: 100%; padding: var(--space-2); border: 1px solid var(--border-color); border-radius: var(--radius-md); background: var(--bg-secondary);">
                            <option value="planning">Planning</option>
                            <option value="in_progress">In Progress</option>
                            <option value="review">Review</option>
                            <option value="completed">Completed</option>
                            <option value="on_hold">On Hold</option>
                        </select>
                    </div>

                    <div>
                        <label style="display: block; margin-bottom: var(--space-2); font-weight: 500;">Priority</label>
                        <select id="edit_priority" style="width: 100%; padding: var(--space-2); border: 1px solid var(--border-color); border-radius: var(--radius-md); background: var(--bg-secondary);">
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: var(--space-4); margin-bottom: var(--space-4);">
                    <div>
                        <label style="display: block; margin-bottom: var(--space-2); font-weight: 500;">Start Date</label>
                        <input type="date" id="edit_start_date" style="width: 100%; padding: var(--space-2); border: 1px solid var(--border-color); border-radius: var(--radius-md); background: var(--bg-secondary);">
                    </div>

                    <div>
                        <label style="display: block; margin-bottom: var(--space-2); font-weight: 500;">Due Date</label>
                        <input type="date" id="edit_due_date" style="width: 100%; padding: var(--space-2); border: 1px solid var(--border-color); border-radius: var(--radius-md); background: var(--bg-secondary);">
                    </div>

                    <div>
                        <label style="display: block; margin-bottom: var(--space-2); font-weight: 500;">Estimated Hours</label>
                        <input type="number" id="edit_estimated_hours" min="0" step="0.5" style="width: 100%; padding: var(--space-2); border: 1px solid var(--border-color); border-radius: var(--radius-md); background: var(--bg-secondary);">
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: var(--space-3); margin-top: var(--space-6);">
                    <button type="button" onclick="closeEditModal()" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/theme.js"></script>
    <script>
        // Edit Project
        async function editProject(projectId) {
            try {
                const response = await fetch(`../api/projects.php?id=${projectId}`);
                const data = await response.json();

                if (data.success) {
                    const project = data.project;
                    document.getElementById('edit_project_id').value = project.id;
                    document.getElementById('edit_project_name').value = project.project_name;
                    document.getElementById('edit_client_name').value = project.client_name || '';
                    document.getElementById('edit_description').value = project.description || '';
                    document.getElementById('edit_status').value = project.status;
                    document.getElementById('edit_priority').value = project.priority;
                    document.getElementById('edit_start_date').value = project.start_date || '';
                    document.getElementById('edit_due_date').value = project.due_date || '';
                    document.getElementById('edit_estimated_hours').value = project.estimated_hours || '';

                    document.getElementById('editProjectModal').style.display = 'flex';
                } else {
                    alert('Error loading project: ' + data.message);
                }
            } catch (error) {
                alert('Error loading project: ' + error.message);
            }
        }

        // Save Project
        async function saveProject(event) {
            event.preventDefault();

            const projectData = {
                id: parseInt(document.getElementById('edit_project_id').value),
                project_name: document.getElementById('edit_project_name').value,
                client_name: document.getElementById('edit_client_name').value,
                description: document.getElementById('edit_description').value,
                status: document.getElementById('edit_status').value,
                priority: document.getElementById('edit_priority').value,
                start_date: document.getElementById('edit_start_date').value,
                due_date: document.getElementById('edit_due_date').value,
                estimated_hours: parseFloat(document.getElementById('edit_estimated_hours').value) || 0
            };

            try {
                const response = await fetch('../api/projects.php', {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(projectData)
                });

                const data = await response.json();

                if (data.success) {
                    alert('Project updated successfully!');
                    location.reload();
                } else {
                    alert('Error updating project: ' + data.message);
                }
            } catch (error) {
                alert('Error updating project: ' + error.message);
            }
        }

        // Delete Project
        async function deleteProject(projectId, projectName) {
            if (!confirm(`Are you sure you want to delete "${projectName}"?\n\nThis action cannot be undone. The project must have no tasks to be deleted.`)) {
                return;
            }

            try {
                const response = await fetch('../api/projects.php', {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ id: projectId })
                });

                const data = await response.json();

                if (data.success) {
                    alert('Project deleted successfully!');
                    location.reload();
                } else {
                    alert('Error deleting project: ' + data.message);
                }
            } catch (error) {
                alert('Error deleting project: ' + error.message);
            }
        }

        // Close Modal
        function closeEditModal() {
            document.getElementById('editProjectModal').style.display = 'none';
        }

        // Close modal on outside click
        document.getElementById('editProjectModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditModal();
            }
        });
    </script>
</body>
</html>
