<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || (!hasRole('admin') && !hasRole('manager'))) {
    redirect('../login.php');
}

$db = getDBConnection();
$currentUser = getCurrentUser();

$successMessage = '';
$errorMessage = '';

// Handle Bulk Add Projects
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_add_projects'])) {
    try {
        $projectsData = $_POST['projects_data'];
        $lines = explode("\n", $projectsData);
        $added = 0;

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // Format: ProjectName | ClientName | Manager | Budget | StartDate | DueDate | Status
            $parts = array_map('trim', explode('|', $line));
            if (count($parts) >= 3) {
                $projectName = $parts[0];
                $clientName = $parts[1] ?? '';
                $managerUsername = $parts[2] ?? '';
                $budget = $parts[3] ?? 0;
                $startDate = $parts[4] ?? date('Y-m-d');
                $dueDate = $parts[5] ?? date('Y-m-d', strtotime('+30 days'));
                $status = $parts[6] ?? 'planning';

                // Find manager by username
                $managerId = null;
                if ($managerUsername) {
                    $stmt = $db->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
                    $stmt->execute([$managerUsername]);
                    $manager = $stmt->fetch();
                    if ($manager) {
                        $managerId = $manager['id'];
                    }
                }

                $stmt = $db->prepare("
                    INSERT INTO projects (project_name, client_name, assigned_manager, budget, start_date, due_date, status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$projectName, $clientName, $managerId, $budget, $startDate, $dueDate, $status]);
                $added++;
            }
        }

        $successMessage = "Successfully added {$added} projects!";
    } catch (Exception $e) {
        $errorMessage = "Error adding projects: " . $e->getMessage();
    }
}

// Handle Bulk Remove Projects
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_remove_projects'])) {
    try {
        $projectIds = $_POST['project_ids'] ?? [];
        if (!empty($projectIds)) {
            $placeholders = implode(',', array_fill(0, count($projectIds), '?'));

            // Delete related tasks first
            $stmt = $db->prepare("DELETE FROM tasks WHERE project_id IN ($placeholders)");
            $stmt->execute($projectIds);

            // Delete projects
            $stmt = $db->prepare("DELETE FROM projects WHERE id IN ($placeholders)");
            $stmt->execute($projectIds);

            $successMessage = "Successfully removed " . count($projectIds) . " projects!";
        }
    } catch (Exception $e) {
        $errorMessage = "Error removing projects: " . $e->getMessage();
    }
}

// Handle Bulk Add Team Members
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_add_users'])) {
    try {
        $usersData = $_POST['users_data'];
        $lines = explode("\n", $usersData);
        $added = 0;

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // Format: Username | Email | FullName | Role | JobTitle | HourlyRate
            $parts = array_map('trim', explode('|', $line));
            if (count($parts) >= 4) {
                $username = $parts[0];
                $email = $parts[1];
                $fullName = $parts[2];
                $role = $parts[3];
                $jobTitle = $parts[4] ?? '';
                $hourlyRate = $parts[5] ?? 50;

                // Check if username exists
                $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$username, $email]);
                if ($stmt->fetch()) {
                    continue; // Skip existing users
                }

                $password = password_hash('welcome123', PASSWORD_DEFAULT);

                $stmt = $db->prepare("
                    INSERT INTO users (username, email, password, full_name, role, job_title, hourly_rate, is_active, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())
                ");
                $stmt->execute([$username, $email, $password, $fullName, $role, $jobTitle, $hourlyRate]);
                $added++;
            }
        }

        $successMessage = "Successfully added {$added} team members! Default password: welcome123";
    } catch (Exception $e) {
        $errorMessage = "Error adding team members: " . $e->getMessage();
    }
}

// Get all projects for removal
$allProjects = $db->query("
    SELECT p.*, u.full_name as manager_name,
           (SELECT COUNT(*) FROM tasks WHERE project_id = p.id) as task_count
    FROM projects p
    LEFT JOIN users u ON p.assigned_manager = u.id
    ORDER BY p.created_at DESC
")->fetchAll();

// Get all users
$allUsers = $db->query("
    SELECT id, username, email, full_name, role, job_title
    FROM users
    WHERE role IN ('employee', 'manager')
    ORDER BY full_name
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Operations - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/ultra-premium.css">
    <style>
    .operations-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
        gap: var(--space-8);
        margin-bottom: var(--space-10);
    }

    .operation-card {
        background: var(--bg-secondary);
        border: 1px solid var(--border-light);
        border-radius: var(--radius-xl);
        padding: var(--space-8);
    }

    .operation-card h3 {
        font-size: var(--font-xl);
        font-weight: 700;
        margin-bottom: var(--space-4);
        color: var(--text-primary);
    }

    .operation-card p {
        font-size: var(--font-sm);
        color: var(--text-secondary);
        margin-bottom: var(--space-6);
        line-height: 1.6;
    }

    .bulk-textarea {
        width: 100%;
        min-height: 200px;
        padding: var(--space-4);
        border: 2px solid var(--border);
        border-radius: var(--radius-md);
        font-family: 'Monaco', 'Courier New', monospace;
        font-size: var(--font-xs);
        background: var(--bg-tertiary);
        color: var(--text-primary);
        resize: vertical;
    }

    .bulk-textarea:focus {
        outline: none;
        border-color: var(--primary);
    }

    .format-example {
        background: var(--bg-tertiary);
        padding: var(--space-3);
        border-radius: var(--radius-sm);
        font-family: 'Monaco', 'Courier New', monospace;
        font-size: var(--font-xs);
        margin-bottom: var(--space-4);
        color: var(--text-secondary);
    }

    .project-checkbox-list {
        max-height: 400px;
        overflow-y: auto;
        border: 1px solid var(--border);
        border-radius: var(--radius-md);
        padding: var(--space-4);
    }

    .project-checkbox-item {
        padding: var(--space-3);
        border-bottom: 1px solid var(--border-light);
        display: flex;
        align-items: center;
        gap: var(--space-3);
    }

    .project-checkbox-item:last-child {
        border-bottom: none;
    }

    .project-checkbox-item input {
        width: 18px;
        height: 18px;
        cursor: pointer;
    }

    .alert-success {
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(5, 150, 105, 0.1) 100%);
        border: 1px solid rgba(16, 185, 129, 0.3);
        color: #059669;
        padding: var(--space-4);
        border-radius: var(--radius-lg);
        margin-bottom: var(--space-6);
        font-weight: 600;
    }

    .alert-error {
        background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(220, 38, 38, 0.1) 100%);
        border: 1px solid rgba(239, 68, 68, 0.3);
        color: #dc2626;
        padding: var(--space-4);
        border-radius: var(--radius-lg);
        margin-bottom: var(--space-6);
        font-weight: 600;
    }

    .select-all-btn {
        margin-bottom: var(--space-4);
    }
    </style>
    <?php include '../includes/quick-actions-assets.php'; ?>
