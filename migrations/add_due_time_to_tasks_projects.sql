-- Add due time support to tasks and projects
-- Convert due_date from DATE to DATETIME to store both date and time

-- Backup note: This migration converts DATE columns to DATETIME
-- Existing dates will be preserved with 00:00:00 time

-- Update tasks table
ALTER TABLE tasks
MODIFY COLUMN due_date DATETIME NULL DEFAULT NULL
COMMENT 'Due date and time for task completion';

-- Update projects table
ALTER TABLE projects
MODIFY COLUMN due_date DATETIME NULL DEFAULT NULL
COMMENT 'Project deadline date and time';

-- Add indexes for due date queries
-- Note: If these indexes already exist, you'll get an error - that's okay, just skip this part
CREATE INDEX idx_tasks_due_date ON tasks(due_date);
CREATE INDEX idx_projects_due_date ON projects(due_date);
