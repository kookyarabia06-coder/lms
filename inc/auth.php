<?php
// inc/auth.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php'; // ensure helpers like current_user() exist

// Redirect to login if no user is logged in
function require_login() {
    if (!current_user()) {
        header('Location: ../public/login.php');
        exit;
    }
}

// Optional: Redirect logged-in user away from login/register pages
function redirect_if_logged_in() {
    if (current_user()) {
        header('Location: ../public/dashboard.php');
        exit;
    }
}

// Example: Check role access
function require_admin() {
    require_login();
    if (!is_admin()) {
        die('Access denied: Admins only.');
    }
}

function require_proponent() {
    require_login();
    if (!is_proponent()) {
        die('Access denied: Proponents only.');
    }
}


function require_superadmin() {
    require_login();
    if (!is_superadmin()) {
        die('Access denied: Superadmin only.');
    }
}