-- ============================================================
-- FINAL DATABASE MIGRATION - FOXHOLE AGENCY FEATURES
-- MySQL 5.5+ Compatible
-- Run this entire file in phpMyAdmin
-- ============================================================

-- If you get "Duplicate column" or "Table already exists" errors,
-- that's OK! Just continue - it means those parts are already done.

-- ============================================================
-- SECTION 1: CLIENT FEEDBACK SYSTEM
-- ============================================================

-- Create client_feedback table
CREATE TABLE IF NOT EXISTS client_feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    task_id INT NULL,
    feedback_title VARCHAR(200) NOT NULL,
    feedback_text TEXT NOT NULL,
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    assigned_to INT NULL,
    added_by INT NOT NULL,
    due_date DATE NULL,
    completed_date DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (added_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_project (project_id),
    INDEX idx_status (status),
    INDEX idx_assigned (assigned_to)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create feedback_responses table
CREATE TABLE IF NOT EXISTS feedback_responses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    feedback_id INT NOT NULL,
    user_id INT NOT NULL,
    response_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (feedback_id) REFERENCES client_feedback(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_feedback (feedback_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- SECTION 2: BUDGET SYSTEM
-- ============================================================

-- Create project_budgets table
CREATE TABLE IF NOT EXISTS project_budgets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL UNIQUE,
    total_budget DECIMAL(10,2) NOT NULL DEFAULT 0,
    labor_budget DECIMAL(10,2) DEFAULT 0,
    equipment_budget DECIMAL(10,2) DEFAULT 0,
    materials_budget DECIMAL(10,2) DEFAULT 0,
    other_budget DECIMAL(10,2) DEFAULT 0,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    INDEX idx_project (project_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create project_expenses table
CREATE TABLE IF NOT EXISTS project_expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    expense_category ENUM('equipment', 'travel', 'materials', 'stock_footage', 'music_licensing', 'talent', 'location', 'other') NOT NULL,
    expense_title VARCHAR(200) NOT NULL,
    description TEXT,
    amount DECIMAL(10,2) NOT NULL,
    expense_date DATE NOT NULL,
    receipt_file VARCHAR(255),
    added_by INT NOT NULL,
    approved_by INT NULL,
    approval_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (added_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_project (project_id),
    INDEX idx_category (expense_category),
    INDEX idx_status (approval_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- SECTION 3: CALENDAR EVENTS (Shoots, Edits, Reviews)
-- ============================================================

-- Create calendar_events table
CREATE TABLE IF NOT EXISTS calendar_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NULL,
    task_id INT NULL,
    event_type ENUM('shoot', 'edit', 'review', 'meeting', 'deadline', 'delivery', 'other') NOT NULL DEFAULT 'other',
    event_title VARCHAR(200) NOT NULL,
    description TEXT,
    location VARCHAR(255),
    start_datetime DATETIME NOT NULL,
    end_datetime DATETIME NOT NULL,
    all_day TINYINT(1) DEFAULT 0,
    color VARCHAR(20) DEFAULT '#1990ff',
    assigned_users TEXT COMMENT 'Comma-separated user IDs',
    equipment_needed TEXT,
    notes TEXT,
    status ENUM('scheduled', 'in_progress', 'completed', 'cancelled') DEFAULT 'scheduled',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_project (project_id),
    INDEX idx_type (event_type),
    INDEX idx_start (start_datetime),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create event_type_colors table
CREATE TABLE IF NOT EXISTS event_type_colors (
    event_type VARCHAR(50) PRIMARY KEY,
    color VARCHAR(20) NOT NULL,
    icon VARCHAR(50),
    description VARCHAR(200)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default event type colors
INSERT INTO event_type_colors (event_type, color, icon, description) VALUES
('shoot', '#FF6B6B', 'fa-video', 'Video/Photo Shoot'),
('edit', '#4ECDC4', 'fa-cut', 'Editing Session'),
('review', '#FFE66D', 'fa-eye', 'Client Review'),
('meeting', '#95E1D3', 'fa-users', 'Team Meeting'),
('deadline', '#F38181', 'fa-flag', 'Project Deadline'),
('delivery', '#AA96DA', 'fa-truck', 'Deliverable Due'),
('other', '#CCCCCC', 'fa-calendar', 'Other Event')
ON DUPLICATE KEY UPDATE color=VALUES(color), icon=VALUES(icon), description=VALUES(description);

-- ============================================================
-- SECTION 4: DELIVERABLES & REVISIONS
-- ============================================================

-- Create project_deliverables table
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

-- Create deliverable_versions table
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

-- Create billable_hours_summary table
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

-- Create budget_history table
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
-- SECTION 5: ADD COLUMNS TO EXISTING TABLES
-- Note: If you get "Duplicate column" errors, that's OK!
-- Just means those columns already exist. Continue to next statement.
-- ============================================================

-- Add columns to client_feedback table (run each separately)
-- Copy and run ONE AT A TIME. Skip if you get "Duplicate column" error.

ALTER TABLE client_feedback ADD COLUMN revision_count INT DEFAULT 0;
ALTER TABLE client_feedback ADD COLUMN deliverable_file VARCHAR(500) NULL;
ALTER TABLE client_feedback ADD COLUMN client_approval_status ENUM('pending', 'approved', 'revision_requested') DEFAULT 'pending';
ALTER TABLE client_feedback ADD COLUMN approval_date DATETIME NULL;

-- Add columns to project_budgets table
-- Copy and run ONE AT A TIME. Skip if you get "Duplicate column" error.

ALTER TABLE project_budgets ADD COLUMN billing_type ENUM('fixed', 'hourly', 'mixed') DEFAULT 'fixed';
ALTER TABLE project_budgets ADD COLUMN estimated_hours DECIMAL(10,2) DEFAULT 0;
ALTER TABLE project_budgets ADD COLUMN hourly_rate DECIMAL(10,2) DEFAULT 0;
ALTER TABLE project_budgets ADD COLUMN markup_percentage DECIMAL(5,2) DEFAULT 20;
ALTER TABLE project_budgets ADD COLUMN client_quote DECIMAL(10,2) DEFAULT 0;
ALTER TABLE project_budgets ADD COLUMN internal_cost DECIMAL(10,2) DEFAULT 0;
ALTER TABLE project_budgets ADD COLUMN projected_profit DECIMAL(10,2) DEFAULT 0;

-- Add hourly_rate to users table
-- Skip if you get "Duplicate column" error.

ALTER TABLE users ADD COLUMN hourly_rate DECIMAL(10,2) DEFAULT 50;

-- Add budget columns to projects table
-- Skip if you get "Duplicate column" error.

ALTER TABLE projects ADD COLUMN total_budget DECIMAL(10,2) DEFAULT 0;
ALTER TABLE projects ADD COLUMN budget_currency VARCHAR(3) DEFAULT 'USD';

-- ============================================================
-- SECTION 6: CREATE VIEWS FOR REPORTING
-- These are safe to run multiple times
-- ============================================================

-- Drop views if they exist (to recreate them fresh)
DROP VIEW IF EXISTS v_project_budget_overview;
DROP VIEW IF EXISTS v_feedback_with_revisions;

-- View: Project Budget Overview with Calculations
CREATE VIEW v_project_budget_overview AS
SELECT
    p.id as project_id,
    p.project_name,
    pb.total_budget,
    pb.billing_type,
    pb.estimated_hours,
    pb.hourly_rate as project_hourly_rate,
    pb.markup_percentage,
    pb.client_quote,
    COALESCE(SUM(tl.duration_minutes * u.hourly_rate / 60), 0) as actual_labor_cost,
    COALESCE((SELECT SUM(amount) FROM project_expenses pe WHERE pe.project_id = p.id AND pe.approval_status = 'approved'), 0) as total_expenses,
    COALESCE(SUM(tl.duration_minutes * u.hourly_rate / 60), 0) + COALESCE((SELECT SUM(amount) FROM project_expenses pe WHERE pe.project_id = p.id AND pe.approval_status = 'approved'), 0) as total_spent,
    pb.total_budget - (COALESCE(SUM(tl.duration_minutes * u.hourly_rate / 60), 0) + COALESCE((SELECT SUM(amount) FROM project_expenses pe WHERE pe.project_id = p.id AND pe.approval_status = 'approved'), 0)) as remaining_budget,
    CASE WHEN pb.client_quote > 0 THEN pb.client_quote - (COALESCE(SUM(tl.duration_minutes * u.hourly_rate / 60), 0) + COALESCE((SELECT SUM(amount) FROM project_expenses pe WHERE pe.project_id = p.id AND pe.approval_status = 'approved'), 0)) ELSE 0 END as projected_profit,
    CASE WHEN pb.client_quote > 0 THEN ROUND(((pb.client_quote - (COALESCE(SUM(tl.duration_minutes * u.hourly_rate / 60), 0) + COALESCE((SELECT SUM(amount) FROM project_expenses pe WHERE pe.project_id = p.id AND pe.approval_status = 'approved'), 0))) / pb.client_quote) * 100, 2) ELSE 0 END as actual_profit_margin_percent,
    COALESCE(SUM(tl.duration_minutes) / 60, 0) as total_hours_logged,
    pb.created_at,
    pb.updated_at
FROM projects p
LEFT JOIN project_budgets pb ON p.id = pb.project_id
LEFT JOIN time_logs tl ON p.id = tl.project_id AND tl.end_time IS NOT NULL
LEFT JOIN users u ON tl.user_id = u.id
GROUP BY p.id, pb.id;

-- View: Feedback with Revision Count
CREATE VIEW v_feedback_with_revisions AS
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

-- ============================================================
-- MIGRATION COMPLETE!
-- ============================================================
--
-- What was created:
-- ✅ 12 new tables for agency features
-- ✅ Enhanced columns for budgets and users
-- ✅ 2 views for easy reporting
--
-- Next steps:
-- 1. Refresh your Master Dashboard
-- 2. Start using Client Feedback, Budget, and Calendar features
-- 3. Set hourly rates for your team members
--
-- ============================================================
