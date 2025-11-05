<?php
/**
 * V3 Header Component - SYNTO EDITION
 * Synto Dashboard Template Design
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
        <i class="ri-search-line search-icon"></i>
        <input type="text" class="search-box" placeholder="Search projects, tasks, users..." id="globalSearch">
    </div>

    <!-- Header Actions -->
    <div class="header-actions" style="display: flex; align-items: center; gap: 12px;">
        <!-- Notifications -->
        <button class="icon-button" onclick="toggleNotifications()" title="Notifications">
            <i class="ri-notification-3-line"></i>
            <span class="badge"></span>
        </button>

        <!-- Messages -->
        <button class="icon-button" onclick="window.location.href='chat.php'" title="Messages">
            <i class="ri-message-3-line"></i>
        </button>

        <!-- Settings -->
        <button class="icon-button" onclick="window.location.href='email-config.php'" title="Settings">
            <i class="ri-settings-3-line"></i>
        </button>

        <!-- User Menu -->
        <div class="user-menu" onclick="toggleUserDropdown()">
            <img src="<?php echo htmlspecialchars($userAvatar); ?>"
                 alt="<?php echo htmlspecialchars($currentUser['full_name']); ?>"
                 class="user-avatar"
                 onerror="this.src='../assets/images/default-avatar.png'">
            <span class="user-name"><?php echo htmlspecialchars($currentUser['full_name']); ?></span>
            <i class="ri-arrow-down-s-line" style="font-size: 16px; margin-left: 4px; color: var(--synto-text-secondary);"></i>
        </div>
    </div>
</div>

<!-- PAGE BREADCRUMB - PERMANENT LOCATION IDENTIFIER -->
<div class="page-breadcrumb" style="background: var(--synto-bg); border-bottom: 1px solid var(--synto-border); padding: 12px 24px; font-size: 13px; display: flex; align-items: center; gap: 8px; color: var(--synto-text-secondary); position: sticky; top: 70px; z-index: 100;">
    <i class="ri-home-5-line" style="font-size: 16px; color: var(--synto-primary);"></i>
    <span id="breadcrumb-path" style="display: flex; align-items: center; gap: 8px;">
        <!-- Breadcrumb will be populated by JavaScript -->
    </span>
</div>

<!-- User Dropdown Menu (Hidden by default) -->
<div id="userDropdown" style="display: none; position: absolute; top: 70px; right: 30px; background: var(--synto-card-bg); border: 1px solid var(--synto-border); border-radius: var(--synto-radius-xl); box-shadow: var(--synto-shadow-xl); min-width: 220px; z-index: 1000;">
    <div style="padding: 16px; border-bottom: 1px solid var(--synto-border);">
        <div style="font-weight: 600; color: var(--synto-text-primary); margin-bottom: 4px; font-size: 14px;">
            <?php echo htmlspecialchars($currentUser['full_name']); ?>
        </div>
        <div style="font-size: 12px; color: var(--synto-text-secondary);">
            <?php echo htmlspecialchars($currentUser['email']); ?>
        </div>
    </div>
    <div style="padding: 8px 0;">
        <a href="profile.php" style="display: flex; align-items: center; gap: 12px; padding: 10px 16px; color: var(--synto-text-primary); text-decoration: none; transition: all 0.2s; font-size: 14px;">
            <i class="ri-user-line" style="font-size: 18px;"></i> My Profile
        </a>
        <a href="email-config.php" style="display: flex; align-items: center; gap: 12px; padding: 10px 16px; color: var(--synto-text-primary); text-decoration: none; transition: all 0.2s; font-size: 14px;">
            <i class="ri-settings-3-line" style="font-size: 18px;"></i> Settings
        </a>
        <a href="../logout.php" style="display: flex; align-items: center; gap: 12px; padding: 10px 16px; color: var(--synto-danger); text-decoration: none; transition: all 0.2s; border-top: 1px solid var(--synto-border); margin-top: 4px; font-size: 14px;">
            <i class="ri-logout-box-line" style="font-size: 18px;"></i> Logout
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
        this.style.background = '#F3F4F6';
        this.style.borderRadius = 'var(--synto-radius-md)';
    });
    link.addEventListener('mouseleave', function() {
        this.style.background = 'transparent';
    });
});
</script>

<style>
/* Additional header styles - Synto Edition */
#userDropdown a:hover {
    background: #F3F4F6;
    border-radius: var(--synto-radius-md);
}
</style>
