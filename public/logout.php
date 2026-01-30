<?php
require_once __DIR__ . '/../inc/config.php';

session_start();

// Unset all session variables
$_SESSION = [];

// Destroy the session
session_destroy();

// Delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Redirect to login page
header('Location: ' . BASE_URL . '/public/index.php');
exit;
