-- ==================== ADDITIONAL ENHANCEMENTS ====================
-- Add budget fields to projects table if they don't exist

-- MySQL compatible approach using stored procedures
DELIMITER $$

-- Add total_budget column if it doesn't exist
DROP PROCEDURE IF EXISTS add_total_budget_column$$
CREATE PROCEDURE add_total_budget_column()
BEGIN
    IF NOT EXISTS (
        SELECT * FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'projects'
        AND COLUMN_NAME = 'total_budget'
    ) THEN
        ALTER TABLE projects ADD COLUMN total_budget DECIMAL(10,2) DEFAULT 0;
    END IF;
END$$

-- Add budget_currency column if it doesn't exist
DROP PROCEDURE IF EXISTS add_budget_currency_column$$
CREATE PROCEDURE add_budget_currency_column()
BEGIN
    IF NOT EXISTS (
        SELECT * FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'projects'
        AND COLUMN_NAME = 'budget_currency'
    ) THEN
        ALTER TABLE projects ADD COLUMN budget_currency VARCHAR(3) DEFAULT 'USD';
    END IF;
END$$

DELIMITER ;

-- Execute the procedures
CALL add_total_budget_column();
CALL add_budget_currency_column();

-- Clean up
DROP PROCEDURE IF EXISTS add_total_budget_column;
DROP PROCEDURE IF EXISTS add_budget_currency_column;
