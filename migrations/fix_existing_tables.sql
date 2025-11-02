-- Fix script for existing database with table structure issues
-- Run this FIRST if you get "Unknown column" errors

-- Option 1: If user_points table exists but has wrong columns, drop and recreate
DROP TABLE IF EXISTS user_badges;
DROP TABLE IF EXISTS user_points;

-- Option 2: If you want to preserve existing data, manually alter the table
-- Uncomment and run these instead of the DROP statements above:
-- ALTER TABLE user_points ADD COLUMN IF NOT EXISTS points INT DEFAULT 0 AFTER user_id;
-- ALTER TABLE user_points ADD COLUMN IF NOT EXISTS level INT DEFAULT 1 AFTER points;
-- ALTER TABLE user_points ADD COLUMN IF NOT EXISTS total_tasks_completed INT DEFAULT 0 AFTER level;
-- ALTER TABLE user_points ADD COLUMN IF NOT EXISTS current_streak INT DEFAULT 0 AFTER total_tasks_completed;
-- ALTER TABLE user_points ADD COLUMN IF NOT EXISTS longest_streak INT DEFAULT 0 AFTER current_streak;
-- ALTER TABLE user_points ADD COLUMN IF NOT EXISTS last_activity_date DATE AFTER longest_streak;

-- Now you can safely run: final_production_features.sql
-- The CREATE TABLE IF NOT EXISTS statements will create the tables with correct structure
