<?php
/**
 * Teacher Records & Files Management
 * Preschool Monitoring System
 * Fulfills: "Admission Management: As an admin, I want to record and keep students and teachers files"
 */

$pageTitle = 'Teacher Files & Assignments';
$pageSubtitle = 'Record and manage faculty files, contact information, credentials and assigned classrooms';

require_once dirname(__DIR__) . '/includes/auth.php';
checkAuth(['admin']);

$db = getDB();

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $classroomId = !empty($_POST['classroom_id']) ? (int)$_POST['classroom_id'] : null;

        if (empty($name) || empty($email)) {
            setFlash('danger', 'Teacher name and email address are required.');
        } else {
            if ($action === 'create') {
                $password = $_POST['password'] ?? 'teacher123';
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (name, email, password, role, phone, status, created_at) VALUES (?, ?, ?, 'teacher', ?, 'active', datetime('now'))");
                $stmt->execute([$name, $email, $hash, $phone]);
                $teacherId = $db->lastInsertId();

                if ($classroomId) {
                    $cStmt = $db->prepare("UPDATE classrooms SET teacher_id = ? WHERE id = ?");
                    $cStmt->execute([$teacherId, $classroomId]);
                }

                logActivity('Teacher Added', "Created teacher file for {$name} ({$email})");
                setFlash('success', "Teacher record created successfully!");
            } else {
                $id = (int)$_POST['id'];
                $stmt = $db->prepare("UPDATE users SET name=?, email=?, phone=? WHERE id=? AND role='teacher'");
                $stmt->execute([$name, $email, $phone, $id]);

                // Reset classroom teacher
                $db->prepare("UPDATE classrooms SET teacher_id = NULL WHERE teacher_id = ?")->execute([$id]);
                if ($classroomId) {
                    $db->prepare("UPDATE classrooms SET teacher_id = ? WHERE id = ?")->execute([$id, $classroomId]);
                }

                logActivity('Teacher Updated', "Updated teacher file ID {$id} ({$name})");
                setFlash('success', "Teacher file updated successfully!");
            }
            header('Location: teachers.php');
            exit;
        }
    } elseif ($action === 'archive') {
        $id = (int)$_POST['id'];
        $db->prepare("UPDATE users SET status = 'archived' WHERE id = ? AND role = 'teacher'")->execute([$id]);
        $db->prepare("UPDATE classrooms SET teacher_id = NULL WHERE teacher_id = ?")->execute([$id]);
        logActivity('Teacher Archived', "Archived teacher ID {$id}");
        setFlash('warning', 'Teacher file has been archived.');
        header('Location: teachers.php');
        exit;
    }
}

