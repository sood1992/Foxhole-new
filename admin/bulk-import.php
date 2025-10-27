<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !hasRole('admin')) {
    redirect('../login.php');
}

$db = getDBConnection();
$currentUser = getCurrentUser();

$message = '';
$error = '';

// Handle User Import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_users'])) {
    if (isset($_FILES['user_csv']) && $_FILES['user_csv']['error'] === 0) {
        $file = fopen($_FILES['user_csv']['tmp_name'], 'r');
        $header = fgetcsv($file); // Skip header

        $imported = 0;
        $errors = [];

        while (($row = fgetcsv($file)) !== false) {
            if (count($row) >= 5) {
                try {
                    $username = trim($row[0]);
                    $email = trim($row[1]);
                    $fullName = trim($row[2]);
                    $role = trim($row[3]);
                    $jobTitle = trim($row[4]);
                    $hourlyRate = isset($row[5]) ? floatval($row[5]) : 0;

                    $password = password_hash('welcome123', PASSWORD_DEFAULT);

                    $stmt = $db->prepare("
                        INSERT INTO users (username, email, password, full_name, role, job_title, hourly_rate, is_active)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 1)
                    ");
                    $stmt->execute([$username, $email, $password, $fullName, $role, $jobTitle, $hourlyRate]);
                    $imported++;
                } catch (PDOException $e) {
                    $errors[] = "Row error for $username: " . $e->getMessage();
                }
            }
        }
        fclose($file);

        $message = "Successfully imported $imported users!";
        if (!empty($errors)) {
            $error = implode('<br>', $errors);
        }
    }
}

// Handle Task Import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_tasks'])) {
    if (isset($_FILES['task_csv']) && $_FILES['task_csv']['error'] === 0) {
        $file = fopen($_FILES['task_csv']['tmp_name'], 'r');
        $header = fgetcsv($file); // Skip header

        $imported = 0;
        $errors = [];

        while (($row = fgetcsv($file)) !== false) {
            if (count($row) >= 6) {
                try {
                    $projectId = intval($row[0]);
                    $taskName = trim($row[1]);
                    $description = trim($row[2]);
                    $assignedTo = intval($row[3]);
                    $priority = trim($row[4]);
                    $estimatedHours = floatval($row[5]);
                    $dueDate = !empty($row[6]) ? $row[6] : null;
                    $budget = isset($row[7]) ? floatval($row[7]) : 0;

                    $stmt = $db->prepare("
                        INSERT INTO tasks (project_id, task_name, description, assigned_to, priority, estimated_hours, due_date, budget, created_by, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'todo')
                    ");
                    $stmt->execute([$projectId, $taskName, $description, $assignedTo, $priority, $estimatedHours, $dueDate, $budget, $currentUser['id']]);
                    $imported++;
                } catch (PDOException $e) {
                    $errors[] = "Row error for task '$taskName': " . $e->getMessage();
                }
            }
        }
        fclose($file);

        $message = "Successfully imported $imported tasks!";
        if (!empty($errors)) {
            $error = implode('<br>', $errors);
        }
    }
}

// Get all projects for reference
$projects = $db->query("SELECT id, project_name FROM projects ORDER BY project_name")->fetchAll();

// Get all users for reference
$users = $db->query("SELECT id, full_name, username FROM users WHERE is_active = 1 ORDER BY full_name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Import - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/theme.css">
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>Neofox</h2>
                <div class="user-role">Admin Panel</div>
            </div>

            <nav class="sidebar-nav">
                <a href="index.php">
                    <span class="icon">📊</span>
                    Dashboard
                </a>
                <a href="reports.php">
                    <span class="icon">📈</span>
                    Reports
                </a>
                <a href="projects.php">
                    <span class="icon">📁</span>
                    Projects
                </a>
                <a href="team.php">
                    <span class="icon">👥</span>
                    Team Management
                </a>
                <a href="bulk-import.php" class="active">
                    <span class="icon">📥</span>
                    Bulk Import
                </a>
                <a href="users.php">
                    <span class="icon">⚙️</span>
                    User Settings
                </a>
            </nav>

            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($currentUser['full_name'], 0, 1)); ?>
                    </div>
                    <div class="user-details">
                        <h4><?php echo e($currentUser['full_name']); ?></h4>
                        <p><?php echo e($currentUser['job_title'] ?? 'Administrator'); ?></p>
                    </div>
                </div>
                <a href="../logout.php" class="btn btn-secondary btn-block btn-sm">Logout</a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="topbar">
                <h1>Bulk Import</h1>
            </div>

            <div class="content">
                <?php if ($message): ?>
                    <div class="alert alert-success"><?php echo $message; ?></div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo $error; ?></div>
                <?php endif; ?>

                <!-- Import Users -->
                <div class="card">
                    <div class="card-header">
                        <h3>📥 Bulk Import Users</h3>
                    </div>
                    <div class="card-body">
                        <p style="margin-bottom: 20px;">Upload a CSV file to import multiple users at once. Default password: <strong>welcome123</strong></p>

                        <div style="background: var(--bg-tertiary); padding: 20px; border-radius: var(--radius-md); margin-bottom: 20px;">
                            <strong>CSV Format:</strong>
                            <pre style="margin-top: 10px; background: white; padding: 15px; border-radius: var(--radius-sm);">username,email,full_name,role,job_title,hourly_rate
