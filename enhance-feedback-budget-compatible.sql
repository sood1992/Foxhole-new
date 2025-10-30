-- ============================================================
-- ENHANCED FEEDBACK & BUDGET SYSTEM (MySQL Compatible)
-- PM enters client feedback, tracks revisions, manages budget
-- ============================================================

-- Add revision tracking to client_feedback table (one column at a time)
ALTER TABLE client_feedback ADD COLUMN revision_count INT DEFAULT 0;
ALTER TABLE client_feedback ADD COLUMN deliverable_file VARCHAR(500) NULL COMMENT 'Link to deliverable file';
ALTER TABLE client_feedback ADD COLUMN client_approval_status ENUM('pending', 'approved', 'revision_requested') DEFAULT 'pending';
ALTER TABLE client_feedback ADD COLUMN approval_date DATETIME NULL;

-- Deliverable files uploaded by PM (what was sent to client)
CREATE TABLE IF NOT EXISTS project_deliverables (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    task_id INT NULL,
    feedback_id INT NULL COMMENT 'Link to related feedback',
    deliverable_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type ENUM('video', 'image', 'document', 'other') DEFAULT 'other',
    version_number INT DEFAULT 1,
    description TEXT,
    uploaded_by INT NOT NULL COMMENT 'PM who uploaded',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE SET NULL,
    FOREIGN KEY (feedback_id) REFERENCES client_feedback(id) ON DELETE SET NULL,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_project (project_id),
    INDEX idx_feedback (feedback_id),
    INDEX idx_version (version_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Version history for deliverables
CREATE TABLE IF NOT EXISTS deliverable_versions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    deliverable_id INT NOT NULL,
    version_number INT NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    changes_description TEXT,
    uploaded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (deliverable_id) REFERENCES project_deliverables(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_deliverable (deliverable_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- ENHANCED BUDGET SYSTEM
-- Time-based billing, Profit margins, Budget vs Actual
-- ============================================================

-- Enhance project_budgets table with billing features (one column at a time)
ALTER TABLE project_budgets ADD COLUMN billing_type ENUM('fixed', 'hourly', 'mixed') DEFAULT 'fixed';
ALTER TABLE project_budgets ADD COLUMN estimated_hours DECIMAL(10,2) DEFAULT 0;
ALTER TABLE project_budgets ADD COLUMN hourly_rate DECIMAL(10,2) DEFAULT 0 COMMENT 'Project hourly rate';
ALTER TABLE project_budgets ADD COLUMN markup_percentage DECIMAL(5,2) DEFAULT 20 COMMENT 'Profit margin %';
ALTER TABLE project_budgets ADD COLUMN client_quote DECIMAL(10,2) DEFAULT 0 COMMENT 'What client will pay';
ALTER TABLE project_budgets ADD COLUMN internal_cost DECIMAL(10,2) DEFAULT 0 COMMENT 'Our actual costs';
ALTER TABLE project_budgets ADD COLUMN projected_profit DECIMAL(10,2) DEFAULT 0;

-- Add hourly rate to users table (for labor cost calculations)
ALTER TABLE users ADD COLUMN hourly_rate DECIMAL(10,2) DEFAULT 50 COMMENT 'Employee hourly rate';

-- Billable hours tracking (auto-calculated from time_logs)
CREATE TABLE IF NOT EXISTS billable_hours_summary (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    user_id INT NOT NULL,
    total_hours DECIMAL(10,2) NOT NULL,
    hourly_rate DECIMAL(10,2) NOT NULL,
    total_cost DECIMAL(10,2) NOT NULL,
    last_calculated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_project_user (project_id, user_id),
    INDEX idx_project (project_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Budget snapshots (track changes over time)
CREATE TABLE IF NOT EXISTS budget_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    snapshot_date DATE NOT NULL,
    total_budget DECIMAL(10,2) NOT NULL,
    spent_amount DECIMAL(10,2) NOT NULL,
    labor_cost DECIMAL(10,2) NOT NULL,
    expenses_total DECIMAL(10,2) NOT NULL,
    remaining_budget DECIMAL(10,2) NOT NULL,
    profit_margin DECIMAL(5,2) NOT NULL,
    notes TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_project_date (project_id, snapshot_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- VIEWS FOR EASY REPORTING
-- ============================================================

-- View: Project Budget Overview with Calculations
CREATE OR REPLACE VIEW v_project_budget_overview AS
SELECT
    p.id as project_id,
    p.project_name,
    pb.total_budget,
    pb.billing_type,
    pb.estimated_hours,
    pb.hourly_rate as project_hourly_rate,
    pb.markup_percentage,
    pb.client_quote,

    -- Calculate labor costs from time logs
    COALESCE(SUM(tl.duration_minutes * u.hourly_rate / 60), 0) as actual_labor_cost,

    -- Calculate total expenses
    COALESCE((
        SELECT SUM(amount)
        FROM project_expenses pe
        WHERE pe.project_id = p.id AND pe.approval_status = 'approved'
    ), 0) as total_expenses,

    -- Total spent = labor + expenses
    COALESCE(SUM(tl.duration_minutes * u.hourly_rate / 60), 0) + COALESCE((
        SELECT SUM(amount)
        FROM project_expenses pe
        WHERE pe.project_id = p.id AND pe.approval_status = 'approved'
    ), 0) as total_spent,

    -- Remaining budget
    pb.total_budget - (
        COALESCE(SUM(tl.duration_minutes * u.hourly_rate / 60), 0) + COALESCE((
            SELECT SUM(amount)
            FROM project_expenses pe
            WHERE pe.project_id = p.id AND pe.approval_status = 'approved'
        ), 0)
    ) as remaining_budget,

    -- Profit calculation (if client_quote is set)
    CASE
        WHEN pb.client_quote > 0 THEN
            pb.client_quote - (
                COALESCE(SUM(tl.duration_minutes * u.hourly_rate / 60), 0) + COALESCE((
                    SELECT SUM(amount)
                    FROM project_expenses pe
                    WHERE pe.project_id = p.id AND pe.approval_status = 'approved'
                ), 0)
            )
        ELSE 0
    END as projected_profit,

    -- Profit margin percentage
    CASE
        WHEN pb.client_quote > 0 THEN
            ROUND((
                (pb.client_quote - (
                    COALESCE(SUM(tl.duration_minutes * u.hourly_rate / 60), 0) + COALESCE((
                        SELECT SUM(amount)
                        FROM project_expenses pe
                        WHERE pe.project_id = p.id AND pe.approval_status = 'approved'
                    ), 0)
                )) / pb.client_quote
            ) * 100, 2)
        ELSE 0
    END as actual_profit_margin_percent,

    -- Total hours logged
    COALESCE(SUM(tl.duration_minutes) / 60, 0) as total_hours_logged,

    pb.created_at,
    pb.updated_at

FROM projects p
LEFT JOIN project_budgets pb ON p.id = pb.project_id
LEFT JOIN time_logs tl ON p.id = tl.project_id AND tl.end_time IS NOT NULL
LEFT JOIN users u ON tl.user_id = u.id
GROUP BY p.id, pb.id;

-- View: Feedback with Revision Count
CREATE OR REPLACE VIEW v_feedback_with_revisions AS
SELECT
    cf.*,
    p.project_name,
    t.task_name,
    u_added.full_name as added_by_name,
    u_assigned.full_name as assigned_to_name,
    (SELECT COUNT(*) FROM feedback_responses WHERE feedback_id = cf.id) as response_count,
    (SELECT COUNT(*) FROM project_deliverables WHERE feedback_id = cf.id) as deliverable_count
FROM client_feedback cf
JOIN projects p ON cf.project_id = p.id
LEFT JOIN tasks t ON cf.task_id = t.id
JOIN users u_added ON cf.added_by = u_added.id
LEFT JOIN users u_assigned ON cf.assigned_to = u_assigned.id;
