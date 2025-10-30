<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !hasRole('admin')) {
    redirect('../login.php');
}

$db = getDBConnection();
$currentUser = getCurrentUser();

// Filters
$userFilter = $_GET['user'] ?? 'all';
$actionFilter = $_GET['action'] ?? 'all';
$entityFilter = $_GET['entity'] ?? 'all';
$dateFilter = $_GET['date'] ?? 'all'; // all, today, week, month

// Build query
$query = "
    SELECT
        al.*,
        u.full_name as user_name,
        u.role as user_role
    FROM activity_log al
    LEFT JOIN users u ON al.user_id = u.id
    WHERE 1=1
";

$params = [];

if ($userFilter !== 'all') {
    $query .= " AND al.user_id = ?";
    $params[] = $userFilter;
}

if ($actionFilter !== 'all') {
    $query .= " AND al.action_type = ?";
    $params[] = $actionFilter;
}

if ($entityFilter !== 'all') {
    $query .= " AND al.entity_type = ?";
    $params[] = $entityFilter;
}

// Date filters
switch ($dateFilter) {
    case 'today':
        $query .= " AND DATE(al.created_at) = CURDATE()";
        break;
    case 'week':
        $query .= " AND al.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        break;
    case 'month':
        $query .= " AND al.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        break;
}

$query .= " ORDER BY al.created_at DESC LIMIT 500";