john_doe,john@example.com,John Doe,employee,Designer,50
jane_smith,jane@example.com,Jane Smith,manager,Project Manager,75</pre>
                            <p style="margin-top: 10px; font-size: 13px;">
                                <strong>Roles:</strong> admin, manager, employee<br>
                                <strong>Hourly Rate:</strong> Optional, defaults to 0
                            </p>
                        </div>

                        <form method="POST" enctype="multipart/form-data">
                            <div class="form-group">
                                <label>Select CSV File</label>
                                <input type="file" name="user_csv" accept=".csv" required>
                            </div>
                            <button type="submit" name="import_users" class="btn btn-primary">Import Users</button>
                            <a href="../templates/users-template.csv" class="btn btn-secondary" download>Download Template</a>
                        </form>
                    </div>
                </div>

                <!-- Import Tasks -->
                <div class="card">
                    <div class="card-header">
                        <h3>📥 Bulk Import Tasks</h3>
                    </div>
                    <div class="card-body">
                        <p style="margin-bottom: 20px;">Upload a CSV file to import multiple tasks at once.</p>

                        <div style="background: var(--bg-tertiary); padding: 20px; border-radius: var(--radius-md); margin-bottom: 20px;">
                            <strong>CSV Format:</strong>
                            <pre style="margin-top: 10px; background: white; padding: 15px; border-radius: var(--radius-sm);">project_id,task_name,description,assigned_to,priority,estimated_hours,due_date,budget
1,Design Homepage,Create new homepage design,3,high,20,2024-12-31,500
1,Setup Database,Initialize database structure,2,medium,10,2024-12-15,0</pre>
                            <p style="margin-top: 10px; font-size: 13px;">
                                <strong>Priority:</strong> low, medium, high, urgent<br>
                                <strong>Date Format:</strong> YYYY-MM-DD
                            </p>
                        </div>

                        <!-- Reference Tables -->
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                            <div>
                                <strong>Available Projects:</strong>
                                <div style="max-height: 200px; overflow-y: auto; background: white; border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 10px; margin-top: 8px;">
                                    <?php foreach ($projects as $project): ?>
                                        <div style="padding: 4px 0; font-size: 13px;">
                                            ID: <strong><?php echo $project['id']; ?></strong> - <?php echo e($project['project_name']); ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div>
                                <strong>Available Users:</strong>
                                <div style="max-height: 200px; overflow-y: auto; background: white; border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 10px; margin-top: 8px;">
                                    <?php foreach ($users as $user): ?>
                                        <div style="padding: 4px 0; font-size: 13px;">
                                            ID: <strong><?php echo $user['id']; ?></strong> - <?php echo e($user['full_name']); ?> (<?php echo e($user['username']); ?>)
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <form method="POST" enctype="multipart/form-data">
                            <div class="form-group">
                                <label>Select CSV File</label>
                                <input type="file" name="task_csv" accept=".csv" required>
                            </div>
                            <button type="submit" name="import_tasks" class="btn btn-primary">Import Tasks</button>
                            <a href="../templates/tasks-template.csv" class="btn btn-secondary" download>Download Template</a>
                        </form>
                    </div>
                </div>

                <!-- Quick Tips -->
                <div class="card">
                    <div class="card-header">
                        <h3>💡 Tips for Bulk Import</h3>
                    </div>
                    <div class="card-body">
                        <ul style="line-height: 2;">
                            <li>✓ Make sure your CSV file is UTF-8 encoded</li>
                            <li>✓ First row should contain column headers (they will be skipped)</li>
                            <li>✓ All users will receive default password: <strong>welcome123</strong></li>
                            <li>✓ Users should change their password after first login</li>
                            <li>✓ For tasks, use valid project IDs and user IDs from the reference tables above</li>
                            <li>✓ Invalid rows will be skipped and reported</li>
                            <li>✓ Download templates to see the exact format required</li>
                        </ul>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script src="../assets/js/theme.js"></script>
</body>
</html>
