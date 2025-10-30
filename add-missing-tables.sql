-- ============================================================
-- ADD MISSING TABLES FOR BULK DELETE & ACTIVITY LOG
-- Run this in phpMyAdmin to add essential missing tables
-- Safe to run - uses IF NOT EXISTS
-- ============================================================

-- 1. Create activity_log table for audit trail
CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT NOT NULL,
    description TEXT,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Activity audit trail for admin';

-- 2. Create comments table (or use existing task_comments - check first!)
-- If you already have a 'task_comments' table, you can skip this
CREATE TABLE IF NOT EXISTS comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    user_id INT NOT NULL,
    comment_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_task (task_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Task comments';

-- 3. Create task_attachments table if not exists
CREATE TABLE IF NOT EXISTS task_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    uploaded_by INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT,
    file_type VARCHAR(100),
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_task (task_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Task file attachments';

-- 4. Add missing columns to notifications table (if not exist)
-- Note: These will fail with "Duplicate column" if they already exist - that's OK!

ALTER TABLE notifications ADD COLUMN task_id INT NULL;
ALTER TABLE notifications ADD COLUMN project_id INT NULL;

-- Add indexes for the new columns
ALTER TABLE notifications ADD INDEX idx_task (task_id);
ALTER TABLE notifications ADD INDEX idx_project (project_id);

-- ============================================================
-- VERIFICATION QUERIES (Run these to check if tables exist)
-- Copy these one at a time to verify
-- ============================================================

-- Check if activity_log exists:
-- SHOW TABLES LIKE 'activity_log';

-- Check if comments exists:
-- SHOW TABLES LIKE 'comments';

-- Check if task_attachments exists:
-- SHOW TABLES LIKE 'task_attachments';

-- Check notifications columns:
-- SHOW COLUMNS FROM notifications LIKE '%task%';
-- SHOW COLUMNS FROM notifications LIKE '%project%';

-- ============================================================
-- DONE!
-- ============================================================
