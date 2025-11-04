<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !hasRole('admin')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access required']);
    exit;
}

$db = getDBConnection();
$userId = $_SESSION['user_id'];

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

try {
    switch ($action) {
        case 'update_project_budget':
            $projectId = $input['project_id'] ?? null;
            $budgetInr = $input['budget_inr'] ?? 0;
            $hourlyRateInr = $input['hourly_rate_inr'] ?? 0;
            $additionalCostsInr = $input['additional_costs_inr'] ?? 0;
            $clientBillingInr = $input['client_billing_inr'] ?? 0;
            $profitMarginPercent = $input['profit_margin_percent'] ?? 0;

            if (!$projectId) {
                throw new Exception('Project ID is required');
            }

            $stmt = $db->prepare("
                UPDATE projects
                SET budget_inr = ?,
                    hourly_rate_inr = ?,
                    additional_costs_inr = ?,
                    client_billing_inr = ?,
                    profit_margin_percent = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $budgetInr,
                $hourlyRateInr,
                $additionalCostsInr,
                $clientBillingInr,
                $profitMarginPercent,
                $projectId
            ]);

            // Calculate actual costs based on time logs
            calculateProjectCosts($db, $projectId);

            echo json_encode([
                'success' => true,
                'message' => 'Budget updated successfully'
            ]);
            break;

        case 'update_payment_status':
            $projectId = $input['project_id'] ?? null;
            $paymentReceivedInr = $input['payment_received_inr'] ?? 0;
            $paymentStatus = $input['payment_status'] ?? 'pending';

            if (!$projectId) {
                throw new Exception('Project ID is required');
            }

            $stmt = $db->prepare("
                UPDATE projects
                SET payment_received_inr = ?,
                    payment_status = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$paymentReceivedInr, $paymentStatus, $projectId]);

            echo json_encode([
                'success' => true,
                'message' => 'Payment status updated'
            ]);
            break;

        case 'add_expense':
            $projectId = $input['project_id'] ?? null;
            $expenseType = $input['expense_type'] ?? null;
            $description = $input['description'] ?? '';
            $amountInr = $input['amount_inr'] ?? 0;
            $expenseDate = $input['expense_date'] ?? date('Y-m-d');

            if (!$projectId || !$expenseType || !$description) {
                throw new Exception('Project ID, expense type, and description are required');
            }

            $stmt = $db->prepare("
                INSERT INTO project_expenses (project_id, expense_type, description, amount_inr, expense_date, added_by)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$projectId, $expenseType, $description, $amountInr, $expenseDate, $userId]);

            // Recalculate project costs
            calculateProjectCosts($db, $projectId);

            echo json_encode([
                'success' => true,
                'message' => 'Expense added successfully',
                'expense_id' => $db->lastInsertId()
            ]);
            break;

        case 'get_project_budget':
            $projectId = $input['project_id'] ?? null;

            if (!$projectId) {
                throw new Exception('Project ID is required');
            }

            // Get project budget data
            $stmt = $db->prepare("
                SELECT p.*,
                       u.full_name as manager_name
                FROM projects p
                LEFT JOIN users u ON p.assigned_manager = u.id
                WHERE p.id = ?
            ");
            $stmt->execute([$projectId]);
            $project = $stmt->fetch();

            // Get expenses
            $stmt = $db->prepare("
                SELECT pe.*, u.full_name as added_by_name
                FROM project_expenses pe
                JOIN users u ON pe.added_by = u.id
                WHERE pe.project_id = ?
                ORDER BY pe.expense_date DESC
            ");
            $stmt->execute([$projectId]);
            $expenses = $stmt->fetchAll();

            // Calculate labor costs from time logs
            $stmt = $db->prepare("
                SELECT
                    SUM(tl.duration_minutes / 60 * COALESCE(u.hourly_rate_inr, p.hourly_rate_inr, 0)) as labor_cost
                FROM time_logs tl
                LEFT JOIN users u ON tl.user_id = u.id
                JOIN projects p ON tl.project_id = p.id
                WHERE tl.project_id = ? AND tl.end_time IS NOT NULL
            ");
            $stmt->execute([$projectId]);
            $laborCost = $stmt->fetch()['labor_cost'] ?? 0;

            // Calculate total expenses
            $totalExpenses = array_sum(array_column($expenses, 'amount_inr'));

            // Calculate total cost
            $totalCost = $laborCost + $totalExpenses + ($project['additional_costs_inr'] ?? 0);

            // Calculate profit/loss
            $revenue = $project['client_billing_inr'] ?? 0;
            $profitLoss = $revenue - $totalCost;
            $profitMargin = $revenue > 0 ? ($profitLoss / $revenue) * 100 : 0;

            echo json_encode([
                'success' => true,
                'project' => $project,
                'expenses' => $expenses,
                'financial_summary' => [
                    'labor_cost_inr' => round($laborCost, 2),
                    'expenses_inr' => round($totalExpenses, 2),
                    'additional_costs_inr' => round($project['additional_costs_inr'] ?? 0, 2),
                    'total_cost_inr' => round($totalCost, 2),
                    'client_billing_inr' => round($revenue, 2),
                    'payment_received_inr' => round($project['payment_received_inr'] ?? 0, 2),
                    'profit_loss_inr' => round($profitLoss, 2),
                    'profit_margin_percent' => round($profitMargin, 2),
                    'budget_inr' => round($project['budget_inr'] ?? 0, 2),
                    'budget_variance_inr' => round(($project['budget_inr'] ?? 0) - $totalCost, 2)
                ]
            ]);
            break;

        case 'get_pl_report':
            $startDate = $input['start_date'] ?? date('Y-m-01');
            $endDate = $input['end_date'] ?? date('Y-m-t');

            // Get all projects in date range
            $stmt = $db->prepare("
                SELECT
                    p.id,
                    p.project_name,
                    p.client_name,
                    p.status,
                    p.budget_inr,
                    p.actual_cost_inr,
                    p.client_billing_inr,
                    p.payment_received_inr,
                    p.payment_status
                FROM projects p
                WHERE p.created_at BETWEEN ? AND ?
                   OR p.completed_date BETWEEN ? AND ?
                ORDER BY p.created_at DESC
            ");
            $stmt->execute([$startDate, $endDate, $startDate, $endDate]);
            $projects = $stmt->fetchAll();

            $totalRevenue = 0;
            $totalCost = 0;
            $totalReceived = 0;

            foreach ($projects as &$project) {
                // Calculate costs for each project
                $costs = calculateProjectCosts($db, $project['id'], false);
                $project['calculated_cost'] = $costs['total_cost'];
                $project['profit_loss'] = ($project['client_billing_inr'] ?? 0) - $costs['total_cost'];

                $totalRevenue += $project['client_billing_inr'] ?? 0;
                $totalCost += $costs['total_cost'];
                $totalReceived += $project['payment_received_inr'] ?? 0;
            }

            $totalProfit = $totalRevenue - $totalCost;
            $profitMargin = $totalRevenue > 0 ? ($totalProfit / $totalRevenue) * 100 : 0;

            echo json_encode([
                'success' => true,
                'projects' => $projects,
                'summary' => [
                    'total_revenue_inr' => round($totalRevenue, 2),
                    'total_cost_inr' => round($totalCost, 2),
                    'total_profit_inr' => round($totalProfit, 2),
                    'profit_margin_percent' => round($profitMargin, 2),
                    'total_received_inr' => round($totalReceived, 2),
                    'pending_payment_inr' => round($totalRevenue - $totalReceived, 2),
                    'project_count' => count($projects)
                ],
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ]
            ]);
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Calculate and update project costs
 */
function calculateProjectCosts($db, $projectId, $update = true) {
    // Calculate labor costs
    $stmt = $db->prepare("
        SELECT
            SUM(tl.duration_minutes / 60 * COALESCE(u.hourly_rate_inr, p.hourly_rate_inr, 0)) as labor_cost
        FROM time_logs tl
        LEFT JOIN users u ON tl.user_id = u.id
        JOIN projects p ON tl.project_id = p.id
        WHERE tl.project_id = ? AND tl.end_time IS NOT NULL
    ");
    $stmt->execute([$projectId]);
    $laborCost = $stmt->fetch()['labor_cost'] ?? 0;

    // Calculate expenses
    $stmt = $db->prepare("
        SELECT SUM(amount_inr) as total_expenses
        FROM project_expenses
        WHERE project_id = ?
    ");
    $stmt->execute([$projectId]);
    $expenses = $stmt->fetch()['total_expenses'] ?? 0;

    // Get additional costs
    $stmt = $db->prepare("SELECT additional_costs_inr FROM projects WHERE id = ?");
    $stmt->execute([$projectId]);
    $additionalCosts = $stmt->fetch()['additional_costs_inr'] ?? 0;

    $totalCost = $laborCost + $expenses + $additionalCosts;

    // Update project
    if ($update) {
        $stmt = $db->prepare("UPDATE projects SET actual_cost_inr = ? WHERE id = ?");
        $stmt->execute([$totalCost, $projectId]);
    }

    return [
        'labor_cost' => $laborCost,
        'expenses' => $expenses,
        'additional_costs' => $additionalCosts,
        'total_cost' => $totalCost
    ];
}
?>
