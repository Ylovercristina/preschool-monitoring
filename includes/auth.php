<?php
/**
 * Authentication Middleware & Helpers
 * Preschool Monitoring System
 */

require_once dirname(__DIR__) . '/config/config.php';

function checkAuth($roles = []) {
    requireAuth($roles);
}

function getActiveEmergencyAlert() {
    static $alert = null;
    if ($alert === null) {
        $db = getDB();
        $stmt = $db->query("SELECT * FROM emergency_alerts WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
        $alert = $stmt->fetch() ?: false;
    }
    return $alert;
}

function getUnreadNotificationsCount($userId) {
    if (!$userId) return 0;
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

function getPendingApprovalsCount() {
    $db = getDB();
    $stmt = $db->query("SELECT COUNT(*) FROM users WHERE role = 'parent' AND status = 'pending_approval'");
    return (int)$stmt->fetchColumn();
}

function getUnreadMessagesCount($userId) {
    if (!$userId) return 0;
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}
