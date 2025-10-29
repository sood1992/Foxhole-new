<!-- Task Dependencies Component -->
<!-- Usage: include this file and set $taskId variable -->

<div class="dependencies-section" style="margin-top: 24px;">
    <h3 style="font-size: 16px; font-weight: 600; margin-bottom: 16px;">🔗 Task Dependencies</h3>

    <!-- Add Dependency -->
    <div style="background: var(--bg-tertiary); padding: 16px; border-radius: var(--radius-md); margin-bottom: 16px;">
        <form id="addDependencyForm">
            <div style="display: flex; gap: 12px; align-items: end;">
                <div class="form-group" style="flex: 1; margin-bottom: 0;">
                    <label>This task depends on:</label>
                    <select id="dependsOnSelect" class="form-control" required>
                        <option value="">Select a task...</option>
                    </select>
                </div>
                <div class="form-group" style="width: 180px; margin-bottom: 0;">
                    <label>Type:</label>
                    <select id="dependencyType" class="form-control">
                        <option value="finish_to_start">Finish to Start</option>
                        <option value="start_to_start">Start to Start</option>
                        <option value="finish_to_finish">Finish to Finish</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">
                    Add Dependency
                </button>
            </div>
        </form>
    </div>

    <!-- Dependencies List -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
        <!-- Depends On -->
        <div class="card">
            <div class="card-header">
                <h4 style="font-size: 14px; font-weight: 600; margin: 0;">⬅️ This task depends on</h4>
            </div>
            <div class="card-body">
                <div id="dependsOnList">
                    <p style="text-align: center; color: var(--text-secondary); padding: 12px;">
                        Loading...
                    </p>
                </div>
            </div>
        </div>

        <!-- Blocks -->
        <div class="card">
            <div class="card-header">
                <h4 style="font-size: 14px; font-weight: 600; margin: 0;">➡️ This task blocks</h4>
            </div>
            <div class="card-body">
                <div id="blocksList">
                    <p style="text-align: center; color: var(--text-secondary); padding: 12px;">
                        Loading...
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.dependency-item {
    padding: 12px;
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    margin-bottom: 8px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.dependency-info h5 {
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 4px;
}

.dependency-meta {
    font-size: 12px;
    color: var(--text-secondary);
}
</style>

<script>
(function() {
    const taskId = <?php echo $taskId; ?>;

    // Load available tasks for dropdown
    function loadAvailableTasks() {
        fetch('../api/tasks.php?exclude=' + taskId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const select = document.getElementById('dependsOnSelect');
                    select.innerHTML = '<option value="">Select a task...</option>';

                    data.tasks.forEach(task => {
                        const option = document.createElement('option');
                        option.value = task.id;
                        option.textContent = `${task.project_name} - ${task.task_name}`;
                        select.appendChild(option);
                    });
                }
            })
            .catch(error => console.error('Error loading tasks:', error));
    }

    // Load dependencies
    function loadDependencies() {
        fetch(`../api/dependencies.php?task_id=${taskId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayDependsOn(data.depends_on);
                    displayBlocks(data.blocks);
                }
            })
            .catch(error => console.error('Error loading dependencies:', error));
    }

    // Display tasks this depends on
    function displayDependsOn(dependencies) {
        const container = document.getElementById('dependsOnList');

        if (dependencies.length === 0) {
            container.innerHTML = '<p style="text-align: center; color: var(--text-secondary); padding: 12px;">No dependencies</p>';
            return;
        }

        let html = '';
        dependencies.forEach(dep => {
            html += `
                <div class="dependency-item">
                    <div class="dependency-info">
                        <h5>${escapeHtml(dep.task_name)}</h5>
                        <div class="dependency-meta">
                            ${escapeHtml(dep.project_name)} •
                            <span class="badge ${getStatusClass(dep.status)}">${dep.status}</span> •
                            ${dep.dependency_type.replace(/_/g, ' ')}
                        </div>
                    </div>
                    <button onclick="removeDependency(${dep.dependency_id})" class="btn btn-danger btn-sm">
                        Remove
                    </button>
                </div>
            `;
        });

        container.innerHTML = html;
    }

    // Display tasks this blocks
    function displayBlocks(blockers) {
        const container = document.getElementById('blocksList');

        if (blockers.length === 0) {
            container.innerHTML = '<p style="text-align: center; color: var(--text-secondary); padding: 12px;">Not blocking any tasks</p>';
            return;
        }

        let html = '';
        blockers.forEach(blocker => {
            html += `
                <div class="dependency-item">
                    <div class="dependency-info">
                        <h5>${escapeHtml(blocker.task_name)}</h5>
                        <div class="dependency-meta">
                            ${escapeHtml(blocker.project_name)} •
                            <span class="badge ${getStatusClass(blocker.status)}">${blocker.status}</span>
                        </div>
                    </div>
                    <button onclick="removeDependency(${blocker.dependency_id})" class="btn btn-danger btn-sm">
                        Remove
                    </button>
                </div>
            `;
        });

        container.innerHTML = html;
    }

    // Add dependency
    document.getElementById('addDependencyForm').addEventListener('submit', function(e) {
        e.preventDefault();

        const dependsOnTaskId = document.getElementById('dependsOnSelect').value;
        const dependencyType = document.getElementById('dependencyType').value;

        if (!dependsOnTaskId) {
            alert('Please select a task');
            return;
        }

        fetch('../api/dependencies.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                task_id: taskId,
                depends_on_task_id: parseInt(dependsOnTaskId),
                dependency_type: dependencyType
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Dependency added successfully');
                this.reset();
                loadDependencies();
            } else {
                alert('Failed to add dependency: ' + data.message);
            }
        })
        .catch(error => {
            alert('Error adding dependency: ' + error);
        });
    });

    // Remove dependency (global function)
    window.removeDependency = function(dependencyId) {
        if (!confirm('Are you sure you want to remove this dependency?')) return;

        fetch('../api/dependencies.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ dependency_id: dependencyId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Dependency removed successfully');
                loadDependencies();
            } else {
                alert('Failed to remove dependency: ' + data.message);
            }
        })
        .catch(error => {
            alert('Error removing dependency: ' + error);
        });
    };

    // Helper functions
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function getStatusClass(status) {
        const classes = {
            'todo': 'status-todo',
            'in_progress': 'status-progress',
            'review': 'status-review',
            'completed': 'status-completed',
            'blocked': 'status-blocked'
        };
        return classes[status] || 'status-default';
    }

    // Initial load
    loadAvailableTasks();
    loadDependencies();
})();
</script>
