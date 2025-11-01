-- ========================================
-- Add DateTime Support for Due Dates
-- ========================================
-- This migration updates date columns to datetime to support time tracking
-- Run this in phpMyAdmin or your MySQL client

-- Update projects table: Change date columns to datetime
-- This preserves existing dates and sets time to 00:00:00
ALTER TABLE projects
    MODIFY COLUMN start_date DATETIME NULL DEFAULT NULL,
    MODIFY COLUMN due_date DATETIME NULL DEFAULT NULL;

-- Update tasks table: Change date columns to datetime
-- This preserves existing dates and sets time to 00:00:00
ALTER TABLE tasks
    MODIFY COLUMN start_date DATETIME NULL DEFAULT NULL,
    MODIFY COLUMN due_date DATETIME NULL DEFAULT NULL;

-- Note: Existing date values will be preserved and time will default to 00:00:00
-- Example: '2024-12-25' becomes '2024-12-25 00:00:00'

-- ========================================
-- Verification Queries (Optional - Run to verify)
-- ========================================

-- Check projects table structure
-- DESCRIBE projects;

-- Check tasks table structure
-- DESCRIBE tasks;

-- View sample data to verify conversion
-- SELECT id, project_name, start_date, due_date FROM projects LIMIT 5;
-- SELECT id, task_name, start_date, due_date FROM tasks LIMIT 5;
