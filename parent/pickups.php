<?php
/**
 * Authorized Pickup Registration & Digital Pass Management (Parent View)
 * Preschool Monitoring System
 * Fulfills: "Authorized Pickup Verification: As a parent, I want to register or provide
 * authorized pickup information, so that the school knows who is allowed to pick up my child."
 */

$pageTitle = 'Authorized Pickup Registration';
$pageSubtitle = 'Register authorized guardians, generate digital security passes & set pickup PIN codes for child safety';

require_once dirname(__DIR__) . '/includes/auth.php';
checkAuth(['parent']);

$db = getDB();
$parentId = $_SESSION['user_id'];

// Fetch Child
$childStmt = $db->prepare("SELECT * FROM students WHERE parent_id = ? AND enrollment_status = 'enrolled' LIMIT 1");
$childStmt->execute([$parentId]);
$child = $childStmt->fetch();
$childId = $child['id'] ?? 0;

// Handle Adding / Editing / Deactivating Pickup Guardians
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $fullName = trim($_POST['full_name'] ?? '');
        $relationship = trim($_POST['relationship'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $pinCode = trim($_POST['pin_code'] ?? '');

        if (empty($fullName) || empty($relationship) || empty($phone) || empty($pinCode)) {
            setFlash('danger', 'All guardian fields including the security PIN are required.');
        } elseif (strlen($pinCode) < 4) {
            setFlash('danger', 'Security PIN must be at least 4 digits.');
        } else {
            if ($action === 'create') {
                $stmt = $db->prepare("INSERT INTO authorized_pickups (student_id, parent_id, full_name, relationship, phone, pin_code, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, 1, datetime('now'))");
                $stmt->execute([$childId, $parentId, $fullName, $relationship, $phone, $pinCode]);

                logActivity('Guardian Registered', "Parent registered pickup guardian: {$fullName} ({$relationship}) for student ID {$childId}");
                setFlash('success', "Authorized pickup person {$fullName} registered successfully with PIN {$pinCode}!");
            } else {
                $id = (int)$_POST['id'];
                $stmt = $db->prepare("UPDATE authorized_pickups SET full_name=?, relationship=?, phone=?, pin_code=? WHERE id=? AND parent_id=?");
                $stmt->execute([$fullName, $relationship, $phone, $pinCode, $id, $parentId]);

                logActivity('Guardian Updated', "Parent updated pickup guardian ID {$id} ({$fullName})");
                setFlash('success', "Guardian information and PIN code updated successfully!");
            }
            header('Location: pickups.php');
            exit;
        }
    } elseif ($action === 'toggle_active') {
        $id = (int)$_POST['id'];
        $newActive = (int)$_POST['is_active'];
        $db->prepare("UPDATE authorized_pickups SET is_active = ? WHERE id = ? AND parent_id = ?")->execute([$newActive, $id, $parentId]);

        logActivity('Guardian Status Changed', "Parent toggled active status for guardian ID {$id}");
        setFlash('warning', 'Guardian authorization status updated.');
        header('Location: pickups.php');
        exit;
    }
}

// Fetch Registered Guardians
$guardians = [];
if ($childId) {
    $gStmt = $db->prepare("SELECT * FROM authorized_pickups WHERE student_id = ? ORDER BY is_active DESC, id ASC");
    $gStmt->execute([$childId]);
    $guardians = $gStmt->fetchAll();
}

