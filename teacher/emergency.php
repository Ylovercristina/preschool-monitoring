<?php
/**
 * Emergency Alert Broadcast System
 * Preschool Monitoring System
 * Fulfills: "Emergency Alert: As a teacher, I want to inform parents/guardian
 * if there is an emergency occuring in the school."
 */

$pageTitle = 'Emergency Alert Broadcast System';
$pageSubtitle = 'Broadcast instantaneous emergency safety advisories and notifications to all parents and administration';

require_once dirname(__DIR__) . '/includes/auth.php';
checkAuth(['teacher', 'admin']);

$db = getDB();
$userId = $_SESSION['user_id'];

// Handle Emergency Broadcast Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'broadcast') {
        $title = trim($_POST['title'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $severity = $_POST['severity'] ?? 'urgent';

        if (empty($title) || empty($message)) {
            setFlash('danger', 'Alert title and message body are mandatory for an emergency broadcast.');
        } else {
            // Deactivate existing alerts if new one is broadcasted
            $db->exec("UPDATE emergency_alerts SET is_active = 0");

            // Insert new alert
            $stmt = $db->prepare("INSERT INTO emergency_alerts (title, message, severity, posted_by, created_at, is_active) VALUES (?, ?, ?, ?, datetime('now'), 1)");
            $stmt->execute([$title, $message, $severity, $userId]);
            $alertId = $db->lastInsertId();

            // Broadcast notification to ALL active users (Parents, Teachers, Admins)
            $allUsers = $db->query("SELECT id FROM users WHERE status = 'active'")->fetchAll();
            $notifStmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, 'emergency', 'parent/notifications.php')");
            foreach ($allUsers as $u) {
                $notifStmt->execute([
                    $u['id'],
                    "EMERGENCY ADVISORY: {$title}",
                    $message
                ]);
            }

            logActivity('Emergency Alert Broadcasted', "BROADCASTED EMERGENCY ALERT: '{$title}' ({$severity})", $userId);
            setFlash('danger', "EMERGENCY BROADCAST POSTED! Instant safety notifications sent to all parents and staff.");
            header('Location: emergency.php');
            exit;
        }
    } elseif ($action === 'resolve') {
        $id = (int)$_POST['id'];
        $db->prepare("UPDATE emergency_alerts SET is_active = 0 WHERE id = ?")->execute([$id]);
        logActivity('Emergency Alert Resolved', "Resolved/deactivated emergency alert ID {$id}", $userId);
        setFlash('success', 'Emergency alert has been resolved and deactivated.');
        header('Location: emergency.php');
        exit;
    }
}

// Fetch Alerts
$alerts = $db->query("
    SELECT a.*, u.name as posted_by_name, u.role as posted_by_role 
    FROM emergency_alerts a 
    LEFT JOIN users u ON a.posted_by = u.id 
    ORDER BY a.is_active DESC, a.created_at DESC
")->fetchAll();

require_once dirname(__DIR__) . '/includes/header.php';
?>

<!-- Broadcast Composer Card -->
<div class="card" style="border: 2px solid var(--rose); background: #FFF1F2;">
    <div class="card-header" style="border-bottom-color: #FECDD3;">
        <h3 class="card-title" style="color: var(--rose-dark);">
            <span>🚨</span> Broadcast Immediate School Emergency Alert
        </h3>
        <span class="badge badge-danger">High Priority Dispatch</span>
    </div>

    <form method="POST" action="emergency.php">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="broadcast">

        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 16px;">
            <div class="form-group">
                <label class="form-label" for="alertTitle" style="color: #9F1239;">Emergency Alert Headline *</label>
                <input type="text" name="title" id="alertTitle" class="form-control" placeholder="e.g. Inclement Weather Alert / Early Dismissal at 11:00 AM" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="alertSeverity" style="color: #9F1239;">Urgency Level *</label>
                <select name="severity" id="alertSeverity" class="form-select">
                    <option value="urgent">Urgent Notice (Weather / Early Dismissal)</option>
                    <option value="critical">Critical Emergency (Immediate Action Required)</option>
                    <option value="warning">Health / Precautionary Advisory</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label" for="alertMessage" style="color: #9F1239;">Detailed Alert Instructions for Parents *</label>
            <textarea name="message" id="alertMessage" class="form-control" rows="3" placeholder="Provide clear instructions: e.g. Signal No. 1 has been announced. All preschool classes dismiss at 11:00 AM. Please dispatch authorized pickup guardians with security PINs." required></textarea>
        </div>

        <div class="d-flex justify-between align-center" style="margin-top: 14px;">
            <small style="color: #881337;">
                ⚠️ This will display an emergency top ticker on all parent dashboards and send immediate notifications.
            </small>
            <button type="submit" class="btn btn-danger btn-lg" onclick="return confirm('Broadcast this emergency alert to ALL parents immediately?');">
                🚨 Broadcast Alert to All Parents Now
            </button>
        </div>
    </form>
</div>

<!-- Emergency Broadcast History Card -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <span>📋</span> Emergency Broadcast Logs & Active Advisories
        </h3>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Status</th>
                    <th>Severity</th>
                    <th>Alert Title</th>
                    <th>Broadcast Message</th>
                    <th>Posted By</th>
                    <th>Date & Time</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($alerts)): ?>
                    <tr><td colspan="7" style="text-align: center; padding: 24px; color: var(--text-muted);">No emergency alerts have been issued.</td></tr>
                <?php else: ?>
                    <?php foreach ($alerts as $al): ?>
                        <tr style="<?= $al['is_active'] ? 'background: #FFF5F6;' : '' ?>">
                            <td>
                                <?php if ($al['is_active']): ?>
                                    <span class="badge badge-danger">LIVE ACTIVE</span>
                                <?php else: ?>
                                    <span class="badge badge-neutral">RESOLVED</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= $al['severity'] === 'critical' ? 'badge-danger' : ($al['severity'] === 'urgent' ? 'badge-warning' : 'badge-info') ?>">
                                    <?= strtoupper($al['severity']) ?>
                                </span>
                            </td>
                            <td>
                                <strong style="color: var(--text-primary);"><?= htmlspecialchars($al['title']) ?></strong>
                            </td>
                            <td>
                                <div style="font-size: 0.86rem; color: var(--text-secondary); max-width: 380px;">
                                    <?= htmlspecialchars($al['message']) ?>
                                </div>
                            </td>
                            <td>
                                <?= htmlspecialchars($al['posted_by_name'] ?? 'Staff') ?><br>
                                <small style="color: var(--text-muted);"><?= strtoupper($al['posted_by_role'] ?? 'TEACHER') ?></small>
                            </td>
                            <td>
                                <?= formatDate($al['created_at']) ?><br>
                                <small style="color: var(--text-muted);"><?= date('h:i A', strtotime($al['created_at'])) ?></small>
                            </td>
                            <td>
                                <?php if ($al['is_active']): ?>
                                    <form method="POST" action="emergency.php" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                        <input type="hidden" name="action" value="resolve">
                                        <input type="hidden" name="id" value="<?= $al['id'] ?>">
                                        <button type="submit" class="btn btn-secondary btn-sm" onclick="return confirm('Mark this emergency alert as resolved?');">
                                            ✓ Deactivate
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span style="color: var(--text-muted); font-size: 0.8rem;">Archived</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
