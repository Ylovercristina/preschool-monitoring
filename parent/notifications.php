<?php
/**
 * Notifications & Emergency Alerts Center (Parent View)
 * Preschool Monitoring System
 * Fulfills:
 * - "receive notifications: As a parent, I want to be notified when their is an event in school."
 * - "Emergency Alert: As a parent, I want to receive emergency alerts, so that I can quickly know
 *    if there is an emergency involving my child at school."
 */

$pageTitle = 'Notifications & Safety Advisories';
$pageSubtitle = 'Real-time school alerts, activity announcements, fee notices and child safety updates';

require_once dirname(__DIR__) . '/includes/auth.php';
checkAuth(['parent']);

$db = getDB();
$parentId = $_SESSION['user_id'];

// Handle Mark All Read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_all_read') {
    verifyCsrf();
    $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$parentId]);
    setFlash('info', 'All notifications marked as read.');
    header('Location: notifications.php');
    exit;
}

// Fetch Active Emergency Alerts
$activeEmergencies = $db->query("SELECT * FROM emergency_alerts WHERE is_active = 1 ORDER BY id DESC")->fetchAll();

// Fetch Notifications
$stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY is_read ASC, created_at DESC");
$stmt->execute([$parentId]);
$notifications = $stmt->fetchAll();

require_once dirname(__DIR__) . '/includes/header.php';
?>

<!-- Action Bar -->
<div class="d-flex justify-between align-center" style="margin-bottom: 24px; flex-wrap: wrap; gap: 14px;">
    <div style="font-size: 0.95rem; color: var(--text-secondary);">
        Stay updated with your child's preschool events, health reminders and immediate safety alerts.
    </div>
    <?php if (!empty($notifications)): ?>
        <form method="POST" action="notifications.php">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" value="mark_all_read">
            <button type="submit" class="btn btn-secondary btn-sm">
                ✓ Mark All as Read
            </button>
        </form>
    <?php endif; ?>
</div>

<!-- Active Emergency Advisories Section -->
<?php if (!empty($activeEmergencies)): ?>
    <div class="card" style="border: 2px solid var(--rose); background: #FFF1F2;">
        <div class="card-header" style="border-bottom-color: #FECDD3;">
            <h3 class="card-title" style="color: var(--rose-dark);">
                <span>🚨</span> Active Emergency Advisories & Warnings
            </h3>
            <span class="badge badge-danger">Immediate Attention</span>
        </div>

        <div style="display: flex; flex-direction: column; gap: 12px;">
            <?php foreach ($activeEmergencies as $em): ?>
                <div style="background: #FFFFFF; border-left: 4px solid var(--rose); padding: 16px; border-radius: var(--radius-md);">
                    <div class="d-flex justify-between align-center" style="margin-bottom: 4px;">
                        <strong style="color: var(--rose-dark); font-size: 1.1rem;"><?= htmlspecialchars($em['title']) ?></strong>
                        <span class="badge badge-danger"><?= strtoupper($em['severity']) ?></span>
                    </div>
                    <p style="margin: 0; color: #1E293B; font-size: 0.95rem; line-height: 1.5;">
                        <?= nl2br(htmlspecialchars($em['message'])) ?>
                    </p>
                    <small style="color: var(--text-muted); display: block; margin-top: 8px;">
                        Broadcasted: <?= formatDate($em['created_at'], 'M d, Y h:i A') ?>
                    </small>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Notifications List -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <span>🔔</span> Notification Inbox
            <span class="badge badge-primary"><?= count($notifications) ?> Total</span>
        </h3>
    </div>

    <?php if (empty($notifications)): ?>
        <div style="text-align: center; padding: 40px; color: var(--text-muted);">
            <div style="font-size: 2.5rem; margin-bottom: 8px;">📭</div>
            <p>You have no notifications at this time.</p>
        </div>
    <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 12px;">
            <?php foreach ($notifications as $n): 
                $icon = '📌';
                $badgeType = 'badge-primary';
                if ($n['type'] === 'emergency') { $icon = '🚨'; $badgeType = 'badge-danger'; }
                elseif ($n['type'] === 'event') { $icon = '🎈'; $badgeType = 'badge-info'; }
                elseif ($n['type'] === 'fee_reminder') { $icon = '💰'; $badgeType = 'badge-warning'; }
                elseif ($n['type'] === 'pickup') { $icon = '🛡️'; $badgeType = 'badge-success'; }
                elseif ($n['type'] === 'milestone') { $icon = '⭐'; $badgeType = 'badge-mint'; }
            ?>
                <div style="
                    padding: 16px;
                    border-radius: var(--radius-md);
                    background: <?= $n['is_read'] ? '#FFFFFF' : 'var(--primary-light)' ?>;
                    border: 1px solid <?= $n['is_read'] ? 'var(--border-color)' : '#C7D2FE' ?>;
                    display: flex;
                    align-items: flex-start;
                    gap: 14px;
                    box-shadow: var(--shadow-sm);
                ">
                    <div style="font-size: 1.5rem; line-height: 1;"><?= $icon ?></div>
                    <div style="flex: 1;">
                        <div class="d-flex justify-between align-center" style="margin-bottom: 4px;">
                            <strong style="color: var(--text-primary); font-size: 0.95rem;"><?= htmlspecialchars($n['title']) ?></strong>
                            <span class="badge <?= $badgeType ?>"><?= strtoupper(str_replace('_', ' ', $n['type'])) ?></span>
                        </div>
                        <p style="margin: 0; color: var(--text-secondary); font-size: 0.9rem; line-height: 1.5;">
                            <?= htmlspecialchars($n['message']) ?>
                        </p>
                        <div class="d-flex justify-between align-center" style="margin-top: 8px; font-size: 0.76rem; color: var(--text-muted);">
                            <span><?= formatDate($n['created_at'], 'M d, Y h:i A') ?></span>
                            <?php if ($n['link']): ?>
                                <a href="<?= url($n['link']) ?>" class="fw-bold text-primary">View Details &rarr;</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
