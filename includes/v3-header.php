<?php
/**
 * V3 Header Component
 * Vien Admin Panel Design
 */

// Ensure user is logged in
if (!isset($currentUser)) {
    $currentUser = getCurrentUser();
}

// Get user avatar (use default if not set)
$userAvatar = !empty($currentUser['avatar']) ? $currentUser['avatar'] : '../assets/images/default-avatar.png';
?>

<div class="header">
    <!-- Search Box -->
    <div class="header-search">
        <i class="fas fa-search search-icon"></i>
        <input type="text" class="search-box" placeholder="Search projects, tasks, users..." id="globalSearch">
    </div>

    <!-- Header Actions -->
    <div class="header-actions">
        <!-- Notifications -->
        <button class="icon-button" onclick="toggleNotifications()" title="Notifications">
            <i class="fas fa-bell"></i>
            <span class="badge" id="notificationBadge" style="display: none;"></span>
        </button>

        <!-- Messages -->
        <button class="icon-button" onclick="window.location.href='chat.php'" title="Messages">
            <i class="fas fa-envelope"></i>
        </button>

        <!-- Settings -->
        <button class="icon-button" onclick="window.location.href='settings.php'" title="Settings">
            <i class="fas fa-cog"></i>
        </button>

        <!-- User Menu -->
        <div class="user-menu" onclick="toggleUserDropdown()">
            <img src="<?php echo htmlspecialchars($userAvatar); ?>"
                 alt="<?php echo htmlspecialchars($currentUser['full_name']); ?>"
                 class="user-avatar"
                 onerror="this.src='../assets/images/default-avatar.png'">
            <span class="user-name"><?php echo htmlspecialchars($currentUser['full_name']); ?></span>
            <i class="fas fa-chevron-down" style="font-size: 10px; margin-left: 5px; color: var(--text-secondary);"></i>
        </div>
    </div>
</div>

<!-- Notifications Dropdown (Hidden by default) -->
<div id="notificationsDropdown" style="display: none; position: absolute; top: 70px; right: 250px; background: var(--card-bg); border-radius: var(--radius-lg); box-shadow: 0 5px 25px rgba(0,0,0,0.15); width: 380px; max-height: 500px; overflow: hidden; z-index: 1000;">
    <div style="padding: 15px 20px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
        <div style="font-weight: 600; color: var(--heading-color);">
            <i class="fas fa-bell"></i> Notifications
        </div>
        <button onclick="markAllAsRead()" style="background: none; border: none; color: var(--primary); cursor: pointer; font-size: 12px; padding: 4px 8px;">
            <i class="fas fa-check-double"></i> Mark all read
        </button>
    </div>
    <div id="notificationsList" style="max-height: 400px; overflow-y: auto;">
        <div style="padding: 40px 20px; text-align: center; color: var(--text-secondary);">
            <i class="fas fa-spinner fa-spin" style="font-size: 24px; margin-bottom: 10px;"></i>
            <div>Loading notifications...</div>
        </div>
    </div>
</div>

