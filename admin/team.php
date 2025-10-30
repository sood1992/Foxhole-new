<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !hasRole('admin')) {
    redirect('../login.php');
}

$db = getDBConnection();
$currentUser = getCurrentUser();

// Get role filter from query parameter
$roleFilter = isset($_GET['role']) ? $_GET['role'] : 'all';

// Build query based on filter
$query = "
    SELECT
        u.*,
        COUNT(DISTINCT t.project_id) as projects,
        COUNT(DISTINCT t.id) as tasks,
        SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
        (SELECT SUM(duration_minutes) FROM time_logs WHERE user_id = u.id) as total_minutes
    FROM users u
    LEFT JOIN tasks t ON u.id = t.assigned_to
    WHERE u.role IN ('manager', 'employee')
";

// Add role filter if specified
if ($roleFilter === 'manager' || $roleFilter === 'employee') {
    $query .= " AND u.role = " . $db->quote($roleFilter);
}

$query .= " GROUP BY u.id ORDER BY u.full_name";

$teamMembers = $db->query($query)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Management - <?php echo SITE_NAME; ?> V3</title>
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
                    <h1 style="margin-bottom: 8px;">Team Management</h1>
                    <p style="color: var(--text-secondary); font-size: 14px; margin: 0;">
                        View and manage all team members and their performance
                    </p>
                </div>

                <!-- Tab Navigation -->
                <div class="tab-nav" style="margin-bottom: 30px;">
                    <a href="team.php" class="tab-link <?php echo ($roleFilter === 'all') ? 'active' : ''; ?>">
                        <i class="fas fa-users"></i> All Team Members
                    </a>
                    <a href="team.php?role=manager" class="tab-link <?php echo ($roleFilter === 'manager') ? 'active' : ''; ?>">
                        <i class="fas fa-user-tie"></i> Managers
                    </a>
                    <a href="team.php?role=employee" class="tab-link <?php echo ($roleFilter === 'employee') ? 'active' : ''; ?>">
                        <i class="fas fa-user"></i> Employees
                    </a>
                    <a href="users.php?action=add" class="tab-link">
                        <i class="fas fa-user-plus"></i> Add User
                    </a>
                </div>

                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success" style="margin-bottom: 20px;">
                        <i class="fas fa-check-circle"></i>
                        <?php
                        echo e($_SESSION['success_message']);
                        unset($_SESSION['success_message']);
                        ?>
                    </div>
                <?php endif; ?>

                <!-- Team Statistics -->
                <div class="row" style="margin-bottom: 30px;">
                    <div class="col-lg-3 col-md-6">
                        <div class="dashboard-card">
                            <div class="card-icon primary">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="card-value"><?php echo count($teamMembers); ?></div>
                            <div class="card-label">Total Members</div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="dashboard-card">
                            <div class="card-icon success">
                                <i class="fas fa-user-check"></i>
                            </div>
                            <div class="card-value">
                                <?php
                                $activeCount = array_filter($teamMembers, function($m) {
                                    return $m['is_active'];
                                });
                                echo count($activeCount);
                                ?>
                            </div>
                            <div class="card-label">Active Members</div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="dashboard-card">
                            <div class="card-icon info">
                                <i class="fas fa-tasks"></i>
                            </div>
                            <div class="card-value">
                                <?php
                                $totalTasks = array_sum(array_column($teamMembers, 'tasks'));
                                echo $totalTasks;
                                ?>
                            </div>
                            <div class="card-label">Total Tasks</div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="dashboard-card">
                            <div class="card-icon warning">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="card-value">
                                <?php
                                $totalMinutes = array_sum(array_column($teamMembers, 'total_minutes'));
                                echo formatHours($totalMinutes);
                                ?>
                            </div>
                            <div class="card-label">Total Hours</div>
                        </div>
                    </div>
                </div>

                <!-- Team Members Table -->
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3 style="margin: 0;">
                                <?php
                                if ($roleFilter === 'manager') {
                                    echo 'Managers';
                                } elseif ($roleFilter === 'employee') {
                                    echo 'Employees';
                                } else {
                                    echo 'Team Overview';
                                }
                                ?> (<?php echo count($teamMembers); ?>)
                            </h3>
                            <p style="font-size: 13px; color: var(--text-secondary); margin: 4px 0 0 0;">
                                <?php
                                if ($roleFilter === 'manager') {
                                    echo 'All managers with their performance metrics';
                                } elseif ($roleFilter === 'employee') {
                                    echo 'All employees with their performance metrics';
                                } else {
                                    echo 'All team members with their performance metrics';
                                }
                                ?>
                            </p>
                        </div>
                        <div style="display: flex; gap: 12px; align-items: center;">
                            <div style="position: relative;">
                                <i class="fas fa-search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-tertiary);"></i>
                                <input type="text" id="searchInput" placeholder="Search team members..."
                                       class="form-control" style="padding-left: 36px; width: 250px;">
                            </div>
                            <button id="bulkDeleteBtn" class="btn btn-danger btn-sm" style="display: none;">
                                <i class="fas fa-trash"></i> Delete Selected (<span id="selectedCount">0</span>)
                            </button>
                            <a href="users.php?action=add" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus"></i> Add Member
                            </a>
                        </div>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <div class="data-table-container" style="border: none; box-shadow: none;">
                            <table class="data-table" id="teamTable">
                                <thead>
                                    <tr>
                                        <th style="width: 40px;">
                                            <input type="checkbox" id="selectAll" style="cursor: pointer;">
                                        </th>
                                        <th class="sortable">Name</th>
                                        <th class="sortable">Email</th>
                                        <th>Role</th>
                                        <th>Job Title</th>
                                        <th class="sortable">Projects</th>
                                        <th class="sortable">Tasks</th>
                                        <th class="sortable">Completed</th>
                                        <th class="sortable">Total Hours</th>
                                        <th>Status</th>
                                        <th class="sortable">Last Login</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($teamMembers as $member): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" class="user-checkbox" value="<?php echo $member['id']; ?>" style="cursor: pointer;">
                                        </td>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 12px;">
                                                <div style="width: 36px; height: 36px; border-radius: 50%;
                                                            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                                                            display: flex; align-items: center; justify-content: center;
                                                            color: white; font-weight: 600; font-size: 14px;">
                                                    <?php echo strtoupper(substr($member['full_name'], 0, 1)); ?>
                                                </div>
                                                <strong style="color: var(--heading-color);"><?php echo e($member['full_name']); ?></strong>
                                            </div>
                                        </td>
                                        <td><?php echo e($member['email']); ?></td>
                                        <td>
                                            <span class="badge <?php echo $member['role'] === 'manager' ? 'badge-warning' : 'badge-primary'; ?>">
                                                <?php echo ucfirst($member['role']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo e($member['job_title'] ?? 'N/A'); ?></td>
                                        <td><strong><?php echo $member['projects']; ?></strong></td>
                                        <td><strong><?php echo $member['tasks']; ?></strong></td>
                                        <td>
                                            <span class="badge badge-success">
                                                <?php echo $member['completed_tasks']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong style="color: var(--primary);">
                                                <?php echo formatHours($member['total_minutes'] ?? 0); ?>h
                                            </strong>
                                        </td>
                                        <td>
                                            <?php if ($member['is_active']): ?>
                                                <span class="badge badge-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge badge-danger">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            if ($member['last_login']) {
                                                echo '<span style="color: var(--text-secondary); font-size: 13px;">' . timeAgo($member['last_login']) . '</span>';
                                            } else {
                                                echo '<span style="color: var(--text-tertiary); font-size: 13px;">Never</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <div style="display: flex; gap: 6px;">
                                                <a href="users.php?action=edit&id=<?php echo $member['id']; ?>"
                                                   class="btn btn-outline btn-sm btn-icon" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="reports.php?employee=<?php echo $member['id']; ?>&type=monthly"
                                                   class="btn btn-outline btn-sm btn-icon" title="Reports">
                                                    <i class="fas fa-chart-bar"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Bulk Delete Confirmation Modal -->
    <div id="bulkDeleteModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 10000; align-items: center; justify-content: center;">
        <div class="card" style="max-width: 500px; margin: 20px;">
            <div class="card-header">
                <h3 style="margin: 0; color: var(--danger);"><i class="fas fa-exclamation-triangle"></i> Confirm Bulk Delete</h3>
            </div>
            <div class="card-body">
                <p>Are you sure you want to delete <strong id="deleteCount">0</strong> selected user(s)?</p>
                <p style="color: var(--danger); font-size: 13px; margin-top: 10px;">
                    <i class="fas fa-info-circle"></i> Warning: This action cannot be undone. All associated data will be permanently deleted.
                </p>
            </div>
            <div class="card-footer" style="display: flex; gap: 10px; justify-content: flex-end;">
                <button onclick="closeBulkDeleteModal()" class="btn btn-outline">Cancel</button>
                <button onclick="confirmBulkDelete()" class="btn btn-danger">
                    <i class="fas fa-trash"></i> Delete Users
                </button>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Search functionality
        const searchInput = document.getElementById('searchInput');
        searchInput.addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            const rows = document.querySelectorAll('#teamTable tbody tr');

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        });

        // Bulk delete functionality
        const selectAllCheckbox = document.getElementById('selectAll');
        const userCheckboxes = document.querySelectorAll('.user-checkbox');
        const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
        const selectedCountSpan = document.getElementById('selectedCount');

        // Select/Deselect All
        selectAllCheckbox.addEventListener('change', function() {
            userCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateBulkDeleteButton();
        });

        // Update bulk delete button visibility and count
        userCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                updateBulkDeleteButton();

                // Update select all checkbox state
                const allChecked = Array.from(userCheckboxes).every(cb => cb.checked);
                const noneChecked = Array.from(userCheckboxes).every(cb => !cb.checked);
                selectAllCheckbox.checked = allChecked;
                selectAllCheckbox.indeterminate = !allChecked && !noneChecked;
            });
        });

        function updateBulkDeleteButton() {
            const checkedBoxes = document.querySelectorAll('.user-checkbox:checked');
            const count = checkedBoxes.length;

            if (count > 0) {
                bulkDeleteBtn.style.display = 'inline-flex';
                selectedCountSpan.textContent = count;
            } else {
                bulkDeleteBtn.style.display = 'none';
            }
        }

        // Bulk delete button click
        bulkDeleteBtn.addEventListener('click', function() {
            const checkedBoxes = document.querySelectorAll('.user-checkbox:checked');
            document.getElementById('deleteCount').textContent = checkedBoxes.length;
            document.getElementById('bulkDeleteModal').style.display = 'flex';
        });

        // Auto-hide alerts after 5 seconds
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            }, 5000);
        });
    });

    function closeBulkDeleteModal() {
        document.getElementById('bulkDeleteModal').style.display = 'none';
    }

    function confirmBulkDelete() {
        const checkedBoxes = document.querySelectorAll('.user-checkbox:checked');
        const userIds = Array.from(checkedBoxes).map(cb => cb.value);

        if (userIds.length === 0) {
            closeBulkDeleteModal();
            return;
        }

        // Show loading state
        const deleteBtn = event.target.closest('button');
        deleteBtn.disabled = true;
        deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';

        // Send delete request
        fetch('../api/bulk-delete-users.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ user_ids: userIds })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Reload page to show updated list
                window.location.href = 'team.php?deleted=' + data.deleted_count;
            } else {
                alert('Error: ' + (data.message || 'Failed to delete users'));
                deleteBtn.disabled = false;
                deleteBtn.innerHTML = '<i class="fas fa-trash"></i> Delete Users';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while deleting users');
            deleteBtn.disabled = false;
            deleteBtn.innerHTML = '<i class="fas fa-trash"></i> Delete Users';
        });
    }
    </script>
</body>
</html>
