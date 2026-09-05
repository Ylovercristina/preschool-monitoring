<?php
/**
 * Application Gateway Entrypoint
 * Preschool Monitoring System
 */

require_once __DIR__ . '/config/config.php';

if (isLoggedIn()) {
    $role = userRole();
    if ($role === 'admin') {
        header('Location: ' . url('admin/index.php'));
        exit;
    } elseif ($role === 'teacher') {
        header('Location: ' . url('teacher/index.php'));
        exit;
    } elseif ($role === 'parent') {
        header('Location: ' . url('parent/index.php'));
        exit;
    }
}

header('Location: ' . url('auth/login.php'));
exit;