// Recent Pickup Logs for Child
$pickupHistory = [];
if ($childId) {
    $histStmt = $db->prepare("
        SELECT pl.*, ap.full_name as guardian_name, ap.relationship, u.name as teacher_name
        FROM pickup_logs pl
        LEFT JOIN authorized_pickups ap ON pl.pickup_person_id = ap.id
        LEFT JOIN users u ON pl.verified_by_teacher_id = u.id
        WHERE pl.student_id = ?
        ORDER BY pl.pickup_time DESC LIMIT 5
    ");
    $histStmt->execute([$childId]);
    $pickupHistory = $histStmt->fetchAll();
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<?php if (!$child): ?>
    <div class="card" style="text-align: center; padding: 40px;">
        <p style="color: var(--text-muted);">No enrolled child currently linked to your parent account.</p>
    </div>
<?php else: ?>

    <!-- Action Toolbar -->
    <div class="d-flex justify-between align-center" style="margin-bottom: 24px; flex-wrap: wrap; gap: 14px;">
        <div>
            <h2 style="margin: 0; font-size: 1.35rem;">
                🛡️ Authorized Pickup Management: <?= htmlspecialchars($child['first_name'] . ' ' . $child['last_name']) ?>
            </h2>
            <div style="font-size: 0.88rem; color: var(--text-muted);">
                Only registered persons with a valid 4-digit security PIN can be released from preschool premises.
            </div>
        </div>
        <button class="btn btn-primary" onclick="openGuardianModal()">
            <span>+</span> Register Authorized Guardian
        </button>
    </div>

    <!-- Active Guardians Grid -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <span>🛡️</span> Approved Pickup Guardians & Digital Passes
                <span class="badge badge-primary"><?= count($guardians) ?> Registered</span>
            </h3>
        </div>

        <?php if (empty($guardians)): ?>
            <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                No authorized pickup persons registered yet. Register a family member, sitter, or driver above.
            </div>
        <?php else: ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px;">
                <?php foreach ($guardians as $g): ?>
                    <div class="pickup-card <?= $g['is_active'] ? 'verified' : '' ?>" style="<?= !$g['is_active'] ? 'opacity: 0.6;' : '' ?>">
                        <div class="d-flex justify-between align-center">
                            <span class="badge <?= $g['is_active'] ? 'badge-success' : 'badge-danger' ?>">
                                <?= $g['is_active'] ? 'AUTHORIZED TO PICK UP' : 'DEACTIVATED' ?>
                            </span>
                            <span class="badge badge-info"><?= htmlspecialchars($g['relationship']) ?></span>
                        </div>

                        <div class="d-flex align-center gap-3">
                            <div class="user-cell-avatar" style="width: 50px; height: 50px; font-size: 1.3rem;">
                                <?= strtoupper(substr($g['full_name'], 0, 1)) ?>
                            </div>
                            <div>
                                <h4 style="margin: 0; font-size: 1.1rem;"><?= htmlspecialchars($g['full_name']) ?></h4>
                                <div style="color: var(--text-muted); font-size: 0.82rem;">📞 <?= htmlspecialchars($g['phone']) ?></div>
                            </div>
                        </div>

                        <!-- Security PIN Box -->
                        <div style="background: #0F172A; border-radius: var(--radius-md); padding: 10px 14px; text-align: center; margin: 4px 0;">
                            <div style="font-size: 0.72rem; color: #94A3B8; text-transform: uppercase; letter-spacing: 0.08em;">Guardian Security PIN</div>
                            <div class="pickup-pin-box" style="margin: 4px 0 0; font-size: 1.6rem;">
                                <?= htmlspecialchars($g['pin_code']) ?>
                            </div>
                        </div>

                        <small style="color: var(--text-muted); font-size: 0.76rem; text-align: center; display: block;">
                            Share this secret 4-digit PIN with <?= htmlspecialchars($g['full_name']) ?> to verify with the teacher at dismissal.
                        </small>

                        <!-- Action Buttons -->
                        <div class="d-flex gap-2" style="margin-top: 6px;">
                            <button class="btn btn-secondary btn-sm" onclick='editGuardian(<?= json_encode($g) ?>)' style="flex: 1;">
                                ✏️ Edit
                            </button>
                            <form method="POST" action="pickups.php" style="flex: 1;">
                                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                <input type="hidden" name="action" value="toggle_active">
                                <input type="hidden" name="id" value="<?= $g['id'] ?>">
                                <input type="hidden" name="is_active" value="<?= $g['is_active'] ? 0 : 1 ?>">
                                <button type="submit" class="btn <?= $g['is_active'] ? 'btn-danger' : 'btn-mint' ?> btn-sm" style="width: 100%;">
                                    <?= $g['is_active'] ? 'Deactivate' : 'Reactivate' ?>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Recent Child Pickups History -->
    <div class="card" style="margin-top: 24px;">
        <div class="card-header">
            <h3 class="card-title">
                <span>📋</span> Recent Pickup & Release Departure Log
            </h3>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Guardian Verified</th>
                        <th>Relationship</th>
                        <th>Classroom Teacher Verifier</th>
                        <th>Verification Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pickupHistory)): ?>
                        <tr><td colspan="5" style="text-align: center; padding: 20px; color: var(--text-muted);">No departure release history on record yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($pickupHistory as $ph): ?>
                            <tr>
                                <td>
                                    <strong><?= formatDate($ph['pickup_time']) ?></strong><br>
                                    <small style="color: var(--text-muted);"><?= date('h:i A', strtotime($ph['pickup_time'])) ?></small>
                                </td>
                                <td>
                                    <strong style="color: var(--mint-dark);"><?= htmlspecialchars($ph['guardian_name'] ?? 'Authorized Person') ?></strong>
                                </td>
                                <td><span class="badge badge-info"><?= htmlspecialchars($ph['relationship'] ?? 'Guardian') ?></span></td>
                                <td><?= htmlspecialchars($ph['teacher_name'] ?? 'Teacher') ?></td>
                                <td><?= htmlspecialchars($ph['notes'] ?: 'Verified ID and security code.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php endif; ?>

<!-- Modal: Register / Edit Authorized Guardian -->
<div class="modal-overlay" id="guardianModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 class="modal-title" id="guardianModalTitle">Register Authorized Guardian</h3>
            <button class="modal-close" onclick="closeModal('guardianModal')">&times;</button>
        </div>
        <form method="POST" action="pickups.php">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" id="guardianFormAction" value="create">
            <input type="hidden" name="id" id="guardianId" value="">

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="guardian_name">Guardian Full Name *</label>
                    <input type="text" name="full_name" id="guardian_name" class="form-control" placeholder="e.g. Robert Watson" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="guardian_relation">Relationship to Child *</label>
                    <input type="text" name="relationship" id="guardian_relation" class="form-control" placeholder="e.g. Uncle, Grandmother, Sitter, Driver" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="guardian_phone">Contact Mobile Number *</label>
                    <input type="text" name="phone" id="guardian_phone" class="form-control" placeholder="+63 9XX XXX XXXX" required>
                </div>

                <div class="form-group">
                    <div class="d-flex justify-between align-center" style="margin-bottom: 6px;">
                        <label class="form-label" for="guardian_pin" style="margin: 0;">4-Digit Security PIN Code *</label>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="generateRandomPin()" style="padding: 2px 8px; font-size: 0.76rem;">
                            ⚡ Auto Generate PIN
                        </button>
                    </div>
                    <input type="text" name="pin_code" id="guardian_pin" class="form-control" placeholder="4-digit PIN (e.g. 5821)" maxlength="6" style="font-size: 1.3rem; text-align: center; letter-spacing: 0.25em; font-family: monospace;" required>
                    <small style="color: var(--text-muted); font-size: 0.78rem; display: block; margin-top: 4px;">
                        The guardian must present this PIN to the teacher when picking up your child.
                    </small>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('guardianModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Authorized Guardian</button>
            </div>
        </form>
    </div>
</div>

<script>
function openGuardianModal() {
    document.getElementById('guardianModalTitle').innerText = 'Register Authorized Guardian';
    document.getElementById('guardianFormAction').value = 'create';
    document.getElementById('guardianId').value = '';
    document.getElementById('guardian_name').value = '';
    document.getElementById('guardian_relation').value = '';
    document.getElementById('guardian_phone').value = '';
    generateRandomPin();
    openModal('guardianModal');
}

function editGuardian(g) {
    document.getElementById('guardianModalTitle').innerText = 'Edit Guardian: ' + g.full_name;
    document.getElementById('guardianFormAction').value = 'update';
    document.getElementById('guardianId').value = g.id;
    document.getElementById('guardian_name').value = g.full_name;
    document.getElementById('guardian_relation').value = g.relationship;
    document.getElementById('guardian_phone').value = g.phone;
    document.getElementById('guardian_pin').value = g.pin_code;
    openModal('guardianModal');
}

function generateRandomPin() {
    const pin = Math.floor(1000 + Math.random() * 9000);
    document.getElementById('guardian_pin').value = pin;
}
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