</head>
<body>
    <div class="dashboard">
        <?php include '../includes/admin-sidebar.php'; ?>

        <main class="main-content">
            <div class="topbar">
                <h1>⚡ Bulk Operations</h1>
                <div class="topbar-actions">
                    <?php include '../includes/notifications-dropdown.php'; ?>
                </div>
            </div>

            <div class="content">
                <?php if ($successMessage): ?>
                <div class="alert-success">✓ <?php echo $successMessage; ?></div>
                <?php endif; ?>

                <?php if ($errorMessage): ?>
                <div class="alert-error">✗ <?php echo $errorMessage; ?></div>
                <?php endif; ?>

                <div class="operations-grid">
                    <!-- Bulk Add Projects -->
                    <div class="operation-card">
                        <h3>📁 Bulk Add Projects</h3>
                        <p>Add multiple projects at once. One project per line.</p>

                        <div class="format-example">
                            Format: ProjectName | ClientName | ManagerUsername | Budget | StartDate | DueDate | Status<br>
                            Example: Website Redesign | Acme Corp | john_manager | 50000 | 2024-01-01 | 2024-03-31 | planning
                        </div>

                        <form method="POST">
                            <textarea name="projects_data" class="bulk-textarea" placeholder="Enter projects (one per line)..."></textarea>
                            <button type="submit" name="bulk_add_projects" class="btn btn-primary" style="margin-top: var(--space-4); width: 100%;">
                                Add Projects
                            </button>
                        </form>
                    </div>

                    <!-- Bulk Add Team Members -->
                    <div class="operation-card">
                        <h3>👥 Bulk Add Team Members</h3>
                        <p>Add multiple team members at once. One user per line.</p>

                        <div class="format-example">
                            Format: Username | Email | FullName | Role | JobTitle | HourlyRate<br>
                            Example: john_doe | john@example.com | John Doe | employee | Designer | 50
                        </div>

                        <form method="POST">
                            <textarea name="users_data" class="bulk-textarea" placeholder="Enter team members (one per line)..."></textarea>
                            <button type="submit" name="bulk_add_users" class="btn btn-primary" style="margin-top: var(--space-4); width: 100%;">
                                Add Team Members
                            </button>
                        </form>
                        <p style="margin-top: var(--space-2); font-size: var(--font-xs); color: var(--text-tertiary);">
                            Default password: welcome123
                        </p>
                    </div>
                </div>

                <!-- Bulk Remove Projects -->
                <div class="card">
                    <div class="card-header">
                        <h3>🗑️ Bulk Remove Projects</h3>
                    </div>
                    <div class="card-body">
                        <p style="color: var(--text-secondary); margin-bottom: var(--space-6);">
                            ⚠️ Warning: This will permanently delete selected projects and all their tasks!
                        </p>

                        <form method="POST" onsubmit="return confirm('Are you sure you want to delete the selected projects? This cannot be undone!');">
                            <button type="button" class="btn btn-secondary select-all-btn" onclick="toggleSelectAll()">
                                Select All / Deselect All
                            </button>

                            <div class="project-checkbox-list">
                                <?php foreach ($allProjects as $project): ?>
                                <div class="project-checkbox-item">
                                    <input type="checkbox" name="project_ids[]" value="<?php echo $project['id']; ?>" id="project_<?php echo $project['id']; ?>">
                                    <label for="project_<?php echo $project['id']; ?>" style="flex: 1; cursor: pointer;">
                                        <strong><?php echo e($project['project_name']); ?></strong><br>
                                        <small style="color: var(--text-tertiary);">
                                            <?php echo e($project['client_name']); ?> •
                                            <?php echo $project['task_count']; ?> tasks •
                                            <?php echo e($project['manager_name'] ?? 'No manager'); ?>
                                        </small>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <button type="submit" name="bulk_remove_projects" class="btn btn-danger" style="margin-top: var(--space-4);">
                                Delete Selected Projects
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/theme.js"></script>
    <script>
    let allSelected = false;

    function toggleSelectAll() {
        const checkboxes = document.querySelectorAll('input[name="project_ids[]"]');
        allSelected = !allSelected;
        checkboxes.forEach(cb => cb.checked = allSelected);
    }
    </script>
</body>
</html>
