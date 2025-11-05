<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !hasRole('admin')) {
    redirect('../login.php');
}

$db = getDBConnection();
$currentUser = getCurrentUser();

// Get date range from parameters (default: this month)
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profit & Loss Report - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/vien-v3.css">

    <!-- Synto Dashboard Template Design -->
    <link rel="stylesheet" href="../assets/css/synto-design.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .pl-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .pl-card {
            background: var(--bg-secondary);
            border-radius: 12px;
            padding: 20px;
            border: 2px solid var(--border-color);
        }

        .pl-card.positive {
            border-color: #10b981;
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), var(--bg-secondary));
        }

        .pl-card.negative {
            border-color: #ef4444;
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), var(--bg-secondary));
        }

        .pl-card-label {
            font-size: 13px;
            color: var(--text-secondary);
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .pl-card-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .pl-card-value.positive {
            color: #10b981;
        }

        .pl-card-value.negative {
            color: #ef4444;
        }

        .pl-card-change {
            font-size: 14px;
            color: var(--text-secondary);
            margin-top: 8px;
        }

        .filter-section {
            background: var(--bg-secondary);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            display: flex;
            gap: 16px;
            align-items: end;
            flex-wrap: wrap;
        }

        .filter-group {
            flex: 1;
            min-width: 200px;
        }

        .filter-group label {
            display: block;
            margin-bottom: 6px;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
        }

        .filter-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            background: var(--bg-primary);
            color: var(--text-primary);
        }

        .projects-table {
            background: var(--bg-secondary);
            border-radius: 12px;
            overflow: hidden;
        }

        .projects-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .projects-table th {
            background: var(--bg-primary);
            padding: 12px 16px;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            border-bottom: 2px solid var(--border-color);
        }

        .projects-table td {
            padding: 16px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
        }

        .projects-table tr:hover {
            background: var(--bg-primary);
        }

        .profit-positive {
            color: #10b981;
            font-weight: 600;
        }

        .profit-negative {
            color: #ef4444;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include '../includes/v3-admin-sidebar.php'; ?>

        <div class="main-content">
            <?php include '../includes/v3-header.php'; ?>

            <div class="content-wrapper">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                    <h1 style="margin: 0;"><i class="fas ri-line-chart-line"></i> Profit & Loss Report</h1>
                    <button onclick="exportToCSV()" class="btn btn-primary">
                        <i class="fas ri-download-line"></i> Export CSV
                    </button>
                </div>

                <!-- Date Filter -->
                <div class="filter-section">
                    <div class="filter-group">
                        <label>Start Date</label>
                        <input type="date" id="startDate" value="<?php echo $startDate; ?>" onchange="updateReport()">
                    </div>
                    <div class="filter-group">
                        <label>End Date</label>
                        <input type="date" id="endDate" value="<?php echo $endDate; ?>" onchange="updateReport()">
                    </div>
                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <button onclick="setQuickDate('month')" class="btn btn-secondary">This Month</button>
                    </div>
                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <button onclick="setQuickDate('quarter')" class="btn btn-secondary">This Quarter</button>
                    </div>
                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <button onclick="setQuickDate('year')" class="btn btn-secondary">This Year</button>
                    </div>
                </div>

                <!-- Summary Cards -->
                <div class="pl-summary" id="summary">
                    <div class="pl-card">
                        <div class="pl-card-label">Total Revenue</div>
                        <div class="pl-card-value">₹<span id="totalRevenue">0</span></div>
                        <div class="pl-card-change"><span id="projectCount">0</span> projects</div>
                    </div>

                    <div class="pl-card">
                        <div class="pl-card-label">Total Costs</div>
                        <div class="pl-card-value">₹<span id="totalCost">0</span></div>
                        <div class="pl-card-change">Labor + Expenses</div>
                    </div>

                    <div class="pl-card" id="profitCard">
                        <div class="pl-card-label">Net Profit/Loss</div>
                        <div class="pl-card-value" id="netProfit">₹0</div>
                        <div class="pl-card-change"><span id="profitMargin">0</span>% margin</div>
                    </div>

                    <div class="pl-card">
                        <div class="pl-card-label">Payment Received</div>
                        <div class="pl-card-value profit-positive">₹<span id="totalReceived">0</span></div>
                        <div class="pl-card-change">Pending: ₹<span id="pendingPayment">0</span></div>
                    </div>
                </div>

                <!-- Projects Table -->
                <div class="projects-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Project Name</th>
                                <th>Client</th>
                                <th>Status</th>
                                <th>Revenue (₹)</th>
                                <th>Cost (₹)</th>
                                <th>Profit/Loss (₹)</th>
                                <th>Margin %</th>
                                <th>Payment</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="projectsTableBody">
                            <tr>
                                <td colspan="9" style="text-align: center; padding: 40px;">
                                    <i class="fas ri-loader-2-line fa-spin"></i> Loading data...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentData = null;

        // Load report data
        function loadReportData() {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;

            fetch('../api/budget.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'get_pl_report',
                    start_date: startDate,
                    end_date: endDate
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    currentData = data;
                    updateSummary(data.summary);
                    updateProjectsTable(data.projects);
                } else {
                    alert('Error loading report: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to load report');
            });
        }

        function updateSummary(summary) {
            document.getElementById('totalRevenue').textContent = formatNumber(summary.total_revenue_inr);
            document.getElementById('totalCost').textContent = formatNumber(summary.total_cost_inr);
            document.getElementById('totalReceived').textContent = formatNumber(summary.total_received_inr);
            document.getElementById('pendingPayment').textContent = formatNumber(summary.pending_payment_inr);
            document.getElementById('projectCount').textContent = summary.project_count;

            const netProfit = summary.total_profit_inr;
            const netProfitEl = document.getElementById('netProfit');
            const profitCard = document.getElementById('profitCard');

            netProfitEl.textContent = '₹' + formatNumber(Math.abs(netProfit));
            document.getElementById('profitMargin').textContent = summary.profit_margin_percent.toFixed(2);

            if (netProfit >= 0) {
                netProfitEl.classList.add('positive');
                netProfitEl.classList.remove('negative');
                profitCard.classList.add('positive');
                profitCard.classList.remove('negative');
            } else {
                netProfitEl.classList.add('negative');
                netProfitEl.classList.remove('positive');
                profitCard.classList.add('negative');
                profitCard.classList.remove('positive');
                netProfitEl.textContent = '-₹' + formatNumber(Math.abs(netProfit));
            }
        }

        function updateProjectsTable(projects) {
            const tbody = document.getElementById('projectsTableBody');

            if (projects.length === 0) {
                tbody.innerHTML = '<tr><td colspan="9" style="text-align: center; padding: 40px; color: var(--text-secondary);">No projects found for this period</td></tr>';
                return;
            }

            tbody.innerHTML = projects.map(project => {
                const revenue = project.client_billing_inr || 0;
                const cost = project.calculated_cost || 0;
                const profit = project.profit_loss || 0;
                const margin = revenue > 0 ? ((profit / revenue) * 100).toFixed(2) : 0;

                const profitClass = profit >= 0 ? 'profit-positive' : 'profit-negative';
                const profitDisplay = profit >= 0 ? `₹${formatNumber(profit)}` : `-₹${formatNumber(Math.abs(profit))}`;

                return `
                    <tr>
                        <td><strong>${escapeHtml(project.project_name)}</strong></td>
                        <td>${escapeHtml(project.client_name || 'N/A')}</td>
                        <td><span class="badge status-${project.status}">${project.status}</span></td>
                        <td>₹${formatNumber(revenue)}</td>
                        <td>₹${formatNumber(cost)}</td>
                        <td class="${profitClass}">${profitDisplay}</td>
                        <td class="${profitClass}">${margin}%</td>
                        <td>
                            <span class="badge ${getPaymentStatusClass(project.payment_status)}">
                                ${project.payment_status || 'pending'}
                            </span>
                            <div style="font-size: 12px; margin-top: 4px;">
                                ₹${formatNumber(project.payment_received_inr || 0)} received
                            </div>
                        </td>
                        <td>
                            <a href="project-budget.php?id=${project.id}" class="btn btn-sm btn-secondary">
                                <i class="fas ri-edit-line"></i> Manage
                            </a>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        function updateReport() {
            loadReportData();
        }

        function setQuickDate(period) {
            const today = new Date();
            let startDate, endDate;

            if (period === 'month') {
                startDate = new Date(today.getFullYear(), today.getMonth(), 1);
                endDate = new Date(today.getFullYear(), today.getMonth() + 1, 0);
            } else if (period === 'quarter') {
                const quarter = Math.floor(today.getMonth() / 3);
                startDate = new Date(today.getFullYear(), quarter * 3, 1);
                endDate = new Date(today.getFullYear(), (quarter + 1) * 3, 0);
            } else if (period === 'year') {
                startDate = new Date(today.getFullYear(), 0, 1);
                endDate = new Date(today.getFullYear(), 11, 31);
            }

            document.getElementById('startDate').value = formatDate(startDate);
            document.getElementById('endDate').value = formatDate(endDate);
            updateReport();
        }

        function exportToCSV() {
            if (!currentData) {
                alert('No data to export');
                return;
            }

            let csv = 'Project Name,Client,Status,Revenue (INR),Cost (INR),Profit/Loss (INR),Margin %,Payment Status,Payment Received (INR)\n';

            currentData.projects.forEach(project => {
                const revenue = project.client_billing_inr || 0;
                const cost = project.calculated_cost || 0;
                const profit = project.profit_loss || 0;
                const margin = revenue > 0 ? ((profit / revenue) * 100).toFixed(2) : 0;

                csv += `"${project.project_name}","${project.client_name || 'N/A'}","${project.status}",${revenue},${cost},${profit},${margin},"${project.payment_status || 'pending'}",${project.payment_received_inr || 0}\n`;
            });

            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `profit-loss-report-${document.getElementById('startDate').value}-to-${document.getElementById('endDate').value}.csv`;
            a.click();
        }

        function formatNumber(num) {
            return new Intl.NumberFormat('en-IN').format(num);
        }

        function formatDate(date) {
            return date.toISOString().split('T')[0];
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function getPaymentStatusClass(status) {
            const classes = {
                'paid': 'status-completed',
                'partial': 'status-progress',
                'pending': 'status-todo',
                'overdue': 'status-blocked'
            };
            return classes[status] || 'status-todo';
        }

        // Load data on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadReportData();
        });
    </script>
    <script src="../assets/js/theme.js"></script>

    <!-- Synto Dashboard Interactions -->
    <script src="../assets/js/synto-interactions.js"></script>
</body>
</html>
