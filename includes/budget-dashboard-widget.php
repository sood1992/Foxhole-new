<?php
/**
 * Budget Dashboard Widget
 * Include this in project detail pages
 * Usage: include '../includes/budget-dashboard-widget.php';
 */

// Ensure $projectId is available
if (!isset($projectId)) {
    $projectId = $_GET['id'] ?? null;
}

$canManageBudget = in_array($_SESSION['role'], ['admin', 'manager']);
?>

<style>
.budget-summary-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.budget-card {
    background: var(--card-bg);
    border: 1px solid var(--border-light);
    border-radius: var(--radius-md);
    padding: 20px;
    text-align: center;
}

.budget-value {
    font-size: 28px;
    font-weight: 700;
    color: var(--heading-color);
    margin-bottom: 4px;
}

.budget-label {
    font-size: 13px;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.budget-progress {
    height: 12px;
    background: var(--border-light);
    border-radius: 6px;
    overflow: hidden;
    margin: 20px 0;
}

.budget-progress-bar {
    height: 100%;
    background: linear-gradient(90deg, #17b06b 0%, #14d48f 100%);
    transition: width 0.3s ease;
}

.budget-progress-bar.warning {
    background: linear-gradient(90deg, #f8b739 0%, #ffce54 100%);
}

.budget-progress-bar.danger {
    background: linear-gradient(90deg, #ec4561 0%, #ff6b9d 100%);
}

.expense-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px;
    background: var(--bg-secondary);
    border-radius: var(--radius-sm);
    margin-bottom: 8px;
}

.expense-status-pending {
    border-left: 3px solid var(--warning);
}

.expense-status-approved {
    border-left: 3px solid var(--success);
}

.expense-status-rejected {
    border-left: 3px solid var(--danger);
}
</style>

<div class="card" style="margin-bottom: 30px;">
    <div class="card-header">
        <h3><i class="fas fa-dollar-sign"></i> Project Budget</h3>
        <?php if ($canManageBudget): ?>
        <div>
            <button class="btn btn-secondary btn-sm" onclick="editBudget()">
                <i class="fas fa-edit"></i> Edit Budget
            </button>
            <button class="btn btn-primary btn-sm" onclick="openAddExpenseModal()">
                <i class="fas fa-plus"></i> Add Expense
            </button>
        </div>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <div id="budget-summary">
            <div style="text-align: center; padding: 40px;">
                <i class="fas fa-spinner fa-spin" style="font-size: 32px; color: var(--primary);"></i>
                <p>Loading budget...</p>
            </div>
        </div>
    </div>
</div>

<!-- Edit Budget Modal -->
<?php if ($canManageBudget): ?>
<div id="editBudgetModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Project Budget</h3>
            <button class="close-modal" onclick="closeEditBudgetModal()">×</button>
        </div>
        <form id="editBudgetForm" onsubmit="submitBudget(event)">
            <div class="form-group">
                <label>Total Budget ($) <span style="color: var(--danger);">*</span></label>
                <input type="number" name="total_budget" class="form-control" step="0.01" required>
            </div>

            <h4 style="margin-top: 20px; margin-bottom: 16px;">Budget Breakdown (Optional)</h4>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Labor Budget ($)</label>
                        <input type="number" name="labor_budget" class="form-control" step="0.01">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Equipment Budget ($)</label>
                        <input type="number" name="equipment_budget" class="form-control" step="0.01">
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Materials Budget ($)</label>
                        <input type="number" name="materials_budget" class="form-control" step="0.01">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Other Budget ($)</label>
                        <input type="number" name="other_budget" class="form-control" step="0.01">
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" class="form-control" rows="3"></textarea>
            </div>

            <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 24px;">
                <button type="button" class="btn btn-secondary" onclick="closeEditBudgetModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Budget</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Expense Modal -->
<div id="addExpenseModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add Expense</h3>
            <button class="close-modal" onclick="closeAddExpenseModal()">×</button>
        </div>
        <form id="addExpenseForm" onsubmit="submitExpense(event)">
            <div class="form-group">
                <label>Expense Title <span style="color: var(--danger);">*</span></label>
                <input type="text" name="expense_title" class="form-control" required placeholder="e.g., Camera Rental">
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Category <span style="color: var(--danger);">*</span></label>
                        <select name="expense_category" class="form-control" required>
                            <option value="">Select category</option>
                            <option value="equipment">Equipment</option>
                            <option value="travel">Travel</option>
                            <option value="materials">Materials</option>
                            <option value="stock_footage">Stock Footage</option>
                            <option value="music_licensing">Music Licensing</option>
                            <option value="talent">Talent/Models</option>
                            <option value="location">Location Fee</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Amount ($) <span style="color: var(--danger);">*</span></label>
                        <input type="number" name="amount" class="form-control" step="0.01" required>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label>Expense Date <span style="color: var(--danger);">*</span></label>
                <input type="date" name="expense_date" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control" rows="3"></textarea>
            </div>

            <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 24px;">
                <button type="button" class="btn btn-secondary" onclick="closeAddExpenseModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Expense</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    loadBudget();
});

function loadBudget() {
    fetch('/api/budget.php?project_id=<?php echo $projectId; ?>')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                displayBudget(data);
            }
        })
        .catch(err => console.error('Error loading budget:', err));
}

