-- Migration: Add support for multiple project managers
-- This allows projects to have multiple managers assigned

-- Create project_managers junction table
CREATE TABLE IF NOT EXISTS project_managers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    manager_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    assigned_by INT,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_project_manager (project_id, manager_id),
    INDEX idx_project (project_id),
    INDEX idx_manager (manager_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Migrate existing assigned_manager data to project_managers table
INSERT INTO project_managers (project_id, manager_id, assigned_by)
SELECT id, assigned_manager, created_by
FROM projects
WHERE assigned_manager IS NOT NULL
ON DUPLICATE KEY UPDATE project_id = project_id;

-- Note: We're keeping the assigned_manager column for backward compatibility
-- It will store the primary/creator manager