<!-- User Dropdown Menu (Hidden by default) -->
<div id="userDropdown" style="display: none; position: absolute; top: 70px; right: 30px; background: var(--card-bg); border-radius: var(--radius-lg); box-shadow: 0 5px 25px rgba(0,0,0,0.15); min-width: 200px; z-index: 1000;">
    <div style="padding: 15px; border-bottom: 1px solid var(--border-color);">
        <div style="font-weight: 600; color: var(--heading-color); margin-bottom: 4px;">
            <?php echo htmlspecialchars($currentUser['full_name']); ?>
        </div>
        <div style="font-size: 12px; color: var(--text-secondary);">
            <?php echo htmlspecialchars($currentUser['email']); ?>
        </div>
    </div>
    <div style="padding: 8px 0;">
        <?php
        // Show role switcher if user has multiple roles
        $allRoles = $_SESSION['all_roles'] ?? [];
        $activeRole = getActiveRole();
        if (count($allRoles) > 1):
        ?>
        <div style="padding: 10px 15px; border-bottom: 1px solid var(--border-light); margin-bottom: 5px;">
            <div style="font-size: 11px; color: var(--text-secondary); text-transform: uppercase; font-weight: 600; margin-bottom: 8px;">
                Switch Role
            </div>
            <?php foreach ($allRoles as $role): ?>
                <?php
                $roleIcons = [
                    'admin' => 'fa-user-shield',
                    'manager' => 'fa-user-tie',
                    'employee' => 'fa-user'
                ];
                $roleColors = [
                    'admin' => '#667eea',
                    'manager' => '#17b06b',
                    'employee' => '#f8b739'
                ];
                $isActive = $role === $activeRole;
                ?>
                <a href="#" onclick="switchRole('<?php echo $role; ?>'); return false;"
                   style="display: block; padding: 8px 10px; color: var(--text-primary); text-decoration: none; transition: all 0.2s; border-radius: 6px; margin-bottom: 4px; <?php echo $isActive ? 'background: var(--light); font-weight: 600;' : ''; ?>">
                    <i class="fas <?php echo $roleIcons[$role]; ?>" style="width: 20px; color: <?php echo $roleColors[$role]; ?>;"></i>
                    <?php echo ucfirst($role); ?>
                    <?php if ($isActive): ?>
                        <i class="fas fa-check" style="float: right; color: var(--success); font-size: 12px;"></i>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <a href="profile.php" style="display: block; padding: 10px 15px; color: var(--text-primary); text-decoration: none; transition: all 0.2s;">
            <i class="fas fa-user" style="width: 20px;"></i> My Profile
        </a>
        <a href="settings.php" style="display: block; padding: 10px 15px; color: var(--text-primary); text-decoration: none; transition: all 0.2s;">
            <i class="fas fa-cog" style="width: 20px;"></i> Settings
        </a>
        <a href="../logout.php" style="display: block; padding: 10px 15px; color: var(--danger); text-decoration: none; transition: all 0.2s; border-top: 1px solid var(--border-light); margin-top: 5px;">
            <i class="fas fa-sign-out-alt" style="width: 20px;"></i> Logout
        </a>
    </div>
</div>

<script>
// User dropdown toggle
function toggleUserDropdown() {
    const dropdown = document.getElementById('userDropdown');
    dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
}

// Close dropdowns when clicking outside
document.addEventListener('click', function(event) {
    const userDropdown = document.getElementById('userDropdown');
    const userMenu = document.querySelector('.user-menu');
    const notificationsDropdown = document.getElementById('notificationsDropdown');
    const notificationButton = event.target.closest('.icon-button[onclick="toggleNotifications()"]');

    // Close user dropdown if clicking outside
    if (!userMenu.contains(event.target) && !userDropdown.contains(event.target)) {
        userDropdown.style.display = 'none';
    }

    // Close notifications dropdown if clicking outside
    if (!notificationButton && !notificationsDropdown.contains(event.target)) {
        notificationsDropdown.style.display = 'none';
    }
});

// Role switcher function
async function switchRole(newRole) {
    try {
        const response = await fetch('../api/switch-role.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ role: newRole })
        });

        const data = await response.json();

        if (data.success) {
            window.location.href = data.redirect_url;
        } else {
            alert(data.message || 'Failed to switch role');
        }
    } catch (error) {
        console.error('Role switch error:', error);
        alert('Failed to switch role. Please try again.');
    }
}

// Global search functionality
document.getElementById('globalSearch')?.addEventListener('input', function(e) {
    const searchTerm = e.target.value.toLowerCase();
    // Implement search logic here
    console.log('Searching for:', searchTerm);
});

// Notifications toggle
let notificationsLoaded = false;

function toggleNotifications() {
    const dropdown = document.getElementById('notificationsDropdown');
    const isVisible = dropdown.style.display === 'block';

    // Close user dropdown if open
    document.getElementById('userDropdown').style.display = 'none';

    if (isVisible) {
        dropdown.style.display = 'none';
    } else {
        dropdown.style.display = 'block';
        if (!notificationsLoaded) {
            loadNotifications();
            notificationsLoaded = true;
        }
    }
}

// Load notifications from API
async function loadNotifications() {
    try {
        const response = await fetch('../api/notifications.php?limit=10');
        const data = await response.json();

        if (data.success) {
            displayNotifications(data.notifications, data.unread_count);
            updateNotificationBadge(data.unread_count);
        } else {
            document.getElementById('notificationsList').innerHTML = `
                <div style="padding: 40px 20px; text-align: center; color: var(--text-secondary);">
                    <i class="fas fa-exclamation-circle" style="font-size: 24px; margin-bottom: 10px;"></i>
                    <div>Failed to load notifications</div>
                </div>
            `;
        }
    } catch (error) {
        console.error('Error loading notifications:', error);
        document.getElementById('notificationsList').innerHTML = `
            <div style="padding: 40px 20px; text-align: center; color: var(--text-secondary);">
                <i class="fas fa-exclamation-circle" style="font-size: 24px; margin-bottom: 10px;"></i>
                <div>Error loading notifications</div>
            </div>
        `;
    }
}

