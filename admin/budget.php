<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || (!hasRole('admin') && !hasRole('manager'))) {
    redirect('../login.php');
}

$db = getDBConnection();
$currentUser = getCurrentUser();

// Check if expenses table exists
try {
    $db->query("SELECT 1 FROM expenses LIMIT 1");
    $expensesTableExists = true;
} catch (PDOException $e) {
    $expensesTableExists = false;
}

// Get all projects with budget info
if ($expensesTableExists) {
    $projects = $db->query("
        SELECT
            p.*,
            (SELECT SUM(amount) FROM expenses WHERE project_id = p.id) as total_expenses,
            (SELECT SUM(duration_minutes) FROM time_logs WHERE project_id = p.id AND end_time IS NOT NULL) as total_minutes,
            (SELECT COUNT(*) FROM expenses WHERE project_id = p.id) as expense_count,
            u.full_name as manager_name
        FROM projects p
        LEFT JOIN users u ON p.assigned_manager = u.id
        WHERE p.status IN ('planning', 'in_progress', 'review', 'completed')
        ORDER BY p.created_at DESC
    ")->fetchAll();
} else {
    $projects = $db->query("
        SELECT
            p.*,
            0 as total_expenses,
            (SELECT SUM(duration_minutes) FROM time_logs WHERE project_id = p.id AND end_time IS NOT NULL) as total_minutes,
            0 as expense_count,
            u.full_name as manager_name
        FROM projects p
        LEFT JOIN users u ON p.assigned_manager = u.id
        WHERE p.status IN ('planning', 'in_progress', 'review', 'completed')
        ORDER BY p.created_at DESC
    ")->fetchAll();
}

// Calculate totals
$totalBudget = 0;
$totalActual = 0;
$totalExpenses = 0;

foreach ($projects as $project) {
    $totalBudget += $project['budget'];

    // Calculate labor cost (hours * average rate)
    $hours = ($project['total_minutes'] ?? 0) / 60;
    $stmt = $db->prepare("
        SELECT AVG(u.hourly_rate) as avg_rate
        FROM time_logs tl
        JOIN users u ON tl.user_id = u.id
        WHERE tl.project_id = ? AND tl.end_time IS NOT NULL
    ");
    $stmt->execute([$project['id']]);
    $avgRate = $stmt->fetch()['avg_rate'] ?? 50; // Default ₹50/hr if no rate set
    $laborCost = $hours * $avgRate;

    $expenses = $project['total_expenses'] ?? 0;
    $actualCost = $laborCost + $expenses;

    $totalActual += $actualCost;
    $totalExpenses += $expenses;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budget Tracking V3 - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/vien-v3.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include '../includes/quick-actions-assets.php'; ?>
</head>
<body>
    <div class="app-container">
        <?php include '../includes/v3-admin-sidebar.php'; ?>

        <div class="main-content">
            <?php include '../includes/v3-header.php'; ?>

            <div class="content-wrapper">
                <div class="page-header">
                    <h1><i class="fas fa-wallet"></i> Budget & Cost Tracking</h1>
                </div>

                <?php if (!$expensesTableExists): ?>
                <div class="alert alert-warning" style="margin-bottom: 24px;">
                    <strong><i class="fas fa-exclamation-triangle"></i> Notice:</strong> Expenses tracking is not yet set up. Budget calculations are based on labor costs only. Run <code>upgrade-schema.sql</code> to enable full expense tracking.
                </div>
                <?php endif; ?>

                <!-- Summary Cards -->
                <div class="stats-grid">
                    <div class="stat-card blue">
                        <div class="stat-card-header">
                            <div>
                                <div class="stat-label">Total Budget</div>
                                <div class="stat-value">₹<?php echo number_format($totalBudget, 0); ?></div>
                                <div class="stat-change">Allocated</div>
                            </div>
                            <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                        </div>
                    </div>

                    <div class="stat-card <?php echo $totalActual > $totalBudget ? 'red' : 'green'; ?>">
                        <div class="stat-card-header">
                            <div>
                                <div class="stat-label">Actual Cost</div>
                                <div class="stat-value">₹<?php echo number_format($totalActual, 0); ?></div>
                                <div class="stat-change">Labor<?php echo $expensesTableExists ? ' + Expenses' : ' Only'; ?></div>
                            </div>
                            <div class="stat-icon"><i class="fas fa-wallet"></i></div>
                        </div>
                    </div>

                    <?php if ($expensesTableExists): ?>
                    <div class="stat-card orange">
                        <div class="stat-card-header">
                            <div>
                                <div class="stat-label">Expenses</div>
                                <div class="stat-value">₹<?php echo number_format($totalExpenses, 0); ?></div>
                                <div class="stat-change">Direct Costs</div>
                            </div>
                            <div class="stat-icon"><i class="fas fa-credit-card"></i></div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="stat-card <?php echo ($totalBudget - $totalActual) < 0 ? 'red' : 'blue'; ?>">
                        <div class="stat-card-header">
                            <div>
                                <div class="stat-label">Variance</div>
                                <div class="stat-value"><?php echo ($totalBudget - $totalActual) >= 0 ? '' : '-'; ?>₹<?php echo number_format(abs($totalBudget - $totalActual), 0); ?></div>
                                <div class="stat-change"><?php echo ($totalBudget - $totalActual) >= 0 ? 'Under Budget' : 'Over Budget'; ?></div>
                            </div>
                            <div class="stat-icon"><i class="fas <?php echo ($totalBudget - $totalActual) >= 0 ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?>"></i></div>
                        </div>
                    </div>
                </div>

                <!-- Projects Budget Table -->
                <div class="card">
                    <div class="card-header">
                        <h3>Project Budgets & Costs</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($projects)): ?>
                            <p style="text-align: center; color: var(--text-secondary); padding: var(--space-10);">
                                No projects found.
                            </p>
                        <?php else: ?>
                            <div class="table-container">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Project</th>
                                            <th>Status</th>
                                            <th>Manager</th>
                                            <th>Budget</th>
                                            <th>Labor Cost</th>
                                            <?php if ($expensesTableExists): ?>
                                            <th>Expenses</th>
                                            <?php endif; ?>
                                            <th>Total Cost</th>
                                            <th>Variance</th>
                                            <th>% Used</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($projects as $project): ?>
                                        <?php
                                            // Calculate costs
                                            $hours = ($project['total_minutes'] ?? 0) / 60;
                                            $stmt = $db->prepare("
                                                SELECT AVG(u.hourly_rate) as avg_rate
                                                FROM time_logs tl
                                                JOIN users u ON tl.user_id = u.id
                                                WHERE tl.project_id = ? AND tl.end_time IS NOT NULL
                                            ");
                                            $stmt->execute([$project['id']]);
                                            $avgRate = $stmt->fetch()['avg_rate'] ?? 50;
                                            $laborCost = $hours * $avgRate;

                                            $expenses = $project['total_expenses'] ?? 0;
                                            $totalCost = $laborCost + $expenses;
                                            $variance = $project['budget'] - $totalCost;
                                            $percentUsed = $project['budget'] > 0 ? ($totalCost / $project['budget']) * 100 : 0;

                                            $statusClass = $variance >= 0 ? 'status-completed' : 'status-blocked';
                                        ?>
                                        <tr>
                                            <td><strong><?php echo e($project['project_name']); ?></strong></td>
                                            <td>
                                                <span class="badge <?php echo getStatusClass($project['status']); ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $project['status'])); ?>
                                                </span>
                                            </td>
                                            <td><?php echo e($project['manager_name'] ?? 'Unassigned'); ?></td>
                                            <td><strong>₹<?php echo number_format($project['budget'], 0); ?></strong></td>
                                            <td>₹<?php echo number_format($laborCost, 0); ?></td>
                                            <?php if ($expensesTableExists): ?>
                                            <td>
                                                ₹<?php echo number_format($expenses, 0); ?>
                                                <?php if ($project['expense_count'] > 0): ?>
                                                    <span style="font-size: 11px; color: var(--text-secondary);">(<?php echo $project['expense_count']; ?>)</span>
                                                <?php endif; ?>
                                            </td>
                                            <?php endif; ?>
                                            <td><strong>₹<?php echo number_format($totalCost, 0); ?></strong></td>
                                            <td>
                                                <span class="badge <?php echo $statusClass; ?>">
                                                    <?php echo $variance >= 0 ? '+' : ''; ?>₹<?php echo number_format($variance, 0); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="progress-bar-container">
                                                    <div class="progress-bar <?php echo $percentUsed > 100 ? 'complete' : ($percentUsed > 75 ? 'high' : 'medium'); ?>"
                                                         style="width: <?php echo min($percentUsed, 100); ?>%"></div>
                                                </div>
                                                <small style="font-size: 11px; margin-top: 4px; display: block;">
                                                    <?php echo number_format($percentUsed, 1); ?>%
                                                </small>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Budget Summary by Status -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 24px;">
                    <div class="card">
                        <div class="card-header">
                            <h3>Budget by Status</h3>
                        </div>
                        <div class="card-body">
                            <?php
                            $statusBudgets = [];
                            foreach ($projects as $project) {
                                $status = $project['status'];
                                if (!isset($statusBudgets[$status])) {
                                    $statusBudgets[$status] = ['budget' => 0, 'count' => 0];
                                }
                                $statusBudgets[$status]['budget'] += $project['budget'];
                                $statusBudgets[$status]['count']++;
                            }
                            ?>
                            <?php if (empty($statusBudgets)): ?>
                                <p style="text-align: center; color: var(--text-secondary); padding: var(--space-6);">
                                    No budget data available
                                </p>
                            <?php else: ?>
                                <?php foreach ($statusBudgets as $status => $data): ?>
                                <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid var(--border-light);">
                                    <div>
                                        <span class="badge <?php echo getStatusClass($status); ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $status)); ?>
                                        </span>
                                        <span style="font-size: 13px; color: var(--text-secondary); margin-left: 8px;">
                                            (<?php echo $data['count']; ?> projects)
                                        </span>
                                    </div>
                                    <div style="font-weight: 600;">
                                        ₹<?php echo number_format($data['budget'], 0); ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h3>Cost Breakdown</h3>
                        </div>
                        <div class="card-body">
                            <?php
                            $laborTotal = $totalActual - $totalExpenses;
                            $laborPercent = $totalActual > 0 ? ($laborTotal / $totalActual) * 100 : 0;
                            $expensePercent = $totalActual > 0 ? ($totalExpenses / $totalActual) * 100 : 0;
                            ?>
                            <div style="margin-bottom: 20px;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                    <span style="font-weight: 600;"><i class="fas fa-briefcase"></i> Labor Costs</span>
                                    <span style="font-weight: 600;">₹<?php echo number_format($laborTotal, 0); ?></span>
                                </div>
                                <div class="progress-bar-container" style="height: 12px;">
                                    <div class="progress-bar medium" style="width: <?php echo $laborPercent; ?>%"></div>
                                </div>
                                <small style="font-size: 12px; color: var(--text-secondary); margin-top: 4px; display: block;">
                                    <?php echo number_format($laborPercent, 1); ?>% of total cost
                                </small>
                            </div>

                            <?php if ($expensesTableExists): ?>
                            <div>
                                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                    <span style="font-weight: 600;"><i class="fas fa-credit-card"></i> Direct Expenses</span>
                                    <span style="font-weight: 600;">₹<?php echo number_format($totalExpenses, 0); ?></span>
                                </div>
                                <div class="progress-bar-container" style="height: 12px;">
                                    <div class="progress-bar high" style="width: <?php echo $expensePercent; ?>%"></div>
                                </div>
                                <small style="font-size: 12px; color: var(--text-secondary); margin-top: 4px; display: block;">
                                    <?php echo number_format($expensePercent, 1); ?>% of total cost
                                </small>
                            </div>
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
