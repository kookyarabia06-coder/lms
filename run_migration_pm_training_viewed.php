<?php
require_once __DIR__ . '/inc/config.php';

try {
    // Add viewed_at column to pm_training_requests if it doesn't exist
    $pdo->exec("ALTER TABLE `pm_training_requests` 
    ADD COLUMN `viewed_at` DATETIME NULL DEFAULT NULL AFTER `updated_at`");
    
    // Add index for better query performance
    $pdo->exec("ALTER TABLE `pm_training_requests` 
    ADD INDEX `idx_viewed_at` (`viewed_at`)");
    
    echo "Migration successful! viewed_at column added to pm_training_requests table.";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "Column already exists. No action needed.";
    } else if (strpos($e->getMessage(), 'Duplicate key') !== false) {
        echo "Index already exists. No action needed.";
    } else {
        echo "Error: " . $e->getMessage();
    }
}
?>
