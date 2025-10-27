-- Gamification System
-- Points, Badges, Achievements, Leaderboards

-- User Points and Stats
CREATE TABLE IF NOT EXISTS user_points (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    total_points INT DEFAULT 0,
    streak_days INT DEFAULT 0,
    last_activity_date DATE,
    tasks_completed_early INT DEFAULT 0,
    tasks_completed_on_time INT DEFAULT 0,
    tasks_completed_late INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY (user_id)
);

-- Badges/Achievements
CREATE TABLE IF NOT EXISTS badges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    icon VARCHAR(50),
    points_required INT DEFAULT 0,
    type ENUM('bronze', 'silver', 'gold', 'platinum', 'special') DEFAULT 'bronze',
    criteria_type ENUM('tasks_completed', 'streak', 'early_completion', 'speed', 'quality') NOT NULL,
    criteria_value INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- User Badges (earned)
CREATE TABLE IF NOT EXISTS user_badges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    badge_id INT NOT NULL,
    earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (badge_id) REFERENCES badges(id) ON DELETE CASCADE,
    UNIQUE KEY (user_id, badge_id)
);

-- Point History/Transactions
CREATE TABLE IF NOT EXISTS point_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    points INT NOT NULL,
    reason VARCHAR(255),
    task_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE SET NULL
);

-- Performance Metrics (for analytics)
CREATE TABLE IF NOT EXISTS performance_metrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    date DATE NOT NULL,
    tasks_completed INT DEFAULT 0,
    hours_worked DECIMAL(10,2) DEFAULT 0,
    avg_task_completion_time DECIMAL(10,2),
    productivity_score DECIMAL(5,2),
    quality_score DECIMAL(5,2),
    speed_score DECIMAL(5,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY (user_id, date)
);

-- Insert default badges
INSERT INTO badges (name, description, icon, type, criteria_type, criteria_value, points_required) VALUES
('First Steps', 'Complete your first task', '🎯', 'bronze', 'tasks_completed', 1, 0),
('Getting Started', 'Complete 5 tasks', '⭐', 'bronze', 'tasks_completed', 5, 50),
('Task Master', 'Complete 25 tasks', '🏅', 'silver', 'tasks_completed', 25, 250),
('Productivity King', 'Complete 100 tasks', '👑', 'gold', 'tasks_completed', 100, 1000),
('Speed Demon', 'Complete 5 tasks early', '⚡', 'silver', 'early_completion', 5, 100),
('Early Bird', 'Complete 20 tasks before deadline', '🎁', 'gold', 'early_completion', 20, 500),
('Streak Master', 'Maintain 7 day streak', '🔥', 'silver', 'streak', 7, 200),
('Consistency Champion', 'Maintain 30 day streak', '💎', 'platinum', 'streak', 30, 1000),
('Quality Guru', 'Maintain high quality work', '✨', 'gold', 'quality', 90, 500),
('Lightning Fast', 'Complete tasks at high speed', '⚡', 'platinum', 'speed', 95, 800);

-- Initialize points for existing users
INSERT INTO user_points (user_id, total_points, streak_days, last_activity_date)
SELECT id, 0, 0, CURDATE()
FROM users
WHERE id NOT IN (SELECT user_id FROM user_points);
