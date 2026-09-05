<?php
/**
 * Student Admission & Records Management
 * Preschool Monitoring System
 * Fulfills: "Admission Management: As an admin, I want to record and keep students and teachers files"
 */

$pageTitle = 'Student Admissions & Records';
$pageSubtitle = 'Manage student enrollment files, emergency contacts, medical records & classroom allocations';

require_once dirname(__DIR__) . '/includes/auth.php';
checkAuth(['admin']);

$db = getDB();

// Handle Actions (Create, Update, Archive)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $lrn = trim($_POST['lrn'] ?? '');
        $dob = $_POST['dob'] ?? '';
        $gender = $_POST['gender'] ?? 'Male';
        $bloodType = trim($_POST['blood_type'] ?? '');
        $classroomId = !empty($_POST['classroom_id']) ? (int)$_POST['classroom_id'] : null;
        $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        $address = trim($_POST['address'] ?? '');
        $emergencyName = trim($_POST['emergency_contact_name'] ?? '');
        $emergencyPhone = trim($_POST['emergency_contact_phone'] ?? '');
        $allergies = trim($_POST['allergies'] ?? '');
        $medicalNotes = trim($_POST['medical_notes'] ?? '');
        $admissionDate = $_POST['admission_date'] ?? date('Y-m-d');

        if (empty($firstName) || empty($lastName) || empty($dob)) {
            setFlash('danger', 'First name, last name, and date of birth are required.');
        } else {
            if ($action === 'create') {
                $stmt = $db->prepare("INSERT INTO students (first_name, last_name, lrn, dob, gender, blood_type, classroom_id, parent_id, address, emergency_contact_name, emergency_contact_phone, allergies, medical_notes, enrollment_status, admission_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'enrolled', ?)");
                $stmt->execute([$firstName, $lastName, $lrn, $dob, $gender, $bloodType, $classroomId, $parentId, $address, $emergencyName, $emergencyPhone, $allergies, $medicalNotes, $admissionDate]);
                logActivity('Student Admitted', "Enrolled new student: {$firstName} {$lastName} ({$lrn})");
                setFlash('success', "Student {$firstName} {$lastName} successfully enrolled!");
            } else {
                $id = (int)$_POST['id'];
                $stmt = $db->prepare("UPDATE students SET first_name=?, last_name=?, lrn=?, dob=?, gender=?, blood_type=?, classroom_id=?, parent_id=?, address=?, emergency_contact_name=?, emergency_contact_phone=?, allergies=?, medical_notes=?, admission_date=? WHERE id=?");
                $stmt->execute([$firstName, $lastName, $lrn, $dob, $gender, $bloodType, $classroomId, $parentId, $address, $emergencyName, $emergencyPhone, $allergies, $medicalNotes, $admissionDate, $id]);
                logActivity('Student Updated', "Updated admission record for student ID {$id} ({$firstName} {$lastName})");
                setFlash('success', "Student record updated successfully!");
            }
            header('Location: students.php');
            exit;
        }
    } elseif ($action === 'archive') {
        $id = (int)$_POST['id'];
        $stmt = $db->prepare("UPDATE students SET enrollment_status = 'archived' WHERE id = ?");
        $stmt->execute([$id]);
        logActivity('Student Archived', "Archived student file ID {$id}");
        setFlash('warning', 'Student file has been archived.');
        header('Location: students.php');
        exit;
    } elseif ($action === 'restore') {
        $id = (int)$_POST['id'];
        $stmt = $db->prepare("UPDATE students SET enrollment_status = 'enrolled' WHERE id = ?");
        $stmt->execute([$id]);
        logActivity('Student Restored', "Restored student file ID {$id} to enrolled status");
        setFlash('success', 'Student enrollment restored.');
        header('Location: students.php');
        exit;
    }
}

// Fetch Classrooms & Parents for dropdowns
$classrooms = $db->query("SELECT * FROM classrooms ORDER BY name")->fetchAll();
$parents = $db->query("SELECT id, name, email FROM users WHERE role = 'parent' AND status = 'active' ORDER BY name")->fetchAll();

