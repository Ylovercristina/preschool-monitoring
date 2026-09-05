<?php
/**
 * System Activity Logs (Audit Trail)
 * Preschool Monitoring System
 * Fulfills: "system activity logs: As an admin, I want to manage parent and teacher accounts,
 * system activity logs, report generation (students, fee)"
 */

$pageTitle = 'System Activity Logs';
$pageSubtitle = 'Immutable audit trail of authentication events, payments, admissions and security actions';

require_once dirname(__DIR__) . '/includes/auth.php';
checkAuth(['admin']);

$db = getDB();

// Clear Logs action if requested
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    if (($_POST['action'] ?? '') === 'clear_old') {
        $db->query("DELETE FROM activity_logs WHERE created_at < datetime('now', '-30 days')");
        logActivity('Logs Maintained', 'Admin pruned activity logs older than 30 days');
        setFlash('success', 'Logs older than 30 days have been cleared.');
        header('Location: logs.php');
        exit;
    }
}

// Fetch Logs
$logs = $db->query("
    SELECT l.*, u.name as user_name, u.role as user_role, u.email as user_email
    FROM activity_logs l
    LEFT JOIN users u ON l.user_id = u.id
    ORDER BY l.id DESC LIMIT 100
")->fetchAll();

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="d-flex justify-between align-center" style="margin-bottom: 24px; flex-wrap: wrap; gap: 14px;">
    <div style="max-width: 450px; width: 100%;">
        <input type="text" class="form-control" data-table-search="logsTable" placeholder="🔍 Search logs by user, action, IP or details...">
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-secondary btn-sm" onclick="triggerPrint()">
            🖨️ Print Audit Trail
        </button>
        <form method="POST" action="logs.php" onsubmit="return confirm('Purge logs older than 30 days?');" style="display:inline;">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" value="clear_old">
            <button type="submit" class="btn btn-danger btn-sm">Clear Logs &gt; 30 Days</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <span>📜</span> System Activity Audit Log
            <span class="badge badge-primary"><?= count($logs) ?> Events</span>
        </h3>
    </div>

    <div class="table-responsive">
        <table class="table" id="logsTable">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>User & Role</th>
                    <th>Action Performed</th>
                    <th>Action Details</th>
                    <th>IP Address</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr><td colspan="5" style="text-align: center; padding: 24px; color: var(--text-muted);">No activity logs found.</td></tr>
                <?php else: ?>
                    <?php foreach ($logs as $l): ?>
                        <tr>
                            <td>
                                <strong><?= formatDate($l['created_at'], 'M d, Y') ?></strong><br>
                                <small style="color: var(--text-muted);"><?= date('h:i:s A', strtotime($l['created_at'])) ?></small>
                            </td>
                            <td>
                                <?php if ($l['user_name']): ?>
                                    <strong><?= htmlspecialchars($l['user_name']) ?></strong><br>
                                    <span class="badge <?= $l['user_role'] === 'admin' ? 'badge-danger' : ($l['user_role'] === 'teacher' ? 'badge-primary' : 'badge-warning') ?>" style="font-size: 0.65rem;">
                                        <?= strtoupper($l['user_role'] ?? 'GUEST') ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: var(--text-muted); font-style: italic;">System / Guest</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong style="color: var(--primary);"><?= htmlspecialchars($l['action']) ?></strong>
                            </td>
                            <td>
                                <div style="font-size: 0.88rem; color: var(--text-secondary); max-width: 450px;">
                                    <?= htmlspecialchars($l['details']) ?>
                                </div>
                            </td>
                            <td>
                                <code style="background: var(--bg-card-subtle); padding: 2px 6px; border-radius: 4px; font-size: 0.78rem;">
                                    <?= htmlspecialchars($l['ip_address'] ?? '127.0.0.1') ?>
                                </code>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
