<?php
session_start();

// Database connection
$host = 'localhost';
$db   = 'lms_db';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Dynamically define BASE_URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' 
            || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
define('BASE_URL', $protocol . $_SERVER['HTTP_HOST'] . '/lms');

// Include functions
require_once __DIR__ . '/functions.php';
//function checkAndUpdateSchema($pdo) {
   // try {
        // Check if columns exist
     //   $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'is_verified'");
      //  if (!$stmt->fetch()) {
            // Add missing columns
    //        $pdo->exec("ALTER TABLE users 
                 //      ADD COLUMN is_verified TINYINT(1) DEFAULT 0,
                  //     ADD COLUMN otp_code VARCHAR(6),
                   //    ADD COLUMN otp_expires_at DATETIME");
     //   }
  //  } catch (PDOException $e) {
        // Table might not exist or error, we'll handle it later
 //   }
//}

// Run schema check
//checkAndUpdateSchema($pdo);


// mail mail and pass are for debugg
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'learningmanagement576@gmail.com');
define('SMTP_PASSWORD', 'ahkv dpsl urcn lbmr');
define('SMTP_FROM_EMAIL', 'learningmanagement576@gmail.com');
define('SMTP_FROM_NAME', 'LMS');



?>



