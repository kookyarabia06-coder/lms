<?php
require_once __DIR__ . '/inc/config.php';

try {
    $pdo = $pdo; // Use existing PDO connection
    
    // Read SQL file
    $sql = file_get_contents(__DIR__ . '/pm_training_migration.sql');
    
    // Split SQL statements by semicolon
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($s) { return !empty($s) && strpos($s, '--') !== 0; }
    );
    
    foreach ($statements as $statement) {
        if (trim($statement)) {
            $pdo->exec($statement);
        }
    }
    
    echo "✓ PM Training Request database tables created successfully!\n";
    echo "✓ Tables created:\n";
    echo "  - pm_training_requests\n";
    echo "  - pm_training_attendance\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
