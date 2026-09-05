<?php
/**
 * Logout Handler
 * Preschool Monitoring System
 * Fulfills logout requirement for Admin, Teacher, and Parent
 */

require_once dirname(__DIR__) . '/config/config.php';

if (isLoggedIn()) {
    $user = currentUser();
    logActivity('User Logout', "User {$user['name']} logged out", $user['id']);
}

$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

session_start();
setFlash('info', 'You have been logged out safely.');
header("Location: login.php");
exit;
