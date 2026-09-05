<?php
/**
 * Authorized Pickup Verification & Child Release
 * Preschool Monitoring System
 * Fulfills: "Authorized Pickup Verification: As a teacher, I want to verify that the person
 * picking up my student is authorized, so that I can make sure student released to a trusted person."
 */

$pageTitle = 'Authorized Pickup Verification';
$pageSubtitle = 'Verify parent-approved guardians, match security PINs & securely log child departure';

require_once dirname(__DIR__) . '/includes/auth.php';
checkAuth(['teacher']);

$db = getDB();
$teacherId = $_SESSION['user_id'];

// Get assigned classroom
$classStmt = $db->prepare("SELECT * FROM classrooms WHERE teacher_id = ? LIMIT 1");
$classStmt->execute([$teacherId]);
$classroom = $classStmt->fetch();
$classroomId = $classroom['id'] ?? 0;

$studentFilter = (int)($_GET['student_id'] ?? 0);

// Handle Pickup Verification Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $studentId = (int)$_POST['student_id'];
    $pickupPersonId = (int)$_POST['pickup_person_id'];
    $enteredPin = trim($_POST['pin_code'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    // Verify PIN against authorized_pickups
    $pStmt = $db->prepare("SELECT ap.*, s.first_name, s.last_name, s.parent_id FROM authorized_pickups ap JOIN students s ON ap.student_id = s.id WHERE ap.id = ? AND ap.student_id = ? AND ap.is_active = 1");
    $pStmt->execute([$pickupPersonId, $studentId]);
    $guardian = $pStmt->fetch();

    if (!$guardian) {
        setFlash('danger', 'Invalid pickup person selection or inactive authorization.');
    } elseif ($guardian['pin_code'] !== $enteredPin) {
        logActivity('Pickup Verification FAILED', "Incorrect PIN entered for guardian {$guardian['full_name']} picking up student {$guardian['first_name']} {$guardian['last_name']}");
        setFlash('danger', "SECURITY WARNING: Incorrect PIN code entered for {$guardian['full_name']}! Student cannot be released.");
    } else {
        // Success: Log child release
        $stmt = $db->prepare("INSERT INTO pickup_logs (student_id, pickup_person_id, verified_by_teacher_id, pickup_time, notes) VALUES (?, ?, ?, datetime('now'), ?)");
        $stmt->execute([$studentId, $pickupPersonId, $teacherId, $notes]);

        // Also record time_out in attendance if today's attendance exists
        $today = date('Y-m-d');
        $timeNow = date('H:i:s');
        $db->prepare("UPDATE attendance SET time_out = ? WHERE student_id = ? AND date = ?")->execute([$timeNow, $studentId, $today]);

        // Send safety notification to parent
        if ($guardian['parent_id']) {
            $notifStmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, 'pickup', 'parent/pickups.php')");
            $notifStmt->execute([
                $guardian['parent_id'],
                'Safety Notice: Child Picked Up',
                "{$guardian['first_name']} has been safely released to authorized guardian {$guardian['full_name']} ({$guardian['relationship']}) at " . date('h:i A') . "."
            ]);
        }

        logActivity('Pickup Verified & Released', "Verified {$guardian['full_name']} with PIN {$enteredPin} for student {$guardian['first_name']} {$guardian['last_name']}");
        setFlash('success', "Pickup Verified! {$guardian['first_name']} {$guardian['last_name']} was safely released to {$guardian['full_name']}. Safety alert sent to parent.");
        header('Location: pickups.php');
        exit;
    }
}

