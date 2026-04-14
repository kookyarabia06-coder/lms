<?php
require_once __DIR__ . '/inc/config.php';

try {
    $pdo = $pdo; // Use existing PDO connection
    
    // Alter the status enum to include 'complete'
    $sql = "ALTER TABLE `pm_training_requests` MODIFY `status` enum('pending','approved','rejected','complete') DEFAULT 'pending'";
    
    $pdo->exec($sql);
    
    echo "✓ Successfully updated pm_training_requests table!\n";
    echo "✓ Added 'complete' status to enum\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
