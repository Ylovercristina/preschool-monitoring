<?php
/**
 * Parent Account Approvals Center
 * Preschool Monitoring System
 * Fulfills: "approve parent accounts: As an admin, I want to view and manage approval for user"
 */

$pageTitle = 'Parent Account Approvals';
$pageSubtitle = 'Review, verify identity & approve pending parent account registrations';

require_once dirname(__DIR__) . '/includes/auth.php';
checkAuth(['admin']);

$db = getDB();

// Handle Approval / Rejection Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    $userId = (int)($_POST['user_id'] ?? 0);

    if ($userId > 0) {
        $stmt = $db->prepare("SELECT name, email FROM users WHERE id = ? AND role = 'parent'");
        $stmt->execute([$userId]);
        $parent = $stmt->fetch();

        if ($parent) {
            if ($action === 'approve') {
                $db->prepare("UPDATE users SET status = 'active', updated_at = datetime('now') WHERE id = ?")->execute([$userId]);
                
                // Add welcome notification for parent
                $db->prepare("INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, 'system', 'parent/index.php')")
                   ->execute([$userId, 'Account Approved!', 'Your parent account has been approved by the school administrator. You now have full access to your child portal.']);

                logActivity('Parent Approved', "Approved parent account for {$parent['name']} ({$parent['email']})");
                setFlash('success', "Account for {$parent['name']} has been approved! They can now log in.");
            } elseif ($action === 'reject') {
                $db->prepare("UPDATE users SET status = 'rejected', updated_at = datetime('now') WHERE id = ?")->execute([$userId]);
                logActivity('Parent Rejected', "Rejected parent registration for {$parent['name']} ({$parent['email']})");
                setFlash('warning', "Registration for {$parent['name']} was rejected.");
            }
            header('Location: approvals.php');
            exit;
        }
    }
}

// Fetch pending parents
$pendingParents = $db->query("
    SELECT * FROM users 
    WHERE role = 'parent' AND status = 'pending_approval' 
    ORDER BY created_at ASC
")->fetchAll();

// Fetch recently reviewed accounts
$recentlyReviewed = $db->query("
    SELECT * FROM users 
    WHERE role = 'parent' AND status IN ('active', 'rejected') 
    ORDER BY updated_at DESC LIMIT 6
")->fetchAll();

require_once dirname(__DIR__) . '/includes/header.php';
?>

<!-- Pending Approvals Alert Banner -->
<?php if (empty($pendingParents)): ?>
    <div class="card" style="background: #F0FDF4; border-color: #BBF7D0; text-align: center; padding: 32px;">
        <div style="font-size: 2.5rem; margin-bottom: 8px;">🎉</div>
        <h3 style="color: var(--mint-dark);">All Caught Up!</h3>
        <p style="color: var(--text-secondary); font-size: 0.9rem;">There are no pending parent registration requests waiting for review at this time.</p>
    </div>
<?php else: ?>
    <div class="card" style="border: 2px solid var(--accent); background: #FFFBEB;">
        <div class="card-header" style="border-bottom-color: #FDE68A;">
            <h3 class="card-title" style="color: #B45309;">
                <span>⏳</span> Pending Parent Applications Awaiting Verification
                <span class="badge badge-warning"><?= count($pendingParents) ?> Pending</span>
            </h3>
        </div>

        <div class="table-responsive">
            <table class="table" style="background: #FFFFFF; border-radius: var(--radius-md);">
                <thead>
                    <tr>
                        <th>Parent Name</th>
                        <th>Email Address</th>
                        <th>Mobile Contact</th>
                        <th>Registration Date</th>
                        <th>Status</th>
                        <th>Verification Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingParents as $p): ?>
                        <tr>
                            <td>
                                <div class="user-cell">
                                    <div class="user-cell-avatar" style="background: var(--grad-accent);">
                                        <?= strtoupper(substr($p['name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <strong class="user-cell-name"><?= htmlspecialchars($p['name']) ?></strong>
                                        <div class="user-cell-sub">Applicant ID #<?= $p['id'] ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($p['email']) ?></td>
                            <td><?= htmlspecialchars($p['phone'] ?? 'None provided') ?></td>
                            <td><?= formatDate($p['created_at']) ?> (<?= date('h:i A', strtotime($p['created_at'])) ?>)</td>
                            <td><span class="badge badge-warning">PENDING APPROVAL</span></td>
                            <td>
                                <div class="d-flex gap-2">
                                    <!-- Approve Form -->
                                    <form method="POST" action="approvals.php" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <input type="hidden" name="user_id" value="<?= $p['id'] ?>">
                                        <button type="submit" class="btn btn-mint btn-sm">
                                            ✓ Approve Account
                                        </button>
                                    </form>

                                    <!-- Reject Form -->
                                    <form method="POST" action="approvals.php" onsubmit="return confirm('Reject this registration request?');" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <input type="hidden" name="user_id" value="<?= $p['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">
                                            ✕ Decline
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- Recently Processed Applications -->
<div class="card" style="margin-top: 24px;">
    <div class="card-header">
        <h3 class="card-title">📋 Recently Processed Parent Accounts</h3>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Parent Name</th>
                    <th>Email</th>
                    <th>Mobile</th>
                    <th>Status</th>
                    <th>Last Status Update</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentlyReviewed as $r): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($r['name']) ?></strong></td>
                        <td><?= htmlspecialchars($r['email']) ?></td>
                        <td><?= htmlspecialchars($r['phone'] ?? 'N/A') ?></td>
                        <td>
                            <span class="badge <?= $r['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>">
                                <?= strtoupper($r['status']) ?>
                            </span>
                        </td>
                        <td><?= formatDate($r['updated_at'] ?? $r['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
