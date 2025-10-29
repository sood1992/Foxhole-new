// Global Search Functionality with Cmd/Ctrl+K shortcut
(function() {
    const modal = document.getElementById('globalSearchModal');
    const input = document.getElementById('globalSearchInput');
    const resultsContainer = document.getElementById('searchResults');
    const placeholder = document.getElementById('searchPlaceholder');
    let searchTimeout;

    // Keyboard shortcut (Cmd/Ctrl + K)
    document.addEventListener('keydown', function(e) {
        if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
            e.preventDefault();
            openSearch();
        }

        // ESC to close
        if (e.key === 'Escape' && modal.style.display === 'flex') {
            closeSearch();
        }
    });

    // Click backdrop to close
    modal.querySelector('.search-modal-backdrop').addEventListener('click', closeSearch);

    // Search input
    input.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const query = input.value.trim();

        if (query.length < 2) {
            showPlaceholder();
            return;
        }

        searchTimeout = setTimeout(() => {
            performSearch(query);
        }, 300);
    });

    function openSearch() {
        modal.style.display = 'flex';
        input.focus();
        input.value = '';
        showPlaceholder();
    }

    function closeSearch() {
        modal.style.display = 'none';
        input.value = '';
    }

    function showPlaceholder() {
        placeholder.style.display = 'block';
        resultsContainer.style.display = 'none';
    }

    function showResults() {
        placeholder.style.display = 'none';
        resultsContainer.style.display = 'block';
    }

    async function performSearch(query) {
        try {
            const response = await fetch(`../api/search.php?q=${encodeURIComponent(query)}`);
            const data = await response.json();

            showResults();
            displayResults(data);
        } catch (error) {
            console.error('Search error:', error);
        }
    }

    function displayResults(data) {
        const projectsSection = document.getElementById('projectsSection');
        const tasksSection = document.getElementById('tasksSection');
        const usersSection = document.getElementById('usersSection');
        const noResults = document.getElementById('noResults');

        const projectsList = document.getElementById('projectsList');
        const tasksList = document.getElementById('tasksList');
        const usersList = document.getElementById('usersList');

        // Clear previous results
        projectsList.innerHTML = '';
        tasksList.innerHTML = '';
        usersList.innerHTML = '';

        let hasResults = false;

        // Display projects
        if (data.projects && data.projects.length > 0) {
            hasResults = true;
            projectsSection.style.display = 'block';
            data.projects.forEach(project => {
                const item = createProjectItem(project);
                projectsList.appendChild(item);
            });
        } else {
            projectsSection.style.display = 'none';
        }

        // Display tasks
        if (data.tasks && data.tasks.length > 0) {
            hasResults = true;
            tasksSection.style.display = 'block';
            data.tasks.forEach(task => {
                const item = createTaskItem(task);
                tasksList.appendChild(item);
            });
        } else {
            tasksSection.style.display = 'none';
        }

        // Display users
        if (data.users && data.users.length > 0) {
            hasResults = true;
            usersSection.style.display = 'block';
            data.users.forEach(user => {
                const item = createUserItem(user);
                usersList.appendChild(item);
            });
        } else {
            usersSection.style.display = 'none';
        }

        // Show no results message
        noResults.style.display = hasResults ? 'none' : 'block';
    }

    function createProjectItem(project) {
        const item = document.createElement('a');
        item.href = getProjectUrl(project.id);
        item.className = 'search-item';

        const statusColors = {
            'planning': '#f59e0b',
            'in_progress': '#3b82f6',
            'review': '#8b5cf6',
            'completed': '#10b981'
        };

        item.innerHTML = `
            <div class="search-item-title">${escapeHtml(project.project_name)}</div>
            <div class="search-item-meta">
                <span>${escapeHtml(project.client_name || 'No client')}</span>
                <span>•</span>
                <span class="search-item-badge" style="background: ${statusColors[project.status] || '#6b7280'}20; color: ${statusColors[project.status] || '#6b7280'};">
                    ${formatStatus(project.status)}
                </span>
                ${project.manager_name ? `<span>•</span><span>👤 ${escapeHtml(project.manager_name)}</span>` : ''}
            </div>
        `;

        item.addEventListener('click', closeSearch);
        return item;
    }

    function createTaskItem(task) {
        const item = document.createElement('a');
        item.href = getTaskUrl(task.id);
        item.className = 'search-item';

        const priorityIcons = {
            'low': '↓',
            'medium': '→',
            'high': '↑',
            'urgent': '⚡'
        };

        const statusColors = {
            'todo': '#f59e0b',
            'in_progress': '#3b82f6',
            'review': '#8b5cf6',
            'completed': '#10b981',
            'blocked': '#ef4444'
        };

        item.innerHTML = `
            <div class="search-item-title">${priorityIcons[task.priority] || ''} ${escapeHtml(task.task_name)}</div>
            <div class="search-item-meta">
                <span>📁 ${escapeHtml(task.project_name)}</span>
                <span>•</span>
                <span class="search-item-badge" style="background: ${statusColors[task.status] || '#6b7280'}20; color: ${statusColors[task.status] || '#6b7280'};">
                    ${formatStatus(task.status)}
                </span>
                ${task.assigned_to_name ? `<span>•</span><span>👤 ${escapeHtml(task.assigned_to_name)}</span>` : ''}
            </div>
        `;

        item.addEventListener('click', closeSearch);
        return item;
    }

    function createUserItem(user) {
        const item = document.createElement('a');
        item.href = getUserUrl(user.id);
        item.className = 'search-item';

        item.innerHTML = `
            <div class="search-item-title">${escapeHtml(user.full_name)}</div>
            <div class="search-item-meta">
                <span>📧 ${escapeHtml(user.email)}</span>
                ${user.job_title ? `<span>•</span><span>${escapeHtml(user.job_title)}</span>` : ''}
                <span>•</span>
                <span class="search-item-badge">${formatRole(user.role)}</span>
            </div>
        `;

        item.addEventListener('click', closeSearch);
        return item;
    }

    function getProjectUrl(projectId) {
        // Determine base path based on current location
        const path = window.location.pathname;
        if (path.includes('/admin/')) {
            return `project-detail.php?id=${projectId}`;
        } else if (path.includes('/manager/')) {
            return `../admin/project-detail.php?id=${projectId}`;
        } else {
            return `../admin/project-detail.php?id=${projectId}`;
        }
    }

    function getTaskUrl(taskId) {
        const path = window.location.pathname;
        if (path.includes('/employee/')) {
            return `tasks.php#task-${taskId}`;
        } else if (path.includes('/admin/')) {
            return `index.php#task-${taskId}`;
        }
        return '#';
    }

    function getUserUrl(userId) {
        const path = window.location.pathname;
        if (path.includes('/admin/')) {
            return `user-profile.php?id=${userId}`;
        }
        return `../admin/user-profile.php?id=${userId}`;
    }

    function formatStatus(status) {
        return status.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    }

    function formatRole(role) {
        return role.charAt(0).toUpperCase() + role.slice(1);
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
})();