$stmt = $db->prepare($query);
$stmt->execute($params);
$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get filter options
$users = $db->query("SELECT id, full_name, role FROM users WHERE role IN ('manager', 'employee') ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);

// Get unique action types from logs
$actionTypes = $db->query("SELECT DISTINCT action_type FROM activity_log ORDER BY action_type")->fetchAll(PDO::FETCH_COLUMN);

// Get unique entity types from logs
$entityTypes = $db->query("SELECT DISTINCT entity_type FROM activity_log ORDER BY entity_type")->fetchAll(PDO::FETCH_COLUMN);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Log - <?php echo SITE_NAME; ?> V3</title>
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
                    <h1 style="margin-bottom: 8px;"><i class="fas fa-history"></i> Activity Log</h1>
                    <p style="color: var(--text-secondary); font-size: 14px; margin: 0;">
                        Track all changes made by managers and employees
                    </p>
                </div>

                <!-- Filters -->
                <div class="card" style="margin-bottom: 30px;">
                    <div class="card-body">
                        <form method="GET" action="activity-log.php" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: end;">
                            <div>
                                <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text-primary);">
                                    User
                                </label>
                                <select name="user" class="form-control">
                                    <option value="all" <?php echo $userFilter === 'all' ? 'selected' : ''; ?>>All Users</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?php echo $user['id']; ?>" <?php echo $userFilter == $user['id'] ? 'selected' : ''; ?>>
                                            <?php echo e($user['full_name']); ?> (<?php echo ucfirst($user['role']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text-primary);">
                                    Action
                                </label>
                                <select name="action" class="form-control">
                                    <option value="all" <?php echo $actionFilter === 'all' ? 'selected' : ''; ?>>All Actions</option>
                                    <?php foreach ($actionTypes as $action): ?>
                                        <option value="<?php echo $action; ?>" <?php echo $actionFilter === $action ? 'selected' : ''; ?>>
                                            <?php echo ucfirst(str_replace('_', ' ', $action)); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text-primary);">
                                    Entity Type
                                </label>
                                <select name="entity" class="form-control">
                                    <option value="all" <?php echo $entityFilter === 'all' ? 'selected' : ''; ?>>All Types</option>
                                    <?php foreach ($entityTypes as $entity): ?>
                                        <option value="<?php echo $entity; ?>" <?php echo $entityFilter === $entity ? 'selected' : ''; ?>>
                                            <?php echo ucfirst(str_replace('_', ' ', $entity)); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text-primary);">
                                    Time Period
                                </label>
                                <select name="date" class="form-control">
                                    <option value="all" <?php echo $dateFilter === 'all' ? 'selected' : ''; ?>>All Time</option>
                                    <option value="today" <?php echo $dateFilter === 'today' ? 'selected' : ''; ?>>Today</option>
                                    <option value="week" <?php echo $dateFilter === 'week' ? 'selected' : ''; ?>>Last 7 Days</option>
                                    <option value="month" <?php echo $dateFilter === 'month' ? 'selected' : ''; ?>>Last 30 Days</option>
                                </select>
                            </div>

                            <div style="display: flex; gap: 10px;">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter"></i> Apply Filters
                                </button>
                                <a href="activity-log.php" class="btn btn-outline">
                                    <i class="fas fa-times"></i> Clear
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Activity Log Table -->
                <div class="card">
                    <div class="card-header">
                        <h3 style="margin: 0;">Recent Activity (<?php echo count($activities); ?>)</h3>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <?php if (empty($activities)): ?>
                            <div style="text-align: center; padding: 60px 20px;">
                                <i class="fas fa-history" style="font-size: 48px; color: var(--text-tertiary); margin-bottom: 16px;"></i>
                                <p style="color: var(--text-secondary); margin: 0;">No activity found for selected filters.</p>
                            </div>
                        <?php else: ?>
                            <div class="data-table-container" style="border: none; box-shadow: none;">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Time</th>
                                            <th>User</th>
                                            <th>Action</th>
                                            <th>Entity</th>
                                            <th>Description</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($activities as $activity): ?>
                                        <tr>
                                            <td style="white-space: nowrap;">
                                                <div style="font-weight: 600; color: var(--heading-color);">
                                                    <?php echo date('M d, Y', strtotime($activity['created_at'])); ?>
                                                </div>
                                                <div style="font-size: 12px; color: var(--text-secondary);">
                                                    <?php echo date('g:i A', strtotime($activity['created_at'])); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 10px;">
                                                    <div style="width: 32px; height: 32px; border-radius: 50%;
                                                                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                                                                display: flex; align-items: center; justify-content: center;
                                                                color: white; font-weight: 600; font-size: 12px;">
                                                        <?php echo strtoupper(substr($activity['user_name'] ?? 'U', 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <div style="font-weight: 600; color: var(--heading-color);">
                                                            <?php echo e($activity['user_name'] ?? 'Unknown'); ?>
                                                        </div>
                                                        <div style="font-size: 11px; color: var(--text-secondary);">
                                                            <?php echo ucfirst($activity['user_role'] ?? 'user'); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php
                                                $actionClass = 'badge-info';
                                                if (strpos($activity['action_type'], 'delete') !== false) $actionClass = 'badge-danger';
                                                elseif (strpos($activity['action_type'], 'create') !== false) $actionClass = 'badge-success';
                                                elseif (strpos($activity['action_type'], 'update') !== false || strpos($activity['action_type'], 'edit') !== false) $actionClass = 'badge-warning';
                                                ?>
                                                <span class="badge <?php echo $actionClass; ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $activity['action_type'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-primary">
                                                    <?php echo ucfirst(str_replace('_', ' ', $activity['entity_type'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div style="max-width: 500px;">
                                                    <?php echo e($activity['description']); ?>
                                                    <?php if ($activity['metadata']): ?>
                                                        <button class="btn btn-outline btn-sm" style="margin-left: 8px; padding: 2px 8px; font-size: 11px;"
                                                                onclick="showMetadata(<?php echo htmlspecialchars($activity['metadata'], ENT_QUOTES); ?>)">
                                                            <i class="fas fa-info-circle"></i> Details
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
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

    <!-- Metadata Modal -->
    <div id="metadataModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 10000; align-items: center; justify-content: center;">
        <div class="card" style="max-width: 600px; max-height: 80vh; margin: 20px; overflow: hidden; display: flex; flex-direction: column;">
            <div class="card-header">
                <h3 style="margin: 0;"><i class="fas fa-info-circle"></i> Activity Details</h3>
            </div>
            <div class="card-body" style="overflow-y: auto; flex: 1;">
                <pre id="metadataContent" style="background: var(--bg-secondary); padding: 15px; border-radius: 8px; overflow-x: auto; font-size: 13px; margin: 0;"></pre>
            </div>
            <div class="card-footer" style="display: flex; justify-content: flex-end;">
                <button onclick="closeMetadataModal()" class="btn btn-outline">Close</button>
            </div>
        </div>
    </div>

    <script>
    function showMetadata(metadata) {
        const modal = document.getElementById('metadataModal');
        const content = document.getElementById('metadataContent');
        content.textContent = JSON.stringify(metadata, null, 2);
        modal.style.display = 'flex';
    }

    function closeMetadataModal() {
        document.getElementById('metadataModal').style.display = 'none';
    }
    </script>
</body>
</html>
