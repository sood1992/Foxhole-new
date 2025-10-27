<!-- Notifications Dropdown Component -->
<div class="notifications-container" style="position: relative;">
    <button id="notificationsBtn" class="btn btn-secondary btn-sm" style="position: relative;">
        🔔 <span id="notificationBadge" class="notification-badge" style="display: none;"></span>
    </button>

    <div id="notificationsDropdown" class="notifications-dropdown" style="display: none;">
        <div class="notifications-header">
            <h4>Notifications</h4>
            <button id="markAllReadBtn" class="btn btn-link btn-sm">Mark all read</button>
        </div>
        <div id="notificationsList" class="notifications-list">
            <p style="text-align: center; color: var(--text-secondary); padding: 20px;">
                Loading notifications...
            </p>
        </div>
    </div>
</div>

<style>
.notifications-container {
    display: inline-block;
}

.notification-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: var(--red);
    color: white;
    border-radius: 10px;
    padding: 2px 6px;
    font-size: 11px;
    font-weight: 600;
    min-width: 18px;
    text-align: center;
}

.notifications-dropdown {
    position: absolute;
    top: 100%;
    right: 0;
    margin-top: 8px;
    width: 380px;
    max-height: 500px;
    background: white;
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-lg);
    z-index: 1000;
    display: flex;
    flex-direction: column;
}

.notifications-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px;
    border-bottom: 1px solid var(--border);
}

.notifications-header h4 {
    font-size: 16px;
    font-weight: 600;
    margin: 0;
}

.notifications-list {
    overflow-y: auto;
    max-height: 450px;
}

.notification-item {
    padding: 12px 16px;
    border-bottom: 1px solid var(--border-light);
    cursor: pointer;
    transition: background 0.2s;
}

.notification-item:hover {
    background: var(--bg-tertiary);
}

.notification-item.unread {
    background: #eff6ff;
}

.notification-item.unread::before {
    content: '';
    position: absolute;
    left: 8px;
    top: 50%;
    transform: translateY(-50%);
    width: 6px;
    height: 6px;
    background: var(--primary);
    border-radius: 50%;
}

.notification-content {
    position: relative;
    padding-left: 36px;
}

.notification-icon {
    position: absolute;
    left: 0;
    font-size: 20px;
}

.notification-title {
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 4px;
}

.notification-message {
    font-size: 13px;
    color: var(--text-secondary);
    margin-bottom: 4px;
}

.notification-time {
    font-size: 11px;
    color: var(--text-tertiary);
}

.notification-actions {
    display: flex;
    gap: 8px;
    margin-top: 8px;
}

.btn-link {
    background: none;
    border: none;
    color: var(--primary);
    font-size: 13px;
    padding: 0;
    cursor: pointer;
}

.btn-link:hover {
    text-decoration: underline;
}
</style>

<script>
(function() {
    let isDropdownOpen = false;
    let notifications = [];

    // Toggle dropdown
    document.getElementById('notificationsBtn').addEventListener('click', function(e) {
        e.stopPropagation();
        const dropdown = document.getElementById('notificationsDropdown');
        isDropdownOpen = !isDropdownOpen;
        dropdown.style.display = isDropdownOpen ? 'flex' : 'none';

        if (isDropdownOpen) {
            loadNotifications();
        }
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.notifications-container')) {
            document.getElementById('notificationsDropdown').style.display = 'none';
            isDropdownOpen = false;
        }
    });

    // Load notifications
    function loadNotifications() {
        fetch('../api/notifications.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    notifications = data.notifications;
                    updateBadge(data.unread_count);
                    displayNotifications(data.notifications);
                }
            })
            .catch(error => console.error('Error loading notifications:', error));
    }

    // Update badge count
    function updateBadge(count) {
        const badge = document.getElementById('notificationBadge');
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : count;
            badge.style.display = 'block';
        } else {
            badge.style.display = 'none';
        }
    }

    // Display notifications
    function displayNotifications(notifications) {
        const container = document.getElementById('notificationsList');

        if (notifications.length === 0) {
            container.innerHTML = '<p style="text-align: center; color: var(--text-secondary); padding: 20px;">No notifications</p>';
            return;
        }

        let html = '';
        notifications.forEach(notif => {
            const icon = getNotificationIcon(notif.type);
            const time = formatTime(notif.created_at);
            const unreadClass = notif.is_read === '0' ? 'unread' : '';

            html += `
                <div class="notification-item ${unreadClass}" data-id="${notif.id}" onclick="handleNotificationClick(${notif.id}, '${notif.related_type}', ${notif.related_id})">
                    <div class="notification-content">
                        <div class="notification-icon">${icon}</div>
                        <div class="notification-title">${escapeHtml(notif.title)}</div>
                        <div class="notification-message">${escapeHtml(notif.message)}</div>
                        <div class="notification-time">${time}</div>
                    </div>
                </div>
            `;
        });

        container.innerHTML = html;
    }

    // Get notification icon
    function getNotificationIcon(type) {
        const icons = {
            'task': '✓',
            'project': '📁',
            'comment': '💬',
            'mention': '@',
            'deadline': '⏰',
            'system': 'ℹ️'
        };
        return icons[type] || 'ℹ️';
    }

    // Format time
    function formatTime(timestamp) {
        const date = new Date(timestamp);
        const now = new Date();
        const diff = now - date;
        const minutes = Math.floor(diff / 60000);
        const hours = Math.floor(minutes / 60);
        const days = Math.floor(hours / 24);

        if (minutes < 1) return 'Just now';
        if (minutes < 60) return `${minutes}m ago`;
        if (hours < 24) return `${hours}h ago`;
        if (days < 7) return `${days}d ago`;
        return date.toLocaleDateString();
    }

    // Escape HTML
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Handle notification click
    window.handleNotificationClick = function(notificationId, relatedType, relatedId) {
        // Mark as read
        fetch('../api/notifications.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'mark_read',
                notification_id: notificationId
            })
        })
        .then(() => {
            loadNotifications();

            // Navigate to related item
            if (relatedType && relatedId) {
                if (relatedType === 'task') {
                    window.location.href = '../employee/tasks.php';
                } else if (relatedType === 'project') {
                    window.location.href = '../admin/projects.php';
                }
            }
        });
    };

    // Mark all as read
    document.getElementById('markAllReadBtn').addEventListener('click', function(e) {
        e.stopPropagation();

        fetch('../api/notifications.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'mark_all_read' })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadNotifications();
            }
        });
    });

    // Auto-refresh notifications every 30 seconds
    setInterval(function() {
        if (!isDropdownOpen) {
            fetch('../api/notifications.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateBadge(data.unread_count);
                    }
                });
        }
    }, 30000);

    // Initial load
    loadNotifications();
})();
</script>