// Display notifications in the dropdown
function displayNotifications(notifications, unreadCount) {
    const container = document.getElementById('notificationsList');

    if (notifications.length === 0) {
        container.innerHTML = `
            <div style="padding: 40px 20px; text-align: center; color: var(--text-secondary);">
                <i class="fas fa-bell-slash" style="font-size: 32px; margin-bottom: 10px; opacity: 0.3;"></i>
                <div>No notifications yet</div>
            </div>
        `;
        return;
    }

    let html = '';
    notifications.forEach(notif => {
        const isUnread = notif.is_read == 0;
        const bgColor = isUnread ? 'rgba(59, 130, 246, 0.05)' : 'transparent';
        const timeAgo = formatTimeAgo(notif.created_at);

        html += `
            <div class="notification-item" onclick="markAsRead(${notif.id})"
                 style="padding: 12px 20px; border-bottom: 1px solid var(--border-light); cursor: pointer; background: ${bgColor}; transition: background 0.2s;"
                 onmouseover="this.style.background='var(--light)'"
                 onmouseout="this.style.background='${bgColor}'">
                <div style="display: flex; align-items: start; gap: 10px;">
                    ${isUnread ? '<div style="width: 8px; height: 8px; background: var(--primary); border-radius: 50%; margin-top: 6px;"></div>' : '<div style="width: 8px;"></div>'}
                    <div style="flex: 1; min-width: 0;">
                        <div style="font-weight: ${isUnread ? '600' : '500'}; color: var(--text-primary); margin-bottom: 4px; font-size: 13px;">
                            ${escapeHtml(notif.title)}
                        </div>
                        <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 4px; line-height: 1.4;">
                            ${escapeHtml(notif.message)}
                        </div>
                        <div style="font-size: 11px; color: var(--text-tertiary);">
                            <i class="fas fa-clock"></i> ${timeAgo}
                        </div>
                    </div>
                </div>
            </div>
        `;
    });

    container.innerHTML = html;
}

// Update notification badge
function updateNotificationBadge(count) {
    const badge = document.getElementById('notificationBadge');
    if (count > 0) {
        badge.textContent = count > 99 ? '99+' : count;
        badge.style.display = 'block';
    } else {
        badge.style.display = 'none';
    }
}

// Mark single notification as read
async function markAsRead(notificationId) {
    try {
        await fetch('../api/notifications.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'mark_read',
                notification_id: notificationId
            })
        });

        // Reload notifications
        notificationsLoaded = false;
        loadNotifications();
    } catch (error) {
        console.error('Error marking notification as read:', error);
    }
}

// Mark all notifications as read
async function markAllAsRead() {
    try {
        await fetch('../api/notifications.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'mark_all_read'
            })
        });

        // Reload notifications
        notificationsLoaded = false;
        loadNotifications();
    } catch (error) {
        console.error('Error marking all as read:', error);
    }
}

// Helper: Format time ago
function formatTimeAgo(timestamp) {
    const now = new Date();
    const notifTime = new Date(timestamp);
    const diffMs = now - notifTime;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);

    if (diffMins < 1) return 'Just now';
    if (diffMins < 60) return `${diffMins}m ago`;
    if (diffHours < 24) return `${diffHours}h ago`;
    if (diffDays < 7) return `${diffDays}d ago`;
    return notifTime.toLocaleDateString();
}

// Helper: Escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Load notification count on page load
document.addEventListener('DOMContentLoaded', function() {
    fetch('../api/notifications.php?limit=1')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                updateNotificationBadge(data.unread_count);
            }
        })
        .catch(err => console.error('Error loading notification count:', err));
});

// Hover styles for dropdown items
document.querySelectorAll('#userDropdown a').forEach(link => {
    link.addEventListener('mouseenter', function() {
        this.style.background = 'var(--light)';
    });
    link.addEventListener('mouseleave', function() {
        this.style.background = 'transparent';
    });
});
</script>

<style>
/* Additional header styles */
#userDropdown a:hover {
    background: var(--light);
}
</style>
