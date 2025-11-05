<?php
require_once '../config/config.php';
require_once '../includes/functions.php';
if (!isLoggedIn() || !hasRole('employee')) redirect('../login.php');

$db = getDBConnection();
$currentUser = getCurrentUser();
$today = date('Y-m-d');

// Get or create today's plan
$stmt = $db->prepare("SELECT * FROM daily_plans WHERE user_id = ? AND plan_date = ?");
$stmt->execute([$currentUser['id'], $today]);
$plan = $stmt->fetch();

// Get tasks for today
$stmt = $db->prepare("
    SELECT t.*, p.project_name FROM tasks t
    JOIN projects p ON t.project_id = p.id
    WHERE t.assigned_to = ? AND t.status != 'completed'
    AND (t.due_date = ? OR t.due_date IS NULL OR t.status = 'in_progress')
    ORDER BY t.priority DESC
");
$stmt->execute([$currentUser['id'], $today]);
$tasks = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Planning - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/vien-v3.css">

    <!-- Synto Dashboard Template Design -->
    <link rel="stylesheet" href="../assets/css/synto-design.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="app-container">
        <?php include '../includes/v3-employee-sidebar.php'; ?>
        <div class="main-content">
            <?php include '../includes/v3-header.php'; ?>
            <div class="content-wrapper">
                <h1><i class="fas fa-sun"></i> Daily Planning - <?php echo date('l, F j, Y'); ?></h1>
                
                <div class="dashboard-card" style="margin: 20px 0;">
                    <div class="card-header"><h3>Morning Intention</h3></div>
                    <div class="card-body">
                        <textarea id="morningIntention" class="form-control" style="width:100%;min-height:80px;" placeholder="What's your main intention for today?"><?php echo e($plan['morning_intention'] ?? ''); ?></textarea>
                    </div>
                </div>

                <div class="dashboard-card" style="margin: 20px 0;">
                    <div class="card-header"><h3>Top 3 Priorities</h3></div>
                    <div class="card-body">
                        <textarea id="top3Priorities" class="form-control" style="width:100%;min-height:100px;" placeholder="1. &#10;2. &#10;3. "><?php echo e($plan['top_3_priorities'] ?? ''); ?></textarea>
                    </div>
                </div>

                <div class="dashboard-card" style="margin: 20px 0;">
                    <div class="card-header"><h3>Today's Tasks</h3></div>
                    <div class="card-body">
                        <?php foreach ($tasks as $task): ?>
                        <div style="padding:10px;margin:5px 0;background:var(--bg-secondary);border-radius:6px;">
                            <strong><?php echo e($task['task_name']); ?></strong> - <?php echo e($task['project_name']); ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div style="text-align:center;margin:30px 0;">
                    <button onclick="savePlan()" class="btn btn-primary" style="padding:15px 40px;font-size:18px;">
                        <i class="fas fa-save"></i> Save Daily Plan
                    </button>
                </div>
            </div>
        </div>
    </div>
    <script>
        function savePlan() {
            fetch('../api/productivity.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'save_daily_plan',
                    morning_intention: document.getElementById('morningIntention').value,
                    top_3_priorities: document.getElementById('top3Priorities').value
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) alert('Daily plan saved!');
                else alert('Error: ' + data.message);
            });
        }
    </script>
    <script src="../assets/js/theme.js"></script>

    <!-- Synto Dashboard Interactions -->
    <script src="../assets/js/synto-interactions.js"></script>
</body>
</html>
