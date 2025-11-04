<?php
/**
 * Advanced Productivity Features Setup Script
 * Pomodoro, Time Boxing, Goals, Dependencies, etc.
 */

require_once 'config/config.php';

echo "🎯 Setting up Advanced Productivity Features...\n\n";

try {
    $db = getDBConnection();

    // 1. Task Dependencies
    echo "📊 Creating task_dependencies table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS task_dependencies (
            id INT AUTO_INCREMENT PRIMARY KEY,
            task_id INT NOT NULL,
            depends_on_task_id INT NOT NULL,
            dependency_type ENUM('finish_to_start', 'start_to_start', 'finish_to_finish') DEFAULT 'finish_to_start',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
            FOREIGN KEY (depends_on_task_id) REFERENCES tasks(id) ON DELETE CASCADE,
            UNIQUE KEY unique_dependency (task_id, depends_on_task_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "  ✓ task_dependencies table created\n";

    // 2. Goals
    echo "\n🎯 Creating goals table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS goals (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            goal_title VARCHAR(255) NOT NULL,
            goal_description TEXT,
            goal_type ENUM('daily', 'weekly', 'monthly', 'quarterly', 'yearly', 'long_term') NOT NULL,
            target_value DECIMAL(10,2),
            current_value DECIMAL(10,2) DEFAULT 0,
            unit VARCHAR(50),
            start_date DATE NOT NULL,
            target_date DATE NOT NULL,
            status ENUM('active', 'completed', 'cancelled', 'on_hold') DEFAULT 'active',
            priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
            related_project_id INT,
            completed_date DATE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (related_project_id) REFERENCES projects(id) ON DELETE SET NULL,
            INDEX idx_user (user_id),
            INDEX idx_type (goal_type),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "  ✓ goals table created\n";

    // 3. Daily Plans
    echo "\n📅 Creating daily_plans table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS daily_plans (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            plan_date DATE NOT NULL,
            morning_intention TEXT,
            top_3_priorities TEXT,
            time_blocks TEXT COMMENT 'JSON array of time blocks',
            completed TINYINT(1) DEFAULT 0,
            reflection_notes TEXT,
            energy_level INT DEFAULT 5 COMMENT '1-10 scale',
            mood VARCHAR(50),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_user_date (user_id, plan_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "  ✓ daily_plans table created\n";

    // 4. Morning Ritual Checklist
    echo "\n☀️ Creating morning_rituals table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS morning_rituals (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            ritual_name VARCHAR(255) NOT NULL,
            ritual_order INT DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "  ✓ morning_rituals table created\n";

    // 5. Morning Ritual Completions
    echo "📝 Creating morning_ritual_completions table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS morning_ritual_completions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            ritual_id INT NOT NULL,
            completion_date DATE NOT NULL,
            completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (ritual_id) REFERENCES morning_rituals(id) ON DELETE CASCADE,
            UNIQUE KEY unique_completion (user_id, ritual_id, completion_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "  ✓ morning_ritual_completions table created\n";

    // 6. Weekly Reviews
    echo "\n📊 Creating weekly_reviews table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS weekly_reviews (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            week_start_date DATE NOT NULL,
            week_end_date DATE NOT NULL,
            wins TEXT COMMENT 'Weekly wins',
            challenges TEXT COMMENT 'Weekly challenges',
            learnings TEXT COMMENT 'What I learned',
            improvements TEXT COMMENT 'Areas to improve',
            next_week_focus TEXT COMMENT 'Focus for next week',
            energy_rating INT COMMENT '1-10 scale',
            satisfaction_rating INT COMMENT '1-10 scale',
            completed_tasks INT DEFAULT 0,
            total_hours DECIMAL(10,2) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_user_week (user_id, week_start_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "  ✓ weekly_reviews table created\n";

    // 7. Time Boxes (for time boxing feature)
    echo "\n⏰ Creating time_boxes table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS time_boxes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            task_id INT,
            box_date DATE NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            status ENUM('planned', 'in_progress', 'completed', 'missed') DEFAULT 'planned',
            actual_start_time DATETIME,
            actual_end_time DATETIME,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
            INDEX idx_user_date (user_id, box_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "  ✓ time_boxes table created\n";

    // 8. Pomodoro Sessions
    echo "\n🍅 Creating pomodoro_sessions table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS pomodoro_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            task_id INT,
            session_type ENUM('work', 'short_break', 'long_break') NOT NULL,
            duration_minutes INT NOT NULL,
            start_time DATETIME NOT NULL,
            end_time DATETIME,
            completed TINYINT(1) DEFAULT 0,
            interrupted TINYINT(1) DEFAULT 0,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE SET NULL,
            INDEX idx_user (user_id),
            INDEX idx_date (start_time)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "  ✓ pomodoro_sessions table created\n";

    // 9. Break Reminders Settings
    echo "\n⏸️ Creating break_reminder_settings table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS break_reminder_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            enabled TINYINT(1) DEFAULT 1,
            work_duration_minutes INT DEFAULT 50,
            break_duration_minutes INT DEFAULT 10,
            remind_before_minutes INT DEFAULT 5,
            notification_sound TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "  ✓ break_reminder_settings table created\n";

    // 10. Add Focus Mode preference to users
    echo "\n🎯 Adding focus mode settings to users...\n";
    $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'focus_mode_enabled'");
    if ($stmt->rowCount() == 0) {
        $db->exec("
            ALTER TABLE users
            ADD COLUMN focus_mode_enabled TINYINT(1) DEFAULT 0,
            ADD COLUMN pomodoro_duration INT DEFAULT 25 COMMENT 'Pomodoro work duration in minutes',
            ADD COLUMN short_break_duration INT DEFAULT 5 COMMENT 'Short break duration in minutes',
            ADD COLUMN long_break_duration INT DEFAULT 15 COMMENT 'Long break duration in minutes',
            ADD COLUMN pomodoros_until_long_break INT DEFAULT 4 COMMENT 'Number of pomodoros before long break'
        ");
        echo "  ✓ Focus mode settings added to users\n";
    } else {
        echo "  ✓ Focus mode settings already exist\n";
    }

    // Insert default morning rituals for existing users
    echo "\n☀️ Adding default morning rituals...\n";
    $db->exec("
        INSERT IGNORE INTO break_reminder_settings (user_id, enabled, work_duration_minutes, break_duration_minutes)
        SELECT id, 1, 50, 10
        FROM users
        WHERE role = 'employee'
    ");
    echo "  ✓ Default break reminders initialized\n";

    echo "\n✅ Advanced productivity features setup complete!\n";
    echo "\n📝 Features enabled:\n";
    echo "  • ✅ Pomodoro Timer (25-min work sessions)\n";
    echo "  • ✅ Time Boxing (schedule time slots)\n";
    echo "  • ✅ Daily Planning (morning routine)\n";
    echo "  • ✅ Task Dependencies (link related tasks)\n";
    echo "  • ✅ Break Reminders (automatic notifications)\n";
    echo "  • ✅ Weekly Review (end-of-week reflection)\n";
    echo "  • ✅ Goal Setting (short & long-term goals)\n";
    echo "  • ✅ Morning Ritual (daily checklist)\n";
    echo "  • ✅ Focus Mode (distraction-free work)\n";
    echo "\n🎉 Your productivity platform is now feature-complete!\n";

} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