function displayBudget(data) {
    const { budget, expenses, summary } = data;
    const container = document.getElementById('budget-summary');

    const percentUsed = summary.percent_used || 0;
    let progressClass = '';
    if (percentUsed > 90) progressClass = 'danger';
    else if (percentUsed > 75) progressClass = 'warning';

    container.innerHTML = `
        <!-- Summary Cards -->
        <div class="budget-summary-cards">
            <div class="budget-card">
                <div class="budget-value">$${formatNumber(summary.total_budget)}</div>
                <div class="budget-label">Total Budget</div>
            </div>
            <div class="budget-card">
                <div class="budget-value">$${formatNumber(summary.total_spent)}</div>
                <div class="budget-label">Total Spent</div>
            </div>
            <div class="budget-card">
                <div class="budget-value">$${formatNumber(summary.remaining)}</div>
                <div class="budget-label">Remaining</div>
            </div>
            <div class="budget-card">
                <div class="budget-value">${percentUsed}%</div>
                <div class="budget-label">Budget Used</div>
            </div>
        </div>

        <!-- Progress Bar -->
        <div class="budget-progress">
            <div class="budget-progress-bar ${progressClass}" style="width: ${Math.min(100, percentUsed)}%;"></div>
        </div>

        <!-- Breakdown -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px; margin-bottom: 24px;">
            <div>
                <div style="font-size: 13px; color: var(--text-secondary); margin-bottom: 4px;">Labor Cost (Time Logs)</div>
                <div style="font-size: 18px; font-weight: 600; color: var(--heading-color);">$${formatNumber(summary.labor_cost)}</div>
            </div>
            ${Object.entries(summary.expenses_by_category || {}).map(([cat, amount]) => `
                <div>
                    <div style="font-size: 13px; color: var(--text-secondary); margin-bottom: 4px; text-transform: capitalize;">${cat.replace('_', ' ')}</div>
                    <div style="font-size: 18px; font-weight: 600; color: var(--heading-color);">$${formatNumber(amount)}</div>
                </div>
            `).join('')}
        </div>

        <!-- Expenses List -->
        <h4 style="margin-bottom: 16px;">Recent Expenses (${expenses.length})</h4>
        ${expenses.length > 0 ? `
            <div>
                ${expenses.map(expense => {
                    const statusClass = `expense-status-${expense.approval_status}`;
                    const statusBadge = {
                        'pending': '<span class="badge badge-warning">Pending</span>',
                        'approved': '<span class="badge badge-success">Approved</span>',
                        'rejected': '<span class="badge badge-danger">Rejected</span>'
                    }[expense.approval_status];

                    return `
                        <div class="expense-item ${statusClass}">
                            <div style="flex: 1;">
                                <div style="font-weight: 600; color: var(--heading-color); margin-bottom: 4px;">
                                    ${escapeHtml(expense.expense_title)}
                                </div>
                                <div style="font-size: 12px; color: var(--text-secondary);">
                                    <span>${expense.expense_category.replace('_', ' ')}</span>
                                    <span style="margin-left: 12px;">${formatDate(expense.expense_date)}</span>
                                    <span style="margin-left: 12px;">by ${escapeHtml(expense.added_by_name)}</span>
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-size: 18px; font-weight: 700; color: var(--heading-color); margin-bottom: 4px;">
                                    $${formatNumber(expense.amount)}
                                </div>
                                ${statusBadge}
                            </div>
                        </div>
                    `;
                }).join('')}
            </div>
        ` : '<p style="text-align: center; color: var(--text-secondary); padding: 20px;">No expenses recorded yet</p>'}
    `;

    // Store budget data for editing
    window.currentBudget = budget;
}

function editBudget() {
    if (!window.currentBudget) return;

    const form = document.getElementById('editBudgetForm');
    form.total_budget.value = window.currentBudget.total_budget || 0;
    form.labor_budget.value = window.currentBudget.labor_budget || 0;
    form.equipment_budget.value = window.currentBudget.equipment_budget || 0;
    form.materials_budget.value = window.currentBudget.materials_budget || 0;
    form.other_budget.value = window.currentBudget.other_budget || 0;
    form.notes.value = window.currentBudget.notes || '';

    document.getElementById('editBudgetModal').classList.add('active');
}

function closeEditBudgetModal() {
    document.getElementById('editBudgetModal').classList.remove('active');
}

function submitBudget(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData);
    data.action = 'update_budget';
    data.project_id = <?php echo $projectId; ?>;

    fetch('/api/budget.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            closeEditBudgetModal();
            loadBudget();
            alert('Budget updated successfully!');
        } else {
            alert('Error: ' + result.message);
        }
    })
    .catch(err => {
        console.error('Error:', err);
        alert('Error updating budget');
    });
}

function openAddExpenseModal() {
    document.getElementById('addExpenseModal').classList.add('active');
}

function closeAddExpenseModal() {
    document.getElementById('addExpenseModal').classList.remove('active');
    document.getElementById('addExpenseForm').reset();
}

function submitExpense(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData);
    data.action = 'add_expense';
    data.project_id = <?php echo $projectId; ?>;

    fetch('/api/budget.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            closeAddExpenseModal();
            loadBudget();
            alert('Expense added successfully!');
        } else {
            alert('Error: ' + result.message);
        }
    })
    .catch(err => {
        console.error('Error:', err);
        alert('Error adding expense');
    });
}

function formatNumber(num) {
    return parseFloat(num).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>
