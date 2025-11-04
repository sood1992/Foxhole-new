<?php
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/gamification-functions.php';

if (!isLoggedIn() || !hasRole('employee')) {
    redirect('../login.php');
}

$db = getDBConnection();
$currentUser = getCurrentUser();

// Get all active tasks
$stmt = $db->prepare("
    SELECT t.*, p.project_name, p.client_name
    FROM tasks t
    JOIN projects p ON t.project_id = p.id
    WHERE t.assigned_to = ?
        AND t.status != 'completed'
    ORDER BY t.due_date ASC
");
$stmt->execute([$currentUser['id']]);
$tasks = $stmt->fetchAll();

// Organize tasks into Eisenhower Matrix quadrants
$quadrants = [
    'urgent-important' => [],
    'urgent-not-important' => [],
    'not-urgent-important' => [],
    'not-urgent-not-important' => []
];

foreach ($tasks as $task) {
    $quadrant = getEisenhowerQuadrant($task);
    $task['quadrant_info'] = $quadrant;
    $quadrants[$quadrant['quadrant']][] = $task;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eisenhower Matrix - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/vien-v3.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .matrix-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-top: 24px;
        }

        .quadrant {
            background: var(--bg-secondary);
            border-radius: 12px;
            padding: 20px;
            min-height: 400px;
            border: 2px solid var(--border-color);
        }

        .quadrant-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
            padding-bottom: 16px;
            border-bottom: 2px solid var(--border-color);
        }

        .quadrant-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }

        .quadrant-title {
            flex: 1;
        }

        .quadrant-title h3 {
            margin: 0 0 4px;
            color: var(--text-primary);
            font-size: 18px;
        }

        .quadrant-title p {
            margin: 0;
            font-size: 13px;
            color: var(--text-secondary);
        }

        .matrix-task {
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 12px;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .matrix-task:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .matrix-task-title {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .matrix-task-meta {
            display: flex;
            gap: 12px;
            font-size: 12px;
            color: var(--text-secondary);
            flex-wrap: wrap;
        }

        .empty-quadrant {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-secondary);
        }

        .empty-quadrant i {
            font-size: 48px;
            margin-bottom: 12px;
            opacity: 0.3;
        }

        @media (max-width: 968px) {
            .matrix-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <?php include '../includes/v3-employee-sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <?php include '../includes/v3-header.php'; ?>

            <div class="content-wrapper">
                <div style="margin-bottom: 24px;">
                    <h1 style="margin: 0 0 8px; color: var(--text-primary);">
                        <i class="fas fa-th"></i> Eisenhower Matrix
                    </h1>
                    <p style="margin: 0; color: var(--text-secondary);">
                        Prioritize your tasks using the proven Eisenhower method. Focus on what truly matters.
                    </p>
                </div>

                <div class="matrix-container">
                    <!-- Quadrant 1: Urgent & Important -->
                    <div class="quadrant" style="border-color: #ef4444;">
                        <div class="quadrant-header">
                            <div class="quadrant-icon" style="background: #ef4444;">
                                <i class="fas fa-exclamation-circle"></i>
                            </div>
                            <div class="quadrant-title">
                                <h3>Do First</h3>
                                <p>Urgent & Important - Handle immediately</p>
                            </div>
                            <span class="badge" style="background: #ef4444; color: white;">
                                <?php echo count($quadrants['urgent-important']); ?>
                            </span>
                        </div>
                        <div class="quadrant-tasks">
                            <?php if (empty($quadrants['urgent-important'])): ?>
                                <div class="empty-quadrant">
                                    <i class="fas fa-check-circle"></i>
                                    <p>No urgent tasks!</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($quadrants['urgent-important'] as $task): ?>
                                <div class="matrix-task">
                                    <div class="matrix-task-title"><?php echo e($task['task_name']); ?></div>
                                    <div class="matrix-task-meta">
                                        <span>📁 <?php echo e($task['project_name']); ?></span>
                                        <?php if ($task['due_date']): ?>
                                            <span>📅 <?php echo date('M d', strtotime($task['due_date'])); ?></span>
                                        <?php endif; ?>
                                        <span class="badge <?php echo getStatusClass($task['status']); ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $task['status'])); ?>
                                        </span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Quadrant 2: Not Urgent & Important -->
                    <div class="quadrant" style="border-color: #3b82f6;">
                        <div class="quadrant-header">
                            <div class="quadrant-icon" style="background: #3b82f6;">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <div class="quadrant-title">
                                <h3>Schedule</h3>
                                <p>Important but not urgent - Plan ahead</p>
                            </div>
                            <span class="badge" style="background: #3b82f6; color: white;">
                                <?php echo count($quadrants['not-urgent-important']); ?>
                            </span>
                        </div>
                        <div class="quadrant-tasks">
                            <?php if (empty($quadrants['not-urgent-important'])): ?>
                                <div class="empty-quadrant">
                                    <i class="fas fa-calendar-check"></i>
                                    <p>No tasks to schedule</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($quadrants['not-urgent-important'] as $task): ?>
                                <div class="matrix-task">
                                    <div class="matrix-task-title"><?php echo e($task['task_name']); ?></div>
                                    <div class="matrix-task-meta">
                                        <span>📁 <?php echo e($task['project_name']); ?></span>
                                        <?php if ($task['due_date']): ?>
                                            <span>📅 <?php echo date('M d', strtotime($task['due_date'])); ?></span>
                                        <?php endif; ?>
                                        <span class="badge <?php echo getStatusClass($task['status']); ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $task['status'])); ?>
                                        </span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Quadrant 3: Urgent & Not Important -->
                    <div class="quadrant" style="border-color: #f59e0b;">
                        <div class="quadrant-header">
                            <div class="quadrant-icon" style="background: #f59e0b;">
                                <i class="fas fa-user-clock"></i>
                            </div>
                            <div class="quadrant-title">
                                <h3>Delegate</h3>
                                <p>Urgent but not important - Delegate if possible</p>
                            </div>
                            <span class="badge" style="background: #f59e0b; color: white;">
                                <?php echo count($quadrants['urgent-not-important']); ?>
                            </span>
                        </div>
                        <div class="quadrant-tasks">
                            <?php if (empty($quadrants['urgent-not-important'])): ?>
                                <div class="empty-quadrant">
                                    <i class="fas fa-tasks"></i>
                                    <p>No tasks here</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($quadrants['urgent-not-important'] as $task): ?>
                                <div class="matrix-task">
                                    <div class="matrix-task-title"><?php echo e($task['task_name']); ?></div>
                                    <div class="matrix-task-meta">
                                        <span>📁 <?php echo e($task['project_name']); ?></span>
                                        <?php if ($task['due_date']): ?>
                                            <span>📅 <?php echo date('M d', strtotime($task['due_date'])); ?></span>
                                        <?php endif; ?>
                                        <span class="badge <?php echo getStatusClass($task['status']); ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $task['status'])); ?>
                                        </span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Quadrant 4: Not Urgent & Not Important -->
                    <div class="quadrant" style="border-color: #6b7280;">
                        <div class="quadrant-header">
                            <div class="quadrant-icon" style="background: #6b7280;">
                                <i class="fas fa-trash-alt"></i>
                            </div>
                            <div class="quadrant-title">
                                <h3>Eliminate</h3>
                                <p>Neither urgent nor important - Consider removing</p>
                            </div>
                            <span class="badge" style="background: #6b7280; color: white;">
                                <?php echo count($quadrants['not-urgent-not-important']); ?>
                            </span>
                        </div>
                        <div class="quadrant-tasks">
                            <?php if (empty($quadrants['not-urgent-not-important'])): ?>
                                <div class="empty-quadrant">
                                    <i class="fas fa-check"></i>
                                    <p>No low-priority tasks</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($quadrants['not-urgent-not-important'] as $task): ?>
                                <div class="matrix-task">
                                    <div class="matrix-task-title"><?php echo e($task['task_name']); ?></div>
                                    <div class="matrix-task-meta">
                                        <span>📁 <?php echo e($task['project_name']); ?></span>
                                        <?php if ($task['due_date']): ?>
                                            <span>📅 <?php echo date('M d', strtotime($task['due_date'])); ?></span>
                                        <?php endif; ?>
                                        <span class="badge <?php echo getStatusClass($task['status']); ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $task['status'])); ?>
                                        </span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/theme.js"></script>
</body>
</html>
