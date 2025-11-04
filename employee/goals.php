<?php
require_once '../config/config.php';
require_once '../includes/functions.php';
if (!isLoggedIn() || !hasRole('employee')) redirect('../login.php');

$db = getDBConnection();
$currentUser = getCurrentUser();

// Get active goals
$stmt = $db->prepare("SELECT * FROM goals WHERE user_id = ? ORDER BY target_date ASC");
$stmt->execute([$currentUser['id']]);
$goals = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Goals - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/vien-v3.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="app-container">
        <?php include '../includes/v3-employee-sidebar.php'; ?>
        <div class="main-content">
            <?php include '../includes/v3-header.php'; ?>
            <div class="content-wrapper">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                    <h1><i class="fas fa-bullseye"></i> My Goals</h1>
                    <button onclick="showAddGoalModal()" class="btn btn-primary">
                        <i class="fas fa-plus"></i> New Goal
                    </button>
                </div>

                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:20px;">
                    <?php foreach ($goals as $goal): 
                        $progress = $goal['target_value'] > 0 ? ($goal['current_value'] / $goal['target_value']) * 100 : 0;
                    ?>
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3><?php echo e($goal['goal_title']); ?></h3>
                            <span class="badge"><?php echo ucfirst($goal['goal_type']); ?></span>
                        </div>
                        <div class="card-body">
                            <p><?php echo e($goal['goal_description']); ?></p>
                            <div style="margin:15px 0;">
                                <div style="background:#e5e7eb;height:20px;border-radius:10px;overflow:hidden;">
                                    <div style="background:linear-gradient(90deg,#10b981,#059669);height:100%;width:<?php echo min($progress,100); ?>%;transition:width 0.3s;"></div>
                                </div>
                                <div style="margin-top:8px;font-size:14px;">
                                    <?php echo $goal['current_value']; ?> / <?php echo $goal['target_value']; ?> <?php echo $goal['unit']; ?>
                                </div>
                            </div>
                            <div style="font-size:13px;color:var(--text-secondary);">
                                Target: <?php echo date('M j, Y', strtotime($goal['target_date'])); ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($goals)): ?>
                    <div class="dashboard-card" style="grid-column:1/-1;text-align:center;padding:60px;">
                        <i class="fas fa-bullseye" style="font-size:64px;color:var(--text-secondary);opacity:0.3;"></i>
                        <h3>No goals yet</h3>
                        <p>Set your first goal to start tracking progress!</p>
                        <button onclick="showAddGoalModal()" class="btn btn-primary">Create Goal</button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div id="goalModal" class="modal">
        <div class="modal-content" style="max-width:500px;">
            <div class="modal-header">
                <h3>New Goal</h3>
                <button class="modal-close" onclick="closeGoalModal()">&times;</button>
            </div>
            <div class="modal-body">
                <label>Goal Title</label>
                <input type="text" id="goalTitle" class="form-control" placeholder="e.g., Complete 50 tasks">
                <label style="margin-top:15px;">Description</label>
                <textarea id="goalDesc" class="form-control" rows="3"></textarea>
                <label style="margin-top:15px;">Type</label>
                <select id="goalType" class="form-control">
                    <option value="weekly">Weekly</option>
                    <option value="monthly">Monthly</option>
                    <option value="quarterly">Quarterly</option>
                    <option value="yearly">Yearly</option>
                </select>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:15px;">
                    <div>
                        <label>Target Value</label>
                        <input type="number" id="goalTarget" class="form-control">
                    </div>
                    <div>
                        <label>Unit</label>
                        <input type="text" id="goalUnit" class="form-control" placeholder="tasks, hours, etc">
                    </div>
                </div>
                <label style="margin-top:15px;">Target Date</label>
                <input type="date" id="goalDate" class="form-control">
                <button onclick="saveGoal()" class="btn btn-primary" style="width:100%;margin-top:20px;">Save Goal</button>
            </div>
        </div>
    </div>

    <script>
        function showAddGoalModal() {
            document.getElementById('goalModal').classList.add('active');
        }
        function closeGoalModal() {
            document.getElementById('goalModal').classList.remove('active');
        }
        function saveGoal() {
            fetch('../api/productivity.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'create_goal',
                    goal_title: document.getElementById('goalTitle').value,
                    goal_description: document.getElementById('goalDesc').value,
                    goal_type: document.getElementById('goalType').value,
                    target_value: document.getElementById('goalTarget').value,
                    unit: document.getElementById('goalUnit').value,
                    target_date: document.getElementById('goalDate').value
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Goal created!');
                    location.reload();
                } else alert('Error: ' + data.message);
            });
        }
    </script>
    <script src="../assets/js/theme.js"></script>
</body>
</html>