// Fetch Teachers with classroom assignment
$teachers = $db->query("
    SELECT u.*, c.name as class_name, c.id as class_id, c.room_number,
    (SELECT COUNT(*) FROM students s WHERE s.classroom_id = c.id AND s.enrollment_status = 'enrolled') as student_count
    FROM users u
    LEFT JOIN classrooms c ON c.teacher_id = u.id
    WHERE u.role = 'teacher'
    ORDER BY u.status ASC, u.name ASC
")->fetchAll();

$classrooms = $db->query("SELECT * FROM classrooms ORDER BY name")->fetchAll();

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="d-flex justify-between align-center" style="margin-bottom: 24px;">
    <div style="max-width: 400px; width: 100%;">
        <input type="text" class="form-control" data-table-search="teachersTable" placeholder="🔍 Search teacher name, email or class...">
    </div>
    <button class="btn btn-primary" onclick="openTeacherModal()">
        <span>+</span> Add Faculty Member
    </button>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <span>👩‍🏫</span> Faculty & Teacher Directory
            <span class="badge badge-primary"><?= count($teachers) ?> Teachers</span>
        </h3>
    </div>

    <div class="table-responsive">
        <table class="table" id="teachersTable">
            <thead>
                <tr>
                    <th>Teacher Name</th>
                    <th>Email & Contact</th>
                    <th>Assigned Classroom</th>
                    <th>Enrolled Students</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($teachers)): ?>
                    <tr><td colspan="6" style="text-align: center; padding: 24px; color: var(--text-muted);">No teacher files found.</td></tr>
                <?php else: ?>
                    <?php foreach ($teachers as $t): ?>
                        <tr>
                            <td>
                                <div class="user-cell">
                                    <div class="user-cell-avatar" style="background: var(--grad-mint);">
                                        <?= strtoupper(substr($t['name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div class="user-cell-name"><?= htmlspecialchars($t['name']) ?></div>
                                        <div class="user-cell-sub">Teacher ID: #TEA-<?= str_pad($t['id'], 3, '0', STR_PAD_LEFT) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($t['email']) ?></strong><br>
                                <small style="color: var(--text-muted);"><?= htmlspecialchars($t['phone'] ?? 'No phone listed') ?></small>
                            </td>
                            <td>
                                <?php if ($t['class_name']): ?>
                                    <span class="badge badge-info"><?= htmlspecialchars($t['class_name']) ?></span><br>
                                    <small style="color: var(--text-muted);"><?= htmlspecialchars($t['room_number']) ?></small>
                                <?php else: ?>
                                    <span style="color: var(--text-muted); font-style: italic;">Unassigned</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= (int)$t['student_count'] ?></strong> students
                            </td>
                            <td>
                                <span class="badge <?= $t['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>">
                                    <?= strtoupper($t['status']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-secondary btn-sm" onclick='editTeacher(<?= json_encode($t) ?>)'>
                                        ✏️ Edit
                                    </button>
                                    <?php if ($t['status'] === 'active'): ?>
                                        <form method="POST" action="teachers.php" onsubmit="return confirm('Archive this teacher record?');" style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                            <input type="hidden" name="action" value="archive">
                                            <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">Archive</button>
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

<!-- Modal: Add / Edit Teacher File -->
<div class="modal-overlay" id="teacherModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 class="modal-title" id="teacherModalTitle">Add Faculty Member</h3>
            <button class="modal-close" onclick="closeModal('teacherModal')">&times;</button>
        </div>
        <form method="POST" action="teachers.php">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" id="teacherAction" value="create">
            <input type="hidden" name="id" id="teacherId" value="">

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="teacher_name">Full Name *</label>
                    <input type="text" name="name" id="teacher_name" class="form-control" placeholder="e.g. Teacher Sarah Jenkins" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="teacher_email">Email Address *</label>
                    <input type="email" name="email" id="teacher_email" class="form-control" placeholder="teacher@preschool.com" required>
                </div>

                <div class="form-group" id="passwordGroup">
                    <label class="form-label" for="teacher_password">Initial Password</label>
                    <input type="password" name="password" id="teacher_password" class="form-control" placeholder="teacher123">
                    <small style="color: var(--text-muted); font-size: 0.78rem;">Default: teacher123</small>
                </div>

                <div class="form-group">
                    <label class="form-label" for="teacher_phone">Phone / Mobile</label>
                    <input type="text" name="phone" id="teacher_phone" class="form-control" placeholder="+63 9XX XXX XXXX">
                </div>

                <div class="form-group">
                    <label class="form-label" for="teacher_classroom">Assigned Classroom</label>
                    <select name="classroom_id" id="teacher_classroom" class="form-select">
                        <option value="">-- No Assigned Classroom --</option>
                        <?php foreach ($classrooms as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['room_number']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('teacherModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Teacher File</button>
            </div>
        </form>
    </div>
</div>

<script>
function openTeacherModal() {
    document.getElementById('teacherModalTitle').innerText = 'Add Faculty Member';
    document.getElementById('teacherAction').value = 'create';
    document.getElementById('teacherId').value = '';
    document.getElementById('teacher_name').value = '';
    document.getElementById('teacher_email').value = '';
    document.getElementById('teacher_phone').value = '';
    document.getElementById('teacher_classroom').value = '';
    document.getElementById('passwordGroup').style.display = 'block';
    openModal('teacherModal');
}

function editTeacher(t) {
    document.getElementById('teacherModalTitle').innerText = 'Edit Teacher File: ' + t.name;
    document.getElementById('teacherAction').value = 'update';
    document.getElementById('teacherId').value = t.id;
    document.getElementById('teacher_name').value = t.name;
    document.getElementById('teacher_email').value = t.email;
    document.getElementById('teacher_phone').value = t.phone || '';
    document.getElementById('teacher_classroom').value = t.class_id || '';
    document.getElementById('passwordGroup').style.display = 'none';
    openModal('teacherModal');
}
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