// Fetch Classroom Students
$students = $db->query("
    SELECT s.*, u.name as parent_name,
           (SELECT COUNT(*) FROM authorized_pickups ap WHERE ap.student_id = s.id AND ap.is_active = 1) as guardians_count
    FROM students s
    LEFT JOIN users u ON s.parent_id = u.id
    WHERE s.classroom_id = $classroomId AND s.enrollment_status = 'enrolled'
    ORDER BY s.last_name ASC
")->fetchAll();

// Fetch Authorized Guardians for either selected student or all in class
$guardiansQuery = "
    SELECT ap.*, s.first_name, s.last_name, s.lrn, u.name as parent_name, u.phone as parent_phone
    FROM authorized_pickups ap
    JOIN students s ON ap.student_id = s.id
    JOIN users u ON ap.parent_id = u.id
    WHERE s.classroom_id = $classroomId AND ap.is_active = 1
";
if ($studentFilter > 0) {
    $guardiansQuery .= " AND s.id = $studentFilter";
}
$guardiansQuery .= " ORDER BY s.last_name ASC, ap.full_name ASC";
$guardians = $db->query($guardiansQuery)->fetchAll();

// Today's Pickup Releases
$todayReleases = $db->query("
    SELECT pl.*, s.first_name, s.last_name, ap.full_name as guardian_name, ap.relationship, u.name as teacher_name
    FROM pickup_logs pl
    JOIN students s ON pl.student_id = s.id
    JOIN authorized_pickups ap ON pl.pickup_person_id = ap.id
    LEFT JOIN users u ON pl.verified_by_teacher_id = u.id
    WHERE date(pl.pickup_time) = date('now') AND s.classroom_id = $classroomId
    ORDER BY pl.pickup_time DESC
")->fetchAll();

require_once dirname(__DIR__) . '/includes/header.php';
?>

<!-- Pupil Filter Bar -->
<div class="card" style="padding: 16px 24px; margin-bottom: 24px;">
    <div class="d-flex justify-between align-center" style="flex-wrap: wrap; gap: 16px;">
        <div class="d-flex align-center gap-3">
            <label for="filterStudent" style="font-weight: 700; font-size: 0.95rem;">Filter by Pupil:</label>
            <select id="filterStudent" class="form-select" style="max-width: 300px;" onchange="location.href='pickups.php?student_id=' + this.value;">
                <option value="0">-- All Classroom Pupils (<?= count($students) ?>) --</option>
                <?php foreach ($students as $st): ?>
                    <option value="<?= $st['id'] ?>" <?= $st['id'] === $studentFilter ? 'selected' : '' ?>>
                        <?= htmlspecialchars($st['first_name'] . ' ' . $st['last_name']) ?> (<?= $st['guardians_count'] ?> Guardians)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="font-size: 0.88rem; color: var(--text-muted);">
            Classroom: <strong><?= htmlspecialchars($classroom['name'] ?? 'Class') ?></strong> &bull; Today's Releases: <strong><?= count($todayReleases) ?></strong>
        </div>
    </div>
</div>

<!-- Main Two-Column Layout -->
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
    <!-- Left Column: Authorized Pickup Cards -->
    <div>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <span>🛡️</span> Registered Authorized Guardians Roster
                    <span class="badge badge-primary"><?= count($guardians) ?> Verified Pickups</span>
                </h3>
            </div>

            <?php if (empty($guardians)): ?>
                <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                    No authorized pickup guardians found for the selected pupil.
                </div>
            <?php else: ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 18px;">
                    <?php foreach ($guardians as $g): ?>
                        <div class="pickup-card">
                            <div class="d-flex justify-between align-center">
                                <span class="badge badge-info">AUTHORIZED GUARDIAN</span>
                                <span class="badge badge-success">ID VERIFIED</span>
                            </div>

                            <div class="d-flex align-center gap-3">
                                <div class="user-cell-avatar" style="width: 52px; height: 52px; font-size: 1.4rem; background: var(--grad-primary);">
                                    <?= strtoupper(substr($g['full_name'], 0, 1)) ?>
                                </div>
                                <div>
                                    <h4 style="margin: 0; font-size: 1.05rem;"><?= htmlspecialchars($g['full_name']) ?></h4>
                                    <div style="color: var(--primary); font-weight: 700; font-size: 0.85rem;"><?= htmlspecialchars($g['relationship']) ?></div>
                                    <div style="font-size: 0.78rem; color: var(--text-muted);"><?= htmlspecialchars($g['phone']) ?></div>
                                </div>
                            </div>

                            <div style="background: var(--bg-card-subtle); padding: 10px 12px; border-radius: var(--radius-sm); font-size: 0.84rem;">
                                <strong>Pupil to Pick Up:</strong><br>
                                <span style="font-size: 0.95rem; font-weight: 700; color: var(--text-primary);"><?= htmlspecialchars($g['first_name'] . ' ' . $g['last_name']) ?></span>
                                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 2px;">
                                    Parent: <?= htmlspecialchars($g['parent_name']) ?> (<?= htmlspecialchars($g['parent_phone']) ?>)
                                </div>
                            </div>

                            <!-- Security Verification Action -->
                            <button class="btn btn-mint btn-lg" onclick='openVerifyModal(<?= json_encode($g) ?>)' style="width: 100%;">
                                🛡️ Verify PIN & Release Pupil
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right Column: Today's Departure Log -->
    <div>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <span>📋</span> Today's Departure Log
                </h3>
            </div>

            <?php if (empty($todayReleases)): ?>
                <p style="color: var(--text-muted); font-size: 0.88rem; text-align: center; padding: 20px;">
                    No pupils released yet today.
                </p>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <?php foreach ($todayReleases as $rel): ?>
                        <div style="padding: 12px; background: #F0FDF4; border: 1px solid #BBF7D0; border-radius: var(--radius-md);">
                            <div class="d-flex justify-between align-center" style="margin-bottom: 4px;">
                                <strong style="color: var(--mint-dark); font-size: 0.92rem;">
                                    <?= htmlspecialchars($rel['first_name'] . ' ' . $rel['last_name']) ?>
                                </strong>
                                <span class="badge badge-success" style="font-size: 0.7rem;">
                                    <?= date('h:i A', strtotime($rel['pickup_time'])) ?>
                                </span>
                            </div>
                            <div style="font-size: 0.82rem; color: #065F46;">
                                Released to: <strong><?= htmlspecialchars($rel['guardian_name']) ?></strong> (<?= htmlspecialchars($rel['relationship']) ?>)
                            </div>
                            <?php if ($rel['notes']): ?>
                                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;">
                                    Note: <?= htmlspecialchars($rel['notes']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal: Verify PIN & Release Student -->
<div class="modal-overlay" id="verifyPickupModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 class="modal-title">🛡️ Guardian Verification & Release</h3>
            <button class="modal-close" onclick="closeModal('verifyPickupModal')">&times;</button>
        </div>
        <form method="POST" action="pickups.php">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="student_id" id="verifyStudentId">
            <input type="hidden" name="pickup_person_id" id="verifyPickupId">

            <div class="modal-body">
                <div style="background: #EEF2FF; border: 1px solid #C7D2FE; border-radius: var(--radius-md); padding: 14px; margin-bottom: 18px;">
                    <div style="font-size: 0.8rem; color: var(--primary); font-weight: 700; text-transform: uppercase;">Pupil Being Released</div>
                    <div style="font-size: 1.2rem; font-weight: 800; color: var(--text-primary);" id="verifyPupilName">Pupil Name</div>
                    <div style="margin-top: 6px; font-size: 0.85rem; color: var(--text-secondary);">
                        Authorized Person Present: <strong id="verifyPersonName">Guardian</strong> (<span id="verifyRelation">Relation</span>)
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="pin_code" style="font-size: 1rem;">Enter Guardian 4-Digit Security PIN *</label>
                    <input type="text" name="pin_code" id="pin_code" class="form-control" placeholder="••••" maxlength="6" style="font-size: 1.5rem; text-align: center; letter-spacing: 0.3em; font-family: monospace;" required autofocus>
                    <small style="color: var(--text-muted); font-size: 0.78rem; display: block; margin-top: 4px; text-align: center;">
                        Ask the guardian to provide their personal pickup PIN code to verify authorization.
                    </small>
                </div>

                <div class="form-group">
                    <label class="form-label" for="notes">Verification Notes / ID Inspection</label>
                    <input type="text" name="notes" id="notes" class="form-control" placeholder="e.g. Inspected Government ID, verified facial photo">
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('verifyPickupModal')">Cancel</button>
                <button type="submit" class="btn btn-mint btn-lg">
                    ✓ Confirm Release & Alert Parent
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openVerifyModal(g) {
    document.getElementById('verifyStudentId').value = g.student_id;
    document.getElementById('verifyPickupId').value = g.id;
    document.getElementById('verifyPupilName').innerText = g.first_name + ' ' + g.last_name;
    document.getElementById('verifyPersonName').innerText = g.full_name;
    document.getElementById('verifyRelation').innerText = g.relationship;
    document.getElementById('pin_code').value = '';
    document.getElementById('notes').value = 'Inspected ID and verified pickup pass.';
    openModal('verifyPickupModal');
}
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
