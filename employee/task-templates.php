<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !hasRole('employee')) {
    redirect('../login.php');
}

$db = getDBConnection();
$currentUser = getCurrentUser();

// Get my projects for template instantiation
$myProjects = $db->prepare("
    SELECT DISTINCT p.id, p.project_name
    FROM projects p
    JOIN tasks t ON p.id = t.project_id
    WHERE t.assigned_to = ?
    ORDER BY p.project_name
");
$myProjects->execute([$currentUser['id']]);
$projects = $myProjects->fetchAll();

// Category filter
$categoryFilter = $_GET['category'] ?? 'all';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Templates - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/vien-v3.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .templates-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 24px;
            margin-top: 24px;
        }

        .template-card {
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            padding: 24px;
            transition: all 0.3s;
            border: 2px solid transparent;
        }

        .template-card:hover {
            border-color: var(--primary);
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.2);
        }

        .template-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 16px;
        }

        .template-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-right: 16px;
        }

        .template-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .template-meta {
            font-size: 12px;
            color: var(--text-secondary);
            margin-bottom: 12px;
        }

        .template-description {
            font-size: 14px;
            color: var(--text-secondary);
            margin-bottom: 16px;
            line-height: 1.6;
        }

        .template-stats {
            display: flex;
            gap: 16px;
            margin-bottom: 16px;
            padding-top: 16px;
            border-top: 1px solid var(--border-color);
        }

        .template-stat {
            font-size: 12px;
        }

        .template-stat strong {
            color: var(--primary);
            font-size: 16px;
        }

        .template-actions {
            display: flex;
            gap: 8px;
        }

        .category-badge {
            display: inline-block;
            padding: 4px 12px;
            background: var(--bg-tertiary);
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
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
                <div class="page-header">
                    <div>
                        <h1>📋 Task Templates</h1>
                        <p class="page-subtitle">Quickly create common task sets from templates</p>
                    </div>
                    <button onclick="openCreateTemplateModal()" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Create Template
                    </button>
                </div>

                <!-- Category Filter -->
                <div class="dashboard-card" style="margin-bottom: 24px;">
                    <div class="card-body" style="padding: 16px;">
                        <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                            <a href="?category=all" class="btn <?php echo $categoryFilter === 'all' ? 'btn-primary' : 'btn-secondary'; ?> btn-sm">
                                All Templates
                            </a>
                            <a href="?category=development" class="btn <?php echo $categoryFilter === 'development' ? 'btn-primary' : 'btn-secondary'; ?> btn-sm">
                                Development
                            </a>
                            <a href="?category=design" class="btn <?php echo $categoryFilter === 'design' ? 'btn-primary' : 'btn-secondary'; ?> btn-sm">
                                Design
                            </a>
                            <a href="?category=marketing" class="btn <?php echo $categoryFilter === 'marketing' ? 'btn-primary' : 'btn-secondary'; ?> btn-sm">
                                Marketing
                            </a>
                            <a href="?category=content" class="btn <?php echo $categoryFilter === 'content' ? 'btn-primary' : 'btn-secondary'; ?> btn-sm">
                                Content
                            </a>
                            <a href="?category=other" class="btn <?php echo $categoryFilter === 'other' ? 'btn-primary' : 'btn-secondary'; ?> btn-sm">
                                Other
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Templates Grid -->
                <div id="templatesGrid" class="templates-grid">
                    <div style="grid-column: 1/-1; text-align: center; padding: 60px 20px;">
                        <div style="font-size: 48px; margin-bottom: 16px;">⏳</div>
                        <p>Loading templates...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Use Template Modal -->
    <div id="useTemplateModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 9999; align-items: center; justify-content: center;">
        <div style="background: var(--bg-secondary); border-radius: var(--radius-lg); padding: 32px; max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto;">
            <h3 style="margin-bottom: 24px;">Use Template: <span id="modalTemplateName"></span></h3>

            <div class="form-group">
                <label>Select Project</label>
                <select id="templateProjectId" class="form-control">
                    <option value="">Choose a project...</option>
                    <?php foreach ($projects as $proj): ?>
                        <option value="<?php echo $proj['id']; ?>"><?php echo e($proj['project_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div id="templateItemsList" style="margin: 24px 0; padding: 16px; background: var(--bg-tertiary); border-radius: var(--radius-md);">
                <h4 style="margin-bottom: 12px;">Tasks that will be created:</h4>
                <div id="templateItems"></div>
            </div>

            <div style="display: flex; gap: 12px;">
                <button onclick="instantiateTemplate()" class="btn btn-success" style="flex: 1;">
                    <i class="fas fa-magic"></i> Create Tasks
                </button>
                <button onclick="closeUseTemplateModal()" class="btn btn-secondary">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Create Template Modal -->
    <div id="createTemplateModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 9999; align-items: center; justify-content: center;">
        <div style="background: var(--bg-secondary); border-radius: var(--radius-lg); padding: 32px; max-width: 700px; width: 90%; max-height: 85vh; overflow-y: auto;">
            <h3 style="margin-bottom: 24px;">Create New Template</h3>

            <div class="form-group">
                <label>Template Name</label>
                <input type="text" id="newTemplateName" class="form-control" placeholder="e.g., Website Launch Checklist">
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea id="newTemplateDescription" class="form-control" rows="3" placeholder="Describe what this template is for..."></textarea>
            </div>

            <div class="form-group">
                <label>Category</label>
                <select id="newTemplateCategory" class="form-control">
                    <option value="development">Development</option>
                    <option value="design">Design</option>
                    <option value="marketing">Marketing</option>
                    <option value="content">Content</option>
                    <option value="other">Other</option>
                </select>
            </div>

            <div class="form-group">
                <label>
                    <input type="checkbox" id="newTemplatePublic"> Make this template available to all users
                </label>
            </div>

            <div style="margin: 24px 0;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                    <h4>Tasks</h4>
                    <button onclick="addTemplateTask()" class="btn btn-sm btn-secondary">
                        <i class="fas fa-plus"></i> Add Task
                    </button>
                </div>
                <div id="templateTasksList"></div>
            </div>

            <div style="display: flex; gap: 12px;">
                <button onclick="saveTemplate()" class="btn btn-success" style="flex: 1;">
                    <i class="fas fa-save"></i> Save Template
                </button>
                <button onclick="closeCreateTemplateModal()" class="btn btn-secondary">Cancel</button>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script>
        let currentTemplateId = null;
        let currentTemplate = null;
        let templateTasks = [{ task_name: '', description: '', estimated_hours: '', priority: 'medium' }];

        // Load templates on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadTemplates();
        });

        async function loadTemplates() {
            try {
                const response = await fetch('../api/task-templates.php?action=list');
                const data = await response.json();

                if (data.success) {
                    displayTemplates(data.templates);
                } else {
                    document.getElementById('templatesGrid').innerHTML =
                        '<div style="grid-column: 1/-1; text-align: center; padding: 60px 20px;"><p>Failed to load templates</p></div>';
                }
            } catch (error) {
                console.error('Error:', error);
                document.getElementById('templatesGrid').innerHTML =
                    '<div style="grid-column: 1/-1; text-align: center; padding: 60px 20px;"><p>Error loading templates</p></div>';
            }
        }

        function displayTemplates(templates) {
            const grid = document.getElementById('templatesGrid');
            const categoryFilter = '<?php echo $categoryFilter; ?>';

            const filteredTemplates = categoryFilter === 'all'
                ? templates
                : templates.filter(t => t.category === categoryFilter);

            if (filteredTemplates.length === 0) {
                grid.innerHTML = `
                    <div style="grid-column: 1/-1; text-align: center; padding: 60px 20px;">
                        <div style="font-size: 64px; margin-bottom: 16px;">📋</div>
                        <h3>No templates found</h3>
                        <p style="color: var(--text-secondary);">Create your first template to get started</p>
                        <button onclick="openCreateTemplateModal()" class="btn btn-primary" style="margin-top: 16px;">
                            <i class="fas fa-plus"></i> Create Template
                        </button>
                    </div>
                `;
                return;
            }

            grid.innerHTML = filteredTemplates.map(template => `
                <div class="template-card">
                    <div class="template-header">
                        <div style="flex: 1;">
                            <div class="template-title">${escapeHtml(template.template_name)}</div>
                            <div class="template-meta">
                                <span class="category-badge">${escapeHtml(template.category)}</span>
                                ${template.is_public ? '<span style="margin-left: 8px;">🌐 Public</span>' : ''}
                            </div>
                        </div>
                    </div>

                    ${template.description ? `<div class="template-description">${escapeHtml(template.description)}</div>` : ''}

                    <div class="template-stats">
                        <div class="template-stat">
                            <strong>${template.task_count}</strong><br>
                            <span>Tasks</span>
                        </div>
                        <div class="template-stat">
                            <span style="font-size: 12px; color: var(--text-secondary);">By ${escapeHtml(template.creator_name)}</span>
                        </div>
                    </div>

                    <div class="template-actions">
                        <button onclick="openUseTemplateModal(${template.id})" class="btn btn-primary" style="flex: 1;">
                            <i class="fas fa-magic"></i> Use Template
                        </button>
                        ${template.created_by == <?php echo $currentUser['id']; ?> ? `
                            <button onclick="deleteTemplate(${template.id})" class="btn btn-danger btn-sm">
                                <i class="fas fa-trash"></i>
                            </button>
                        ` : ''}
                    </div>
                </div>
            `).join('');
        }

        async function openUseTemplateModal(templateId) {
            currentTemplateId = templateId;

            try {
                const response = await fetch(`../api/task-templates.php?action=get&template_id=${templateId}`);
                const data = await response.json();

                if (data.success) {
                    currentTemplate = data.template;
                    document.getElementById('modalTemplateName').textContent = currentTemplate.template_name;

                    const itemsHtml = currentTemplate.items.map((item, index) => `
                        <div style="padding: 12px; background: var(--bg-secondary); border-radius: var(--radius-sm); margin-bottom: 8px;">
                            <div style="font-weight: 600;">${index + 1}. ${escapeHtml(item.task_name)}</div>
                            ${item.description ? `<div style="font-size: 13px; color: var(--text-secondary); margin-top: 4px;">${escapeHtml(item.description)}</div>` : ''}
                            <div style="margin-top: 8px; font-size: 12px;">
                                <span class="badge ${getPriorityClass(item.priority)}">${item.priority}</span>
                                ${item.estimated_hours ? `<span style="margin-left: 8px;">⏱️ ${item.estimated_hours}h</span>` : ''}
                            </div>
                        </div>
                    `).join('');

                    document.getElementById('templateItems').innerHTML = itemsHtml;
                    document.getElementById('useTemplateModal').style.display = 'flex';
                } else {
                    alert(data.message || 'Failed to load template');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Failed to load template');
            }
        }

        function closeUseTemplateModal() {
            document.getElementById('useTemplateModal').style.display = 'none';
            currentTemplateId = null;
            currentTemplate = null;
            document.getElementById('templateProjectId').value = '';
        }

        async function instantiateTemplate() {
            const projectId = document.getElementById('templateProjectId').value;

            if (!projectId) {
                alert('Please select a project');
                return;
            }

            try {
                const response = await fetch('../api/task-templates.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'instantiate',
                        template_id: currentTemplateId,
                        project_id: projectId,
                        assigned_to: <?php echo $currentUser['id']; ?>
                    })
                });

                const data = await response.json();

                if (data.success) {
                    alert(data.message);
                    closeUseTemplateModal();
                    window.location.href = 'tasks.php';
                } else {
                    alert(data.message || 'Failed to create tasks');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Failed to create tasks');
            }
        }

        function openCreateTemplateModal() {
            templateTasks = [{ task_name: '', description: '', estimated_hours: '', priority: 'medium' }];
            renderTemplateTasks();
            document.getElementById('createTemplateModal').style.display = 'flex';
        }

        function closeCreateTemplateModal() {
            document.getElementById('createTemplateModal').style.display = 'none';
            document.getElementById('newTemplateName').value = '';
            document.getElementById('newTemplateDescription').value = '';
            document.getElementById('newTemplatePublic').checked = false;
        }

        function addTemplateTask() {
            templateTasks.push({ task_name: '', description: '', estimated_hours: '', priority: 'medium' });
            renderTemplateTasks();
        }

        function removeTemplateTask(index) {
            templateTasks.splice(index, 1);
            if (templateTasks.length === 0) {
                templateTasks.push({ task_name: '', description: '', estimated_hours: '', priority: 'medium' });
            }
            renderTemplateTasks();
        }

        function renderTemplateTasks() {
            const container = document.getElementById('templateTasksList');
            container.innerHTML = templateTasks.map((task, index) => `
                <div style="padding: 16px; background: var(--bg-tertiary); border-radius: var(--radius-md); margin-bottom: 12px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                        <strong>Task ${index + 1}</strong>
                        ${templateTasks.length > 1 ? `
                            <button onclick="removeTemplateTask(${index})" class="btn btn-sm btn-danger">
                                <i class="fas fa-trash"></i>
                            </button>
                        ` : ''}
                    </div>
                    <div class="form-group" style="margin-bottom: 12px;">
                        <input type="text" class="form-control" placeholder="Task name"
                               value="${escapeHtml(task.task_name)}"
                               onchange="templateTasks[${index}].task_name = this.value">
                    </div>
                    <div class="form-group" style="margin-bottom: 12px;">
                        <textarea class="form-control" rows="2" placeholder="Description (optional)"
                                  onchange="templateTasks[${index}].description = this.value">${escapeHtml(task.description)}</textarea>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <select class="form-control" onchange="templateTasks[${index}].priority = this.value">
                                <option value="low" ${task.priority === 'low' ? 'selected' : ''}>Low</option>
                                <option value="medium" ${task.priority === 'medium' ? 'selected' : ''}>Medium</option>
                                <option value="high" ${task.priority === 'high' ? 'selected' : ''}>High</option>
                                <option value="urgent" ${task.priority === 'urgent' ? 'selected' : ''}>Urgent</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <input type="number" class="form-control" placeholder="Est. hours"
                                   value="${task.estimated_hours}"
                                   onchange="templateTasks[${index}].estimated_hours = this.value"
                                   step="0.5" min="0">
                        </div>
                    </div>
                </div>
            `).join('');
        }

        async function saveTemplate() {
            const templateName = document.getElementById('newTemplateName').value.trim();
            const description = document.getElementById('newTemplateDescription').value.trim();
            const category = document.getElementById('newTemplateCategory').value;
            const isPublic = document.getElementById('newTemplatePublic').checked ? 1 : 0;

            if (!templateName) {
                alert('Please enter a template name');
                return;
            }

            const validTasks = templateTasks.filter(t => t.task_name.trim() !== '');
            if (validTasks.length === 0) {
                alert('Please add at least one task');
                return;
            }

            try {
                const response = await fetch('../api/task-templates.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'create',
                        template_name: templateName,
                        description: description,
                        category: category,
                        is_public: isPublic,
                        items: validTasks
                    })
                });

                const data = await response.json();

                if (data.success) {
                    alert('Template created successfully!');
                    closeCreateTemplateModal();
                    loadTemplates();
                } else {
                    alert(data.message || 'Failed to create template');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Failed to create template');
            }
        }

        async function deleteTemplate(templateId) {
            if (!confirm('Are you sure you want to delete this template?')) {
                return;
            }

            try {
                const response = await fetch('../api/task-templates.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'delete',
                        template_id: templateId
                    })
                });

                const data = await response.json();

                if (data.success) {
                    alert('Template deleted successfully');
                    loadTemplates();
                } else {
                    alert(data.message || 'Failed to delete template');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Failed to delete template');
            }
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function getPriorityClass(priority) {
            const classes = {
                'low': 'status-todo',
                'medium': 'status-progress',
                'high': 'status-review',
                'urgent': 'status-urgent'
            };
            return classes[priority] || 'status-todo';
        }

        // Close modals on ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeUseTemplateModal();
                closeCreateTemplateModal();
            }
        });
    </script>
    <script src="../assets/js/theme.js"></script>
</body>
</html>
