-- Agency-Specific Features Schema
-- Client Feedback, Budget System, Shoot Schedule
-- For Neofox Media

-- ==================== CLIENT FEEDBACK SYSTEM ====================

-- Client feedback entries (PM adds feedback from clients)
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

-- Feedback responses/comments
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

-- ==================== BUDGET SYSTEM ====================

-- Project budget tracking
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

-- Project expenses
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

-- ==================== SHOOT SCHEDULE SYSTEM ====================

-- Calendar events for shoots, edits, reviews, etc.
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

-- Event type colors (default color scheme)
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

-- ==================== ADDITIONAL ENHANCEMENTS ====================

-- Add budget fields to projects table if they don't exist
ALTER TABLE projects
ADD COLUMN IF NOT EXISTS total_budget DECIMAL(10,2) DEFAULT 0,
ADD COLUMN IF NOT EXISTS budget_currency VARCHAR(3) DEFAULT 'USD';

-- Add hourly rate to users table if it doesn't exist
ALTER TABLE users
ADD COLUMN IF NOT EXISTS hourly_rate DECIMAL(10,2) DEFAULT 0;
