<?php
require_once 'config/config.php';

$db = getDBConnection();

// Read migration SQL
$sql = file_get_contents(__DIR__ . '/migrations/001_add_password_resets.sql');

try {
    // Execute migration
    $db->exec($sql);
    echo "Migration applied successfully!\n";
    echo "password_resets table created.\n";
} catch (PDOException $e) {
    // Check if table already exists
    if (strpos($e->getMessage(), 'already exists') !== false) {
        echo "Migration already applied. Table password_resets exists.\n";
    } else {
        echo "Migration error: " . $e->getMessage() . "\n";
        exit(1);
    }
}
?>
