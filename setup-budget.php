<?php
/**
 * Budget Management Setup Script
 * Adds budget tracking fields to projects table (Admin only visibility)
 */

require_once 'config/config.php';

echo "💰 Setting up Budget Management System...\n\n";

try {
    $db = getDBConnection();

    // Check if budget fields already exist
    $stmt = $db->query("SHOW COLUMNS FROM projects LIKE 'budget_inr'");
    if ($stmt->rowCount() > 0) {
        echo "✓ Budget fields already exist.\n";
        exit(0);
    }

    echo "📊 Adding budget fields to projects table...\n";

    // Add budget fields to projects
    $db->exec("
        ALTER TABLE projects
        ADD COLUMN budget_inr DECIMAL(12,2) DEFAULT 0 COMMENT 'Project budget in INR (Admin only)',
        ADD COLUMN actual_cost_inr DECIMAL(12,2) DEFAULT 0 COMMENT 'Actual cost in INR (Admin only)',
        ADD COLUMN hourly_rate_inr DECIMAL(10,2) DEFAULT 0 COMMENT 'Default hourly rate for project (Admin only)',
        ADD COLUMN additional_costs_inr DECIMAL(12,2) DEFAULT 0 COMMENT 'Additional costs like software, tools (Admin only)',
        ADD COLUMN profit_margin_percent DECIMAL(5,2) DEFAULT 0 COMMENT 'Expected profit margin % (Admin only)',
        ADD COLUMN client_billing_inr DECIMAL(12,2) DEFAULT 0 COMMENT 'Amount billed to client (Admin only)',
        ADD COLUMN payment_received_inr DECIMAL(12,2) DEFAULT 0 COMMENT 'Payment received from client (Admin only)',
        ADD COLUMN payment_status ENUM('pending', 'partial', 'paid', 'overdue') DEFAULT 'pending' COMMENT 'Payment status (Admin only)',
        ADD COLUMN notes_admin_only TEXT COMMENT 'Admin-only notes about budget/costs'
    ");

    echo "  ✓ Budget fields added to projects table\n";

    // Create project expenses table for detailed tracking
    echo "\n📝 Creating project_expenses table...\n";

    $db->exec("
        CREATE TABLE IF NOT EXISTS project_expenses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            project_id INT NOT NULL,
            expense_type ENUM('labor', 'software', 'hardware', 'marketing', 'other') NOT NULL,
            description VARCHAR(255) NOT NULL,
            amount_inr DECIMAL(10,2) NOT NULL,
            expense_date DATE NOT NULL,
            receipt_file VARCHAR(255),
            added_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
            FOREIGN KEY (added_by) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_project (project_id),
            INDEX idx_date (expense_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    echo "  ✓ project_expenses table created\n";

    // Add hourly rates to users table
    echo "\n👥 Adding hourly rates to users table...\n";

    $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'hourly_rate_inr'");
    if ($stmt->rowCount() == 0) {
        $db->exec("
            ALTER TABLE users
            ADD COLUMN hourly_rate_inr DECIMAL(10,2) DEFAULT 0 COMMENT 'Employee hourly rate (Admin only)'
        ");
        echo "  ✓ Hourly rate field added to users\n";
    } else {
        echo "  ✓ Hourly rate field already exists\n";
    }

    echo "\n✅ Budget management system setup complete!\n";
    echo "\n📝 Features enabled:\n";
    echo "  • Project budgets in INR\n";
    echo "  • Automatic cost calculation based on time logs\n";
    echo "  • Expense tracking\n";
    echo "  • Profit & Loss reporting\n";
    echo "  • Client billing tracking\n";
    echo "  • Payment status monitoring\n";
    echo "  • Admin-only visibility\n";
    echo "\n🔒 Security: All budget data visible only to admin users\n";

} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
