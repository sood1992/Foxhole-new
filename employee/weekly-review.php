<?php
require_once '../config/config.php';
require_once '../includes/functions.php';
if (!isLoggedIn() || !hasRole('employee')) redirect('../login.php');

$db = getDBConnection();
$currentUser = getCurrentUser();

// Get current week start (Monday)
$weekStart = date('Y-m-d', strtotime('monday this week'));

// Get existing review for this week
$stmt = $db->prepare("SELECT * FROM weekly_reviews WHERE user_id = ? AND week_start_date = ?");
$stmt->execute([$currentUser['id'], $weekStart]);
$review = $stmt->fetch();

// Get stats for the week
$stmt = $db->prepare("
    SELECT
        COUNT(*) as total_tasks,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
        SUM(CASE WHEN status = 'completed' AND due_date >= completed_at THEN 1 ELSE 0 END) as on_time_tasks
    FROM tasks
    WHERE assigned_to = ?
    AND (completed_at >= ? OR (status != 'completed' AND created_at >= ?))
");
$stmt->execute([$currentUser['id'], $weekStart, $weekStart]);
$weekStats = $stmt->fetch();

// Get total focus time
$stmt = $db->prepare("
    SELECT SUM(duration_minutes) / 60 as total_hours
    FROM time_logs
    WHERE user_id = ? AND DATE(start_time) >= ? AND end_time IS NOT NULL
");
$stmt->execute([$currentUser['id'], $weekStart]);
$focusTime = $stmt->fetch()['total_hours'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Weekly Review - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/vien-v3.css">

    <!-- Synto Dashboard Template Design -->
    <link rel="stylesheet" href="../assets/css/synto-design.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .review-section {
            margin-bottom: 30px;
        }
        .stat-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        .stat-value {
            font-size: 32px;
            font-weight: bold;
            margin: 10px 0;
        }
        .stat-label {
            font-size: 14px;
            opacity: 0.9;
        }
        .slider-container {
            margin: 15px 0;
        }
        .slider {
            width: 100%;
            height: 8px;
            border-radius: 4px;
            background: #e5e7eb;
            outline: none;
            -webkit-appearance: none;
        }
        .slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #667eea;
            cursor: pointer;
        }
        .slider::-moz-range-thumb {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #667eea;
            cursor: pointer;
        }
        .slider-value {
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include '../includes/v3-employee-sidebar.php'; ?>
        <div class="main-content">
            <?php include '../includes/v3-header.php'; ?>
            <div class="content-wrapper">
                <h1><i class="fas ri-calendar-line-week"></i> Weekly Review</h1>
                <p style="color:var(--text-secondary);margin-bottom:30px;">
                    Week of <?php echo date('M j', strtotime($weekStart)); ?> - <?php echo date('M j, Y', strtotime($weekStart . ' +6 days')); ?>
                </p>

                <!-- Week Stats -->
                <div class="stat-cards">
                    <div class="stat-card">
                        <div class="stat-label">Tasks Completed</div>
                        <div class="stat-value"><?php echo $weekStats['completed_tasks'] ?? 0; ?></div>
                        <div class="stat-label">of <?php echo $weekStats['total_tasks'] ?? 0; ?> total</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">On-Time Delivery</div>
                        <div class="stat-value"><?php echo $weekStats['on_time_tasks'] ?? 0; ?></div>
                        <div class="stat-label">delivered on time</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Focus Time</div>
                        <div class="stat-value"><?php echo number_format($focusTime, 1); ?></div>
                        <div class="stat-label">hours logged</div>
                    </div>
                </div>

                <!-- Review Form -->
                <div class="dashboard-card review-section">
                    <div class="card-header">
                        <h3><i class="fas ri-trophy-line"></i> Wins This Week</h3>
                    </div>
                    <div class="card-body">
                        <textarea id="wins" class="form-control" rows="4" placeholder="What went well? What are you proud of?"><?php echo e($review['wins'] ?? ''); ?></textarea>
                    </div>
                </div>

                <div class="dashboard-card review-section">
                    <div class="card-header">
                        <h3><i class="fas fa-exclamation-triangle"></i> Challenges</h3>
                    </div>
                    <div class="card-body">
                        <textarea id="challenges" class="form-control" rows="4" placeholder="What obstacles did you face? What slowed you down?"><?php echo e($review['challenges'] ?? ''); ?></textarea>
                    </div>
                </div>

                <div class="dashboard-card review-section">
                    <div class="card-header">
                        <h3><i class="fas fa-lightbulb"></i> Lessons Learned</h3>
                    </div>
                    <div class="card-body">
                        <textarea id="lessonsLearned" class="form-control" rows="4" placeholder="What did you learn? What would you do differently?"><?php echo e($review['lessons_learned'] ?? ''); ?></textarea>
                    </div>
                </div>

                <div class="dashboard-card review-section">
                    <div class="card-header">
                        <h3><i class="fas fa-forward"></i> Goals for Next Week</h3>
                    </div>
                    <div class="card-body">
                        <textarea id="nextWeekGoals" class="form-control" rows="4" placeholder="What do you want to accomplish next week?"><?php echo e($review['next_week_goals'] ?? ''); ?></textarea>
                    </div>
                </div>

                <!-- Rating Sliders -->
                <div class="dashboard-card review-section">
                    <div class="card-header">
                        <h3><i class="fas ri-star-line"></i> Self-Assessment</h3>
                    </div>
                    <div class="card-body">
                        <div class="slider-container">
                            <label>Energy Level (1-10)</label>
                            <input type="range" min="1" max="10" value="<?php echo $review['energy_level'] ?? 5; ?>" class="slider" id="energyLevel" oninput="updateSliderDisplay('energy', this.value)">
                            <div class="slider-value" id="energyDisplay"><?php echo $review['energy_level'] ?? 5; ?> / 10</div>
                        </div>

                        <div class="slider-container">
                            <label>Satisfaction Score (1-10)</label>
                            <input type="range" min="1" max="10" value="<?php echo $review['satisfaction_score'] ?? 5; ?>" class="slider" id="satisfactionScore" oninput="updateSliderDisplay('satisfaction', this.value)">
                            <div class="slider-value" id="satisfactionDisplay"><?php echo $review['satisfaction_score'] ?? 5; ?> / 10</div>
                        </div>
                    </div>
                </div>

                <div style="text-align:center;margin:30px 0;">
                    <button onclick="saveReview()" class="btn btn-primary" style="padding:15px 40px;font-size:18px;">
                        <i class="fas fa-save"></i> Save Weekly Review
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function updateSliderDisplay(type, value) {
            document.getElementById(type + 'Display').textContent = value + ' / 10';
        }

        function saveReview() {
            const weekStart = '<?php echo $weekStart; ?>';

            fetch('../api/productivity.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'save_weekly_review',
                    week_start_date: weekStart,
                    wins: document.getElementById('wins').value,
                    challenges: document.getElementById('challenges').value,
                    lessons_learned: document.getElementById('lessonsLearned').value,
                    next_week_goals: document.getElementById('nextWeekGoals').value,
                    energy_level: document.getElementById('energyLevel').value,
                    satisfaction_score: document.getElementById('satisfactionScore').value
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Weekly review saved! Great job reflecting on your week.');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            });
        }
    </script>
    <script src="../assets/js/theme.js"></script>

    <!-- Synto Dashboard Interactions -->
    <script src="../assets/js/synto-interactions.js"></script>
</body>
</html>
