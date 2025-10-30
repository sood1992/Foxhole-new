<?php
/**
 * Budget & Expenses API
 * Track project budgets and expenses
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = getDBConnection();
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'];

// GET - Fetch budget information
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $projectId = $_GET['project_id'] ?? null;

    if (!$projectId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Project ID required']);
        exit;
    }

    // Get project budget
    $stmt = $db->prepare("SELECT * FROM project_budgets WHERE project_id = ?");
    $stmt->execute([$projectId]);
    $budget = $stmt->fetch(PDO::FETCH_ASSOC);

    // If no budget exists, create default
    if (!$budget) {
        $stmt = $db->prepare("
            INSERT INTO project_budgets (project_id, total_budget)
            VALUES (?, 0)
        ");
        $stmt->execute([$projectId]);
        $budget = [
            'id' => $db->lastInsertId(),
            'project_id' => $projectId,
            'total_budget' => 0,
            'labor_budget' => 0,
            'equipment_budget' => 0,
            'materials_budget' => 0,
            'other_budget' => 0,
            'notes' => null
        ];
    }

    // Get all expenses
    $stmt = $db->prepare("
        SELECT e.*, u.full_name as added_by_name, a.full_name as approved_by_name
        FROM project_expenses e
        JOIN users u ON e.added_by = u.id
        LEFT JOIN users a ON e.approved_by = a.id
        WHERE e.project_id = ?
        ORDER BY e.expense_date DESC
    ");
    $stmt->execute([$projectId]);
    $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate labor cost from time logs
    $stmt = $db->prepare("
        SELECT SUM(tl.duration_minutes * u.hourly_rate / 60) as labor_cost
        FROM time_logs tl
        JOIN users u ON tl.user_id = u.id
        WHERE tl.project_id = ?
    ");
    $stmt->execute([$projectId]);
    $laborCost = $stmt->fetch(PDO::FETCH_ASSOC)['labor_cost'] ?? 0;

    // Calculate total expenses by category
    $expensesByCategory = [];
    $totalExpenses = 0;
    foreach ($expenses as $expense) {
        if ($expense['approval_status'] === 'approved') {
            $category = $expense['expense_category'];
            if (!isset($expensesByCategory[$category])) {
                $expensesByCategory[$category] = 0;
            }
            $expensesByCategory[$category] += floatval($expense['amount']);
            $totalExpenses += floatval($expense['amount']);
        }
    }

    // Calculate totals
    $totalCost = $laborCost + $totalExpenses;
    $remaining = floatval($budget['total_budget']) - $totalCost;
    $percentUsed = $budget['total_budget'] > 0
        ? round(($totalCost / $budget['total_budget']) * 100, 2)
        : 0;

    echo json_encode([
        'success' => true,
        'budget' => $budget,
        'expenses' => $expenses,
        'summary' => [
            'total_budget' => floatval($budget['total_budget']),
            'labor_cost' => round($laborCost, 2),
            'expenses_total' => round($totalExpenses, 2),
            'total_spent' => round($totalCost, 2),
            'remaining' => round($remaining, 2),
            'percent_used' => $percentUsed,
            'expenses_by_category' => $expensesByCategory
        ]
    ]);
    exit;
}

// POST - Create/Update budget or add expense
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? null;

    // Update budget
    if ($action === 'update_budget') {
        if (!in_array($userRole, ['admin', 'manager'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Only managers can update budgets']);
            exit;
        }

        $projectId = $input['project_id'] ?? null;
        $totalBudget = $input['total_budget'] ?? 0;
        $laborBudget = $input['labor_budget'] ?? 0;
        $equipmentBudget = $input['equipment_budget'] ?? 0;
        $materialsBudget = $input['materials_budget'] ?? 0;
        $otherBudget = $input['other_budget'] ?? 0;
        $notes = $input['notes'] ?? null;

        if (!$projectId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Project ID required']);
            exit;
        }

        $stmt = $db->prepare("
            INSERT INTO project_budgets
            (project_id, total_budget, labor_budget, equipment_budget, materials_budget, other_budget, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            total_budget = VALUES(total_budget),
            labor_budget = VALUES(labor_budget),
            equipment_budget = VALUES(equipment_budget),
            materials_budget = VALUES(materials_budget),
            other_budget = VALUES(other_budget),
            notes = VALUES(notes)
        ");
        $stmt->execute([
            $projectId,
            $totalBudget,
            $laborBudget,
            $equipmentBudget,
            $materialsBudget,
            $otherBudget,
            $notes
        ]);

        echo json_encode(['success' => true, 'message' => 'Budget updated successfully']);
        exit;
    }

    // Add expense
    if ($action === 'add_expense') {
        $projectId = $input['project_id'] ?? null;
        $category = $input['expense_category'] ?? null;
        $title = $input['expense_title'] ?? null;
        $amount = $input['amount'] ?? null;
        $expenseDate = $input['expense_date'] ?? date('Y-m-d');
        $description = $input['description'] ?? null;
        $receiptFile = $input['receipt_file'] ?? null;

        if (!$projectId || !$category || !$title || !$amount) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            exit;
        }

        $stmt = $db->prepare("
            INSERT INTO project_expenses
            (project_id, expense_category, expense_title, description, amount, expense_date, receipt_file, added_by, approval_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        // Auto-approve if user is admin/manager
        $approvalStatus = in_array($userRole, ['admin', 'manager']) ? 'approved' : 'pending';

        $stmt->execute([
            $projectId,
            $category,
            $title,
            $description,
            $amount,
            $expenseDate,
            $receiptFile,
            $userId,
            $approvalStatus
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Expense added successfully',
            'expense_id' => $db->lastInsertId()
        ]);
        exit;
    }
}

// PUT - Approve/reject expense or update
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    $expenseId = $input['expense_id'] ?? null;
    $action = $input['action'] ?? null;

    if (!$expenseId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Expense ID required']);
        exit;
    }

    // Approve/reject expense
    if ($action === 'approve' || $action === 'reject') {
        if (!in_array($userRole, ['admin', 'manager'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Only managers can approve expenses']);
            exit;
        }

        $status = $action === 'approve' ? 'approved' : 'rejected';
        $stmt = $db->prepare("
            UPDATE project_expenses
            SET approval_status = ?, approved_by = ?
            WHERE id = ?
        ");
        $stmt->execute([$status, $userId, $expenseId]);

        echo json_encode([
            'success' => true,
            'message' => 'Expense ' . $status . ' successfully'
        ]);
        exit;
    }

    // Update expense
    $updates = [];
    $params = [];

    if (isset($input['expense_title'])) {
        $updates[] = "expense_title = ?";
        $params[] = $input['expense_title'];
    }
    if (isset($input['description'])) {
        $updates[] = "description = ?";
        $params[] = $input['description'];
    }
    if (isset($input['amount'])) {
        $updates[] = "amount = ?";
        $params[] = $input['amount'];
    }
    if (isset($input['expense_category'])) {
        $updates[] = "expense_category = ?";
        $params[] = $input['expense_category'];
    }
    if (isset($input['expense_date'])) {
        $updates[] = "expense_date = ?";
        $params[] = $input['expense_date'];
    }

    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No updates provided']);
        exit;
    }

    $params[] = $expenseId;
    $query = "UPDATE project_expenses SET " . implode(", ", $updates) . " WHERE id = ?";

    $stmt = $db->prepare($query);
    $stmt->execute($params);

    echo json_encode(['success' => true, 'message' => 'Expense updated successfully']);
    exit;
}

// DELETE - Remove expense
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $input = json_decode(file_get_contents('php://input'), true);
    $expenseId = $input['expense_id'] ?? null;

    if (!$expenseId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Expense ID required']);
        exit;
    }

    // Check permission
    $stmt = $db->prepare("SELECT added_by FROM project_expenses WHERE id = ?");
    $stmt->execute([$expenseId]);
    $expense = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$expense) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Expense not found']);
        exit;
    }

    // Only creator or admin/manager can delete
    if ($expense['added_by'] != $userId && !in_array($userRole, ['admin', 'manager'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        exit;
    }

    $stmt = $db->prepare("DELETE FROM project_expenses WHERE id = ?");
    $stmt->execute([$expenseId]);

    echo json_encode(['success' => true, 'message' => 'Expense deleted successfully']);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
?>
