<?php
/**
 * Gamification System Setup Script
 * Run this once to initialize gamification tables and data
 */

require_once 'config/config.php';

echo "🎮 Setting up Gamification System...\n\n";

try {
    $db = getDBConnection();

    // Check if tables already exist
    $stmt = $db->query("SHOW TABLES LIKE 'user_points'");
    if ($stmt->rowCount() > 0) {
        echo "✓ Gamification tables already exist.\n";
        echo "  Would you like to reset them? This will delete all existing points and badges.\n";
        echo "  Type 'yes' to reset, or anything else to skip: ";
        $handle = fopen("php://stdin", "r");
        $line = trim(fgets($handle));
        fclose($handle);

        if (strtolower($line) !== 'yes') {
            echo "\n✓ Skipping setup. Gamification system is already initialized.\n";
            exit(0);
        }

        echo "\n⚠️  Dropping existing tables...\n";
        $db->exec("DROP TABLE IF EXISTS point_transactions");
        $db->exec("DROP TABLE IF EXISTS user_badges");
        $db->exec("DROP TABLE IF EXISTS performance_metrics");
        $db->exec("DROP TABLE IF EXISTS badges");
        $db->exec("DROP TABLE IF EXISTS user_points");
        echo "✓ Tables dropped.\n\n";
    }

    // Create tables
    echo "📊 Creating gamification tables...\n";

    // User Points and Stats
    $db->exec("
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "  ✓ user_points table created\n";

    // Badges/Achievements
    $db->exec("
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "  ✓ badges table created\n";

    // User Badges (earned)
    $db->exec("
        CREATE TABLE IF NOT EXISTS user_badges (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            badge_id INT NOT NULL,
            earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (badge_id) REFERENCES badges(id) ON DELETE CASCADE,
            UNIQUE KEY (user_id, badge_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "  ✓ user_badges table created\n";

    // Point History/Transactions
    $db->exec("
        CREATE TABLE IF NOT EXISTS point_transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            points INT NOT NULL,
            reason VARCHAR(255),
            task_id INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "  ✓ point_transactions table created\n";

    // Performance Metrics (for analytics)
    $db->exec("
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "  ✓ performance_metrics table created\n";

    echo "\n🏆 Inserting default badges...\n";

    // Insert default badges
    $badges = [
        ['First Steps', 'Complete your first task', '🎯', 'bronze', 'tasks_completed', 1, 0],
        ['Getting Started', 'Complete 5 tasks', '⭐', 'bronze', 'tasks_completed', 5, 50],
        ['Task Master', 'Complete 25 tasks', '🏅', 'silver', 'tasks_completed', 25, 250],
        ['Productivity King', 'Complete 100 tasks', '👑', 'gold', 'tasks_completed', 100, 1000],
        ['Speed Demon', 'Complete 5 tasks early', '⚡', 'silver', 'early_completion', 5, 100],
        ['Early Bird', 'Complete 20 tasks before deadline', '🎁', 'gold', 'early_completion', 20, 500],
        ['Streak Master', 'Maintain 7 day streak', '🔥', 'silver', 'streak', 7, 200],
        ['Consistency Champion', 'Maintain 30 day streak', '💎', 'platinum', 'streak', 30, 1000],
        ['Quality Guru', 'Maintain high quality work', '✨', 'gold', 'quality', 90, 500],
        ['Lightning Fast', 'Complete tasks at high speed', '⚡', 'platinum', 'speed', 95, 800]
    ];

    $stmt = $db->prepare("
        INSERT INTO badges (name, description, icon, type, criteria_type, criteria_value, points_required)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($badges as $badge) {
        $stmt->execute($badge);
        echo "  ✓ {$badge[0]}\n";
    }

    echo "\n👥 Initializing points for existing users...\n";

    // Initialize points for all existing users
    $db->exec("
        INSERT IGNORE INTO user_points (user_id, total_points, streak_days, last_activity_date)
        SELECT id, 0, 0, CURDATE()
        FROM users
        WHERE id NOT IN (SELECT user_id FROM user_points)
    ");

    $stmt = $db->query("SELECT COUNT(*) as count FROM user_points");
    $count = $stmt->fetch()['count'];
    echo "  ✓ Initialized {$count} users\n";

    echo "\n✅ Gamification system setup complete!\n";
    echo "\n📝 Features enabled:\n";
    echo "  • Points system with automatic awards\n";
    echo "  • 10 achievement badges\n";
    echo "  • Streak tracking\n";
    echo "  • Leaderboard\n";
    echo "  • Productivity scoring\n";
    echo "  • Eisenhower Matrix for task prioritization\n";
    echo "\n🎉 Users can now earn points and badges by completing tasks!\n";

} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
