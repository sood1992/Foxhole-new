-- Productivity Management Platform Database Schema
-- For Neofox Marketing Agency

-- Drop tables if they exist (for clean setup)
DROP TABLE IF EXISTS time_logs;
DROP TABLE IF EXISTS task_comments;
DROP TABLE IF EXISTS project_comments;
DROP TABLE IF EXISTS tasks;
DROP TABLE IF EXISTS projects;
DROP TABLE IF EXISTS users;

-- Users table with role-based access
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'manager', 'employee') NOT NULL DEFAULT 'employee',
    job_title VARCHAR(100),
    avatar VARCHAR(255),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    INDEX idx_role (role),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Projects table
CREATE TABLE projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_name VARCHAR(200) NOT NULL,
    description TEXT,
    client_name VARCHAR(100),
    status ENUM('planning', 'in_progress', 'review', 'completed', 'on_hold') DEFAULT 'planning',
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    start_date DATE,
    due_date DATE,
    completed_date DATE NULL,
    estimated_hours DECIMAL(10,2),
    actual_hours DECIMAL(10,2) DEFAULT 0,
    created_by INT NOT NULL,
    assigned_manager INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_manager) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_due_date (due_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tasks table
CREATE TABLE tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    task_name VARCHAR(200) NOT NULL,
    description TEXT,
    assigned_to INT,
    status ENUM('todo', 'in_progress', 'review', 'completed', 'blocked') DEFAULT 'todo',
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    estimated_hours DECIMAL(10,2),
    actual_hours DECIMAL(10,2) DEFAULT 0,
    start_date DATE,
    due_date DATE,
    completed_date DATE NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_assigned (assigned_to),
    INDEX idx_project (project_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Time logs table for detailed time tracking
CREATE TABLE time_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    task_id INT NOT NULL,
    project_id INT NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME NULL,
    duration_minutes INT DEFAULT 0,
    notes TEXT,
    is_active TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_task (task_id),
    INDEX idx_active (is_active),
    INDEX idx_date (start_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Project comments table
CREATE TABLE project_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    user_id INT NOT NULL,
    comment TEXT NOT NULL,
    is_update TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_project (project_id),
    INDEX idx_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Task comments table
CREATE TABLE task_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    user_id INT NOT NULL,
    comment TEXT NOT NULL,
    is_update TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_task (task_id),
    INDEX idx_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default admin user (password: admin123)
INSERT INTO users (username, email, password, full_name, role, job_title) VALUES
('admin', 'admin@neofox.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin User', 'admin', 'CEO'),
('john_manager', 'john@neofox.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'John Doe', 'manager', 'Project Manager'),
('sarah_employee', 'sarah@neofox.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Sarah Smith', 'employee', 'Video Editor');

-- Insert sample projects
INSERT INTO projects (project_name, description, client_name, status, priority, start_date, due_date, estimated_hours, created_by, assigned_manager) VALUES
('Brand Campaign 2024', 'Complete brand refresh campaign for TechCorp', 'TechCorp', 'in_progress', 'high', '2024-01-01', '2024-03-31', 200, 1, 2),
('Social Media Management', 'Monthly social media content creation and management', 'StartupXYZ', 'in_progress', 'medium', '2024-01-15', '2024-02-15', 80, 1, 2),
('Website Redesign', 'Complete website overhaul with new branding', 'LocalBiz', 'planning', 'urgent', '2024-02-01', '2024-04-30', 300, 1, 2);

-- Insert sample tasks
INSERT INTO tasks (project_id, task_name, description, assigned_to, status, priority, estimated_hours, start_date, due_date, created_by) VALUES
(1, 'Logo Design Concepts', 'Create 5 logo design concepts for client review', 3, 'in_progress', 'high', 15, '2024-01-01', '2024-01-10', 2),
(1, 'Brand Guidelines Document', 'Develop comprehensive brand guidelines', 3, 'todo', 'medium', 20, '2024-01-11', '2024-01-25', 2),
(2, 'Instagram Content Calendar', 'Create 30-day content calendar for Instagram', 3, 'completed', 'medium', 10, '2024-01-15', '2024-01-20', 2),
(2, 'Video Editing - Promo', 'Edit promotional video for product launch', 3, 'in_progress', 'high', 8, '2024-01-21', '2024-01-28', 2);
