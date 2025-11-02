-- STEP-BY-STEP MIGRATION FOR DUE TIME SUPPORT
-- Run each section separately if you encounter errors

-- STEP 1: Update tasks table (safe - preserves existing dates)
ALTER TABLE tasks
MODIFY COLUMN due_date DATETIME NULL DEFAULT NULL
COMMENT 'Due date and time for task completion';

-- STEP 2: Update projects table (safe - preserves existing dates)
ALTER TABLE projects
MODIFY COLUMN due_date DATETIME NULL DEFAULT NULL
COMMENT 'Project deadline date and time';

-- STEP 3: Add index for tasks due_date (skip if you get "Duplicate key name" error)
-- Uncomment the line below and run:
-- CREATE INDEX idx_tasks_due_date ON tasks(due_date);

-- STEP 4: Add index for projects due_date (skip if you get "Duplicate key name" error)
-- Uncomment the line below and run:
-- CREATE INDEX idx_projects_due_date ON projects(due_date);

-- VERIFICATION: Check that columns are now DATETIME
-- Run these queries to verify:
-- SHOW COLUMNS FROM tasks LIKE 'due_date';
-- SHOW COLUMNS FROM projects LIKE 'due_date';
-- You should see Type: datetime (not date)
