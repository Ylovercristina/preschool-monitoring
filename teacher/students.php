<?php
/**
 * Enrolled Students & Admission Data (Teacher View)
 * Preschool Monitoring System
 * Fulfills: "Admission Management: As a teacher, I want to review relevant datas and manage students enrollment."
 */

$pageTitle = 'Classroom Pupil Enrollment & Records';
$pageSubtitle = 'Review student health profiles, emergency contact records, allergy warnings and classroom files';

require_once dirname(__DIR__) . '/includes/auth.php';
checkAuth(['teacher']);

$db = getDB();
$teacherId = $_SESSION['user_id'];

// Get assigned classroom
$classStmt = $db->prepare("SELECT * FROM classrooms WHERE teacher_id = ? LIMIT 1");
$classStmt->execute([$teacherId]);
$classroom = $classStmt->fetch();
$classroomId = $classroom['id'] ?? 0;

// Handle Updating Medical / Notes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $studentId = (int)$_POST['student_id'];
    $allergies = trim($_POST['allergies'] ?? '');
    $medicalNotes = trim($_POST['medical_notes'] ?? '');
    $emergencyName = trim($_POST['emergency_contact_name'] ?? '');
    $emergencyPhone = trim($_POST['emergency_contact_phone'] ?? '');

    $stmt = $db->prepare("UPDATE students SET allergies=?, medical_notes=?, emergency_contact_name=?, emergency_contact_phone=? WHERE id=? AND classroom_id=?");
    $stmt->execute([$allergies, $medicalNotes, $emergencyName, $emergencyPhone, $studentId, $classroomId]);

    logActivity('Pupil Health File Updated', "Teacher updated medical/emergency notes for student ID {$studentId}");
    setFlash('success', 'Pupil health and emergency notes updated successfully!');
    header('Location: students.php');
    exit;
}

// Fetch Classroom Students
$students = $db->query("
    SELECT s.*, u.name as parent_name, u.email as parent_email, u.phone as parent_phone,
           (SELECT COUNT(*) FROM student_milestones sm WHERE sm.student_id = s.id) as milestones_count,
           (SELECT COUNT(*) FROM authorized_pickups ap WHERE ap.student_id = s.id AND ap.is_active = 1) as pickups_count
    FROM students s
    LEFT JOIN users u ON s.parent_id = u.id
    WHERE s.classroom_id = $classroomId AND s.enrollment_status = 'enrolled'
    ORDER BY s.last_name ASC
")->fetchAll();

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="d-flex justify-between align-center" style="margin-bottom: 24px;">
    <div style="max-width: 400px; width: 100%;">
        <input type="text" class="form-control" data-table-search="classPupilsTable" placeholder="🔍 Search pupil, parent or medical note...">
    </div>
    <div style="font-size: 0.9rem; color: var(--text-secondary);">
        Classroom: <strong><?= htmlspecialchars($classroom['name'] ?? 'Assigned Class') ?></strong>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <span>👶</span> Classroom Pupil Masterlist
            <span class="badge badge-primary"><?= count($students) ?> Pupils</span>
        </h3>
    </div>

    <div class="table-responsive">
        <table class="table" id="classPupilsTable">
            <thead>
                <tr>
                    <th>Pupil Full Name</th>
                    <th>Age / DOB</th>
                    <th>Parent / Legal Guardian</th>
                    <th>Emergency Contact</th>
                    <th>Allergies / Special Care</th>
                    <th>Safety & Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($students)): ?>
                    <tr><td colspan="6" style="text-align:center; padding: 24px; color: var(--text-muted);">No students currently enrolled in your classroom.</td></tr>
                <?php else: ?>
                    <?php foreach ($students as $s): 
                        $age = date_diff(date_create($s['dob']), date_create('today'))->y;
                    ?>
                        <tr>
                            <td>
                                <div class="user-cell">
                                    <div class="user-cell-avatar">
                                        <?= strtoupper(substr($s['first_name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <strong class="user-cell-name"><?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?></strong>
                                        <div class="user-cell-sub">LRN: <?= htmlspecialchars($s['lrn'] ?? 'None') ?> &bull; <?= htmlspecialchars($s['gender']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <strong><?= $age ?> yrs old</strong><br>
                                <small style="color: var(--text-muted);"><?= formatDate($s['dob']) ?></small>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($s['parent_name'] ?? 'Not Linked') ?></strong><br>
                                <small style="color: var(--text-muted);"><?= htmlspecialchars($s['parent_phone'] ?? '') ?></small>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($s['emergency_contact_name'] ?? 'None') ?></strong><br>
                                <small style="color: var(--text-muted);"><?= htmlspecialchars($s['emergency_contact_phone'] ?? '') ?></small>
                            </td>
                            <td>
                                <?php if ($s['allergies']): ?>
                                    <span class="badge badge-danger">⚠️ <?= htmlspecialchars($s['allergies']) ?></span>
                                <?php else: ?>
                                    <span class="badge badge-success">No Allergies</span>
                                <?php endif; ?>
                                <?php if ($s['medical_notes']): ?>
                                    <small style="display:block; color:var(--text-muted); margin-top: 4px;"><?= htmlspecialchars($s['medical_notes']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-secondary btn-sm" onclick='editHealthRecord(<?= json_encode($s) ?>)'>
                                        ✏️ Health Notes
                                    </button>
                                    <a href="progress.php?student_id=<?= $s['id'] ?>" class="btn btn-primary btn-sm">
                                        🌟 Progress
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Edit Pupil Health & Emergency Notes -->
<div class="modal-overlay" id="healthModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 class="modal-title" id="healthModalTitle">Update Health & Emergency Data</h3>
            <button class="modal-close" onclick="closeModal('healthModal')">&times;</button>
        </div>
        <form method="POST" action="students.php">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="student_id" id="healthStudentId">

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="allergiesInput">Allergies & Dietary Restrictions</label>
                    <input type="text" name="allergies" id="allergiesInput" class="form-control" placeholder="e.g. Peanuts, Shellfish, Lactose">
                </div>

                <div class="form-group">
                    <label class="form-label" for="medicalNotesInput">Medical Guidelines & Special Instructions</label>
                    <textarea name="medical_notes" id="medicalNotesInput" class="form-control" rows="3" placeholder="e.g. Needs asthma pump in cabinet, naps after lunch"></textarea>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                    <div class="form-group">
                        <label class="form-label" for="emerName">Emergency Contact Person</label>
                        <input type="text" name="emergency_contact_name" id="emerName" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="emerPhone">Emergency Contact Phone</label>
                        <input type="text" name="emergency_contact_phone" id="emerPhone" class="form-control">
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('healthModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function editHealthRecord(s) {
    document.getElementById('healthModalTitle').innerText = 'Health Record: ' + s.first_name + ' ' + s.last_name;
    document.getElementById('healthStudentId').value = s.id;
    document.getElementById('allergiesInput').value = s.allergies || '';
    document.getElementById('medicalNotesInput').value = s.medical_notes || '';
    document.getElementById('emerName').value = s.emergency_contact_name || '';
    document.getElementById('emerPhone').value = s.emergency_contact_phone || '';
    openModal('healthModal');
}
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
