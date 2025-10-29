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
            <span class="badge"></span>
        </button>

        <!-- Messages -->
        <button class="icon-button" onclick="window.location.href='messages.php'" title="Messages">
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

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    const dropdown = document.getElementById('userDropdown');
    const userMenu = document.querySelector('.user-menu');

    if (!userMenu.contains(event.target) && !dropdown.contains(event.target)) {
        dropdown.style.display = 'none';
    }
});

// Global search functionality
document.getElementById('globalSearch')?.addEventListener('input', function(e) {
    const searchTerm = e.target.value.toLowerCase();
    // Implement search logic here
    console.log('Searching for:', searchTerm);
});

// Notifications toggle
function toggleNotifications() {
    // Implement notifications panel
    alert('Notifications feature - coming soon!');
}

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
