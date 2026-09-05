<?php
/**
 * Application Core Configuration & Helper Utilities
 * Preschool Monitoring System
 * Group Members: Rhysa A. Caruz, Cristine Joy B. Jaojao, Xyrha Viel Sacal
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('APP_NAME', 'Play-Is-School');
define('APP_SUBTITLE', 'Early Childhood Learning & Safety Hub');
define('APP_VERSION', '1.0.0');

// Group members for credit & requirements alignment
define('TEAM_MEMBERS', [
    'Rhysa A. Caruz',
    'Cristine Joy B. Jaojao',
    'Xyrha Viel Sacal'
]);

require_once __DIR__ . '/database.php';

// Base URL helper
function getBaseUrl() {
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $dir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
    
    // Normalize root path whether in root or nested
    $parts = explode('/', trim($dir, '/'));
    if (in_array(end($parts), ['admin', 'teacher', 'parent', 'auth', 'config'])) {
        array_pop($parts);
    }
    $base = '/' . implode('/', $parts);
    return rtrim($base, '/');
}

define('BASE_URL', getBaseUrl());

function url($path = '') {
    $path = ltrim($path, '/');
    $base = BASE_URL;
    return $base ? "{$base}/{$path}" : "/{$path}";
}

// Security & CSRF
function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf() {
    $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        die('Invalid CSRF token submission.');
    }
}

// User & Auth Helpers
function isLoggedIn() {
    return !empty($_SESSION['user_id']);
}

function currentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    return $_SESSION['user'] ?? null;
}

function userRole() {
    return $_SESSION['user']['role'] ?? null;
}

function hasRole($role) {
    return userRole() === $role;
}

function requireAuth($allowedRoles = []) {
    if (!isLoggedIn()) {
        setFlash('warning', 'Please login to access this area.');
        header('Location: ' . url('auth/login.php'));
        exit;
    }

    if (!empty($allowedRoles)) {
        if (is_string($allowedRoles)) {
            $allowedRoles = [$allowedRoles];
        }
        if (!in_array(userRole(), $allowedRoles)) {
            setFlash('danger', 'Unauthorized access! You do not have permission to view that page.');
            $dashboard = userRole() ? userRole() . '/index.php' : 'auth/login.php';
            header('Location: ' . url($dashboard));
            exit;
        }
    }
}

// Flash notifications
function setFlash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type, // success, danger, warning, info
        'message' => $message
    ];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// Sanitization
function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(trim($input ?? ''), ENT_QUOTES, 'UTF-8');
}

// Activity Logging (Admin requirement: system activity logs)
function logActivity($action, $details = '', $userId = null) {
    try {
        $db = getDB();
        if (!$userId && isLoggedIn()) {
            $userId = $_SESSION['user_id'];
        }
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, datetime('now'))");
        $stmt->execute([$userId, $action, $details, $ip]);
    } catch (Exception $e) {
        // Silently log or ignore
    }
}

// Currency formatting (PHP / standard currency)
function formatMoney($amount) {
    return '₱' . number_format((float)$amount, 2);
}

// Date formatting
function formatDate($date, $format = 'M d, Y') {
    if (!$date) return 'N/A';
    return date($format, strtotime($date));
}

function formatTime($time) {
    if (!$time) return 'N/A';
    return date('h:i A', strtotime($time));
}