// Filter: enrolled vs archived
$statusFilter = $_GET['status'] ?? 'enrolled';
$stmt = $db->prepare("
    SELECT s.*, c.name as class_name, u.name as parent_name, u.phone as parent_phone
    FROM students s
    LEFT JOIN classrooms c ON s.classroom_id = c.id
    LEFT JOIN users u ON s.parent_id = u.id
    WHERE s.enrollment_status = ?
    ORDER BY s.last_name ASC, s.first_name ASC
");
$stmt->execute([$statusFilter]);
$studentsList = $stmt->fetchAll();

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="d-flex justify-between align-center" style="margin-bottom: 24px; flex-wrap: wrap; gap: 14px;">
    <!-- Live Search & Status Toggle -->
    <div class="d-flex gap-3 align-center" style="flex: 1; max-width: 500px;">
        <input type="text" class="form-control" data-table-search="studentsTable" placeholder="🔍 Search by student name, LRN, class or parent...">
        <div class="d-flex gap-1" style="background: #E2E8F0; padding: 3px; border-radius: var(--radius-md);">
            <a href="students.php?status=enrolled" class="btn btn-sm <?= $statusFilter === 'enrolled' ? 'btn-primary' : 'btn-secondary' ?>">Enrolled</a>
            <a href="students.php?status=archived" class="btn btn-sm <?= $statusFilter === 'archived' ? 'btn-primary' : 'btn-secondary' ?>">Archived</a>
        </div>
    </div>

    <!-- Action Button -->
    <button class="btn btn-primary" onclick="openStudentModal()">
        <span>+</span> Admit New Student
    </button>
</div>

<!-- Student Roster Card -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <span>👶</span> <?= $statusFilter === 'archived' ? 'Archived Student Records' : 'Enrolled Students Directory' ?>
            <span class="badge badge-primary"><?= count($studentsList) ?> Students</span>
        </h3>
    </div>

    <div class="table-responsive">
        <table class="table" id="studentsTable">
            <thead>
                <tr>
                    <th>Student Name & LRN</th>
                    <th>Age / DOB</th>
                    <th>Classroom</th>
                    <th>Parent / Legal Guardian</th>
                    <th>Emergency Contact</th>
                    <th>Medical / Allergies</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($studentsList)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 32px; color: var(--text-muted);">
                            No <?= htmlspecialchars($statusFilter) ?> students found.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($studentsList as $s): 
                        $age = date_diff(date_create($s['dob']), date_create('today'))->y;
                    ?>
                        <tr>
                            <td>
                                <div class="user-cell">
                                    <div class="user-cell-avatar">
                                        <?= strtoupper(substr($s['first_name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div class="user-cell-name"><?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?></div>
                                        <div class="user-cell-sub">
                                            <strong>LRN:</strong> <?= htmlspecialchars($s['lrn'] ?? 'None') ?> &bull; 
                                            <?= htmlspecialchars($s['gender']) ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <strong><?= $age ?> yrs old</strong><br>
                                <small style="color: var(--text-muted);"><?= formatDate($s['dob']) ?></small>
                            </td>
                            <td>
                                <span class="badge badge-info"><?= htmlspecialchars($s['class_name'] ?? 'Unassigned') ?></span>
                            </td>
                            <td>
                                <?php if ($s['parent_name']): ?>
                                    <strong><?= htmlspecialchars($s['parent_name']) ?></strong><br>
                                    <small style="color: var(--text-muted);"><?= htmlspecialchars($s['parent_phone'] ?? '') ?></small>
                                <?php else: ?>
                                    <span style="color: var(--text-muted); font-style: italic;">No linked parent</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($s['emergency_contact_name'] ?? 'N/A') ?></strong><br>
                                <small style="color: var(--text-muted);"><?= htmlspecialchars($s['emergency_contact_phone'] ?? '') ?></small>
                            </td>
                            <td>
                                <?php if ($s['allergies']): ?>
                                    <span class="badge badge-danger" title="<?= htmlspecialchars($s['allergies']) ?>">⚠️ Allergies</span>
                                <?php else: ?>
                                    <span class="badge badge-success">Clear</span>
                                <?php endif; ?>
                                <?php if ($s['medical_notes']): ?>
                                    <small style="display:block; color:var(--text-muted); max-width: 140px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= htmlspecialchars($s['medical_notes']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-secondary btn-sm" onclick='viewStudentDetails(<?= json_encode($s) ?>)' title="View Full File">
                                        👁️ View
                                    </button>
                                    <button class="btn btn-subtle btn-sm" onclick='editStudent(<?= json_encode($s) ?>)' title="Edit File">
                                        ✏️ Edit
                                    </button>
                                    <?php if ($s['enrollment_status'] === 'enrolled'): ?>
                                        <form method="POST" action="students.php" onsubmit="return confirm('Archive this student record?');" style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                            <input type="hidden" name="action" value="archive">
                                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" title="Archive">Archive</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" action="students.php" style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                            <input type="hidden" name="action" value="restore">
                                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                            <button type="submit" class="btn btn-mint btn-sm">Restore</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Add / Edit Student File -->
<div class="modal-overlay" id="studentModal">
    <div class="modal-box" style="max-width: 680px;">
        <div class="modal-header">
            <h3 class="modal-title" id="studentModalTitle">Admit New Student</h3>
            <button class="modal-close" onclick="closeModal('studentModal')">&times;</button>
        </div>
        <form method="POST" action="students.php">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" id="formAction" value="create">
            <input type="hidden" name="id" id="studentId" value="">

            <div class="modal-body">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label" for="first_name">First Name *</label>
                        <input type="text" name="first_name" id="first_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="last_name">Last Name *</label>
                        <input type="text" name="last_name" id="last_name" class="form-control" required>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label" for="lrn">Learner Ref No. (LRN)</label>
                        <input type="text" name="lrn" id="lrn" class="form-control" placeholder="LRN-2026-XXX">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="dob">Date of Birth *</label>
                        <input type="date" name="dob" id="dob" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="gender">Gender</label>
                        <select name="gender" id="gender" class="form-select">
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label" for="classroom_id">Assign Classroom</label>
                        <select name="classroom_id" id="classroom_id" class="form-select">
                            <option value="">-- Select Classroom --</option>
                            <?php foreach ($classrooms as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['room_number']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="parent_id">Link Registered Parent</label>
                        <select name="parent_id" id="parent_id" class="form-select">
                            <option value="">-- Select Parent / Guardian --</option>
                            <?php foreach ($parents as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?> (<?= htmlspecialchars($p['email']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="address">Home Address</label>
                    <input type="text" name="address" id="address" class="form-control" placeholder="Street, City, Province">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label" for="emergency_contact_name">Emergency Contact Name</label>
                        <input type="text" name="emergency_contact_name" id="emergency_contact_name" class="form-control" placeholder="e.g. Uncle Robert Watson">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="emergency_contact_phone">Emergency Contact Phone</label>
                        <input type="text" name="emergency_contact_phone" id="emergency_contact_phone" class="form-control" placeholder="+63 9XX XXX XXXX">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label" for="allergies">Allergies (if any)</label>
                        <input type="text" name="allergies" id="allergies" class="form-control" placeholder="e.g. Peanuts, Eggs, Dust">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="blood_type">Blood Type</label>
                        <input type="text" name="blood_type" id="blood_type" class="form-control" placeholder="e.g. O+, A+">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="medical_notes">Medical Conditions / Nurse Instructions</label>
                    <textarea name="medical_notes" id="medical_notes" class="form-control" rows="2" placeholder="e.g. Inhaler kept in medical box, needs extra hydration"></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('studentModal')">Cancel</button>
                <button type="submit" class="btn btn-primary" id="saveStudentBtn">Save Student Record</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: View Student Full File -->
<div class="modal-overlay" id="viewStudentModal">
    <div class="modal-box" style="max-width: 600px;">
        <div class="modal-header">
            <h3 class="modal-title">👶 Official Student File</h3>
            <button class="modal-close" onclick="closeModal('viewStudentModal')">&times;</button>
        </div>
        <div class="modal-body" id="viewStudentContent">
            <!-- Populated via JS -->
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('viewStudentModal')">Close</button>
            <button type="button" class="btn btn-primary" onclick="triggerPrint()">Print Student File</button>
        </div>
    </div>
</div>

<script>
function openStudentModal() {
    document.getElementById('studentModalTitle').innerText = 'Admit New Student';
    document.getElementById('formAction').value = 'create';
    document.getElementById('studentId').value = '';
    document.getElementById('first_name').value = '';
    document.getElementById('last_name').value = '';
    document.getElementById('lrn').value = 'LRN-2026-' + Math.floor(100 + Math.random() * 900);
    document.getElementById('dob').value = '';
    document.getElementById('gender').value = 'Male';
    document.getElementById('classroom_id').value = '';
    document.getElementById('parent_id').value = '';
    document.getElementById('address').value = '';
    document.getElementById('emergency_contact_name').value = '';
    document.getElementById('emergency_contact_phone').value = '';
    document.getElementById('allergies').value = '';
    document.getElementById('blood_type').value = '';
    document.getElementById('medical_notes').value = '';
    openModal('studentModal');
}

function editStudent(s) {
    document.getElementById('studentModalTitle').innerText = 'Edit Student File: ' + s.first_name + ' ' + s.last_name;
    document.getElementById('formAction').value = 'update';
    document.getElementById('studentId').value = s.id;
    document.getElementById('first_name').value = s.first_name || '';
    document.getElementById('last_name').value = s.last_name || '';
    document.getElementById('lrn').value = s.lrn || '';
    document.getElementById('dob').value = s.dob || '';
    document.getElementById('gender').value = s.gender || 'Male';
    document.getElementById('classroom_id').value = s.classroom_id || '';
    document.getElementById('parent_id').value = s.parent_id || '';
    document.getElementById('address').value = s.address || '';
    document.getElementById('emergency_contact_name').value = s.emergency_contact_name || '';
    document.getElementById('emergency_contact_phone').value = s.emergency_contact_phone || '';
    document.getElementById('allergies').value = s.allergies || '';
    document.getElementById('blood_type').value = s.blood_type || '';
    document.getElementById('medical_notes').value = s.medical_notes || '';
    openModal('studentModal');
}

function viewStudentDetails(s) {
    const html = `
        <div style="text-align: center; margin-bottom: 20px;">
            <div class="user-cell-avatar" style="width:64px; height:64px; font-size:1.8rem; margin:0 auto 10px;">
                ${s.first_name.charAt(0)}
            </div>
            <h2>${s.first_name} ${s.last_name}</h2>
            <div style="color: var(--text-muted); font-size: 0.88rem;">LRN: ${s.lrn || 'N/A'} &bull; ${s.gender} &bull; Blood Type: ${s.blood_type || 'N/A'}</div>
            <span class="badge badge-success" style="margin-top: 6px;">${s.enrollment_status.toUpperCase()}</span>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; font-size: 0.9rem; margin-bottom: 16px;">
            <div style="background: var(--bg-card-subtle); padding: 12px; border-radius: var(--radius-sm);">
                <strong>Classroom Allocation:</strong><br>
                ${s.class_name || 'Unassigned'}
            </div>
            <div style="background: var(--bg-card-subtle); padding: 12px; border-radius: var(--radius-sm);">
                <strong>Date of Birth:</strong><br>
                ${s.dob}
            </div>
            <div style="background: var(--bg-card-subtle); padding: 12px; border-radius: var(--radius-sm);">
                <strong>Parent / Guardian:</strong><br>
                ${s.parent_name || 'Not Linked'}<br>
                <small>${s.parent_phone || ''}</small>
            </div>
            <div style="background: var(--bg-card-subtle); padding: 12px; border-radius: var(--radius-sm);">
                <strong>Emergency Contact:</strong><br>
                ${s.emergency_contact_name || 'N/A'}<br>
                <small>${s.emergency_contact_phone || ''}</small>
            </div>
        </div>

        <div style="background: #FFF1F2; border-left: 4px solid var(--rose); padding: 12px; border-radius: var(--radius-sm); margin-bottom: 12px;">
            <strong style="color: var(--rose-dark);">⚠️ Allergies:</strong>
            <p style="margin: 4px 0 0; color: #881337; font-size: 0.88rem;">${s.allergies || 'No known allergies.'}</p>
        </div>

        <div style="background: #F0FDF4; border-left: 4px solid var(--mint); padding: 12px; border-radius: var(--radius-sm);">
            <strong style="color: var(--mint-dark);">🩺 Medical Instructions:</strong>
            <p style="margin: 4px 0 0; color: #064E3B; font-size: 0.88rem;">${s.medical_notes || 'No special medical instructions on file.'}</p>
        </div>
    `;
    document.getElementById('viewStudentContent').innerHTML = html;
    openModal('viewStudentModal');
}
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
