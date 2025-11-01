# Database Migrations

This directory contains SQL migration files for database schema updates.

## How to Run Migrations

### Option 1: Using MySQL Command Line
```bash
mysql -u your_username -p your_database_name < migrations/add_project_managers_table.sql
```

### Option 2: Using phpMyAdmin
1. Log in to phpMyAdmin
2. Select your database
3. Go to the SQL tab
4. Copy and paste the contents of the migration file
5. Click "Go" to execute

### Option 3: Using PHP Script
```php
<?php
require_once 'config/config.php';
$db = getDBConnection();
$sql = file_get_contents('migrations/add_project_managers_table.sql');
$db->exec($sql);
echo 'Migration completed successfully!';
?>
```

## Migration Files

### add_project_managers_table.sql
- **Purpose**: Adds multi-manager support to projects
- **What it does**:
  - Creates `project_managers` junction table
  - Migrates existing `assigned_manager` data to the new table
  - Establishes many-to-many relationship between projects and managers
- **Required for**: Multi-manager project assignment feature
- **Breaking change**: No (keeps backward compatibility with `assigned_manager` column)

## Important Notes

1. Always backup your database before running migrations
2. Migrations are designed to be idempotent (safe to run multiple times)
3. The `assigned_manager` column is kept for backward compatibility
4. Existing project assignments will be automatically migrated to the new system
