<?php
/**
 * Classroom Daily Attendance Tracking
 * Preschool Monitoring System
 * Fulfills: "Attendance Tracking: As a teacher, I want to record student attendance,
 * so that I can keep track of who is present and absent."
 */

$pageTitle = 'Classroom Attendance Tracking';
$pageSubtitle = 'Record daily student attendance, time-in, tardiness and absence remarks';

require_once dirname(__DIR__) . '/includes/auth.php';
checkAuth(['teacher']);

$db = getDB();
$teacherId = $_SESSION['user_id'];

// Get assigned classroom
$classStmt = $db->prepare("SELECT * FROM classrooms WHERE teacher_id = ? LIMIT 1");
$classStmt->execute([$teacherId]);
$classroom = $classStmt->fetch();
$classroomId = $classroom['id'] ?? 0;

$selectedDate = $_GET['date'] ?? date('Y-m-d');

// Handle Attendance Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $date = $_POST['date'] ?? date('Y-m-d');
    $attendanceData = $_POST['attendance'] ?? [];

    foreach ($attendanceData as $studentId => $data) {
        $studentId = (int)$studentId;
        $status = $data['status'] ?? 'present';
        $timeIn = !empty($data['time_in']) ? $data['time_in'] : ($status === 'present' || $status === 'late' ? '08:00:00' : null);
        $remarks = trim($data['remarks'] ?? '');

        // Check if attendance record exists for this student on this date
        $check = $db->prepare("SELECT id FROM attendance WHERE student_id = ? AND date = ?");
        $check->execute([$studentId, $date]);
        $existing = $check->fetch();

        if ($existing) {
            $up = $db->prepare("UPDATE attendance SET status = ?, time_in = ?, remarks = ?, recorded_by = ? WHERE id = ?");
            $up->execute([$status, $timeIn, $remarks, $teacherId, $existing['id']]);
        } else {
            $ins = $db->prepare("INSERT INTO attendance (student_id, classroom_id, date, status, time_in, remarks, recorded_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $ins->execute([$studentId, $classroomId, $date, $status, $timeIn, $remarks, $teacherId]);
        }
    }

    logActivity('Attendance Recorded', "Recorded attendance for classroom {$classroom['name']} on {$date}");
    setFlash('success', "Attendance records saved successfully for " . formatDate($date) . "!");
    header("Location: attendance.php?date={$date}");
    exit;
}

// Fetch enrolled students with attendance for selectedDate
$stmt = $db->prepare("
    SELECT s.*, 
           a.status as att_status, a.time_in as att_time_in, a.remarks as att_remarks
    FROM students s
    LEFT JOIN attendance a ON a.student_id = s.id AND a.date = ?
    WHERE s.classroom_id = ? AND s.enrollment_status = 'enrolled'
    ORDER BY s.last_name ASC, s.first_name ASC
");
$stmt->execute([$selectedDate, $classroomId]);
$studentsList = $stmt->fetchAll();

// Daily Stats
$presentCount = 0; $absentCount = 0; $lateCount = 0; $excusedCount = 0;
foreach ($studentsList as $st) {
    if ($st['att_status'] === 'present') $presentCount++;
    elseif ($st['att_status'] === 'late') $lateCount++;
    elseif ($st['att_status'] === 'absent') $absentCount++;
    elseif ($st['att_status'] === 'excused') $excusedCount++;
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<!-- Date Selection & Action Bar -->
<div class="card" style="padding: 16px 24px; margin-bottom: 24px;">
    <form method="GET" action="attendance.php" class="d-flex justify-between align-center" style="flex-wrap: wrap; gap: 16px;">
        <div class="d-flex align-center gap-3">
            <label for="dateSelect" style="font-weight: 700; font-size: 0.95rem;">Select Attendance Date:</label>
            <input type="date" name="date" id="dateSelect" class="form-control" value="<?= htmlspecialchars($selectedDate) ?>" style="max-width: 200px;" onchange="this.form.submit()">
            <a href="attendance.php?date=<?= date('Y-m-d') ?>" class="btn btn-secondary btn-sm">Jump to Today</a>
        </div>

        <div class="d-flex gap-2">
            <button type="button" class="btn btn-mint btn-sm" onclick="markAll('present')">
                ✓ Mark All Present
            </button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="triggerPrint()">
                🖨️ Print Sheet
            </button>
        </div>
    </form>
</div>

<!-- Daily Attendance Summary Badges -->
<div class="stat-grid" style="margin-bottom: 20px;">
    <div class="stat-card" style="padding: 12px 18px;">
        <div class="stat-content">
            <div class="stat-value" style="font-size: 1.4rem;"><?= count($studentsList) ?></div>
            <div class="stat-label">Total Pupils</div>
        </div>
    </div>
    <div class="stat-card" style="padding: 12px 18px;">
        <div class="stat-content">
            <div class="stat-value" style="font-size: 1.4rem; color: var(--mint);"><?= $presentCount ?></div>
            <div class="stat-label">Present</div>
        </div>
    </div>
    <div class="stat-card" style="padding: 12px 18px;">
        <div class="stat-content">
            <div class="stat-value" style="font-size: 1.4rem; color: var(--accent-hover);"><?= $lateCount ?></div>
            <div class="stat-label">Tardy / Late</div>
        </div>
    </div>
    <div class="stat-card" style="padding: 12px 18px;">
        <div class="stat-content">
            <div class="stat-value" style="font-size: 1.4rem; color: var(--rose);"><?= $absentCount ?></div>
            <div class="stat-label">Absent</div>
        </div>
    </div>
</div>

<!-- Attendance Form -->
<form method="POST" action="attendance.php">
    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
    <input type="hidden" name="date" value="<?= htmlspecialchars($selectedDate) ?>">

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <span>📋</span> Daily Attendance Roster: <?= htmlspecialchars($classroom['name'] ?? 'Class') ?>
                <span class="badge badge-primary"><?= formatDate($selectedDate) ?></span>
            </h3>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Pupil Name & LRN</th>
                        <th>Attendance Status</th>
                        <th>Time In</th>
                        <th>Remarks / Health Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($studentsList)): ?>
                        <tr><td colspan="4" style="text-align:center; padding: 32px; color: var(--text-muted);">No enrolled students in your classroom.</td></tr>
                    <?php else: ?>
                        <?php foreach ($studentsList as $s): 
                            $currentStatus = $s['att_status'] ?? 'present';
                            $timeIn = $s['att_time_in'] ? date('H:i', strtotime($s['att_time_in'])) : '08:00';
                        ?>
                            <tr>
                                <td>
                                    <div class="user-cell">
                                        <div class="user-cell-avatar">
                                            <?= strtoupper(substr($s['first_name'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <strong class="user-cell-name"><?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?></strong>
                                            <div class="user-cell-sub">LRN: <?= htmlspecialchars($s['lrn'] ?? 'None') ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex gap-2" style="flex-wrap: wrap;">
                                        <label style="display: flex; align-items: center; gap: 4px; cursor: pointer; font-size: 0.86rem; font-weight: 600;">
                                            <input type="radio" name="attendance[<?= $s['id'] ?>][status]" value="present" class="att-radio-present" <?= $currentStatus === 'present' ? 'checked' : '' ?>>
                                            <span style="color: var(--mint-dark);">Present</span>
                                        </label>
                                        <label style="display: flex; align-items: center; gap: 4px; cursor: pointer; font-size: 0.86rem; font-weight: 600;">
                                            <input type="radio" name="attendance[<?= $s['id'] ?>][status]" value="late" <?= $currentStatus === 'late' ? 'checked' : '' ?>>
                                            <span style="color: var(--accent-hover);">Late</span>
                                        </label>
                                        <label style="display: flex; align-items: center; gap: 4px; cursor: pointer; font-size: 0.86rem; font-weight: 600;">
                                            <input type="radio" name="attendance[<?= $s['id'] ?>][status]" value="absent" <?= $currentStatus === 'absent' ? 'checked' : '' ?>>
                                            <span style="color: var(--rose);">Absent</span>
                                        </label>
                                        <label style="display: flex; align-items: center; gap: 4px; cursor: pointer; font-size: 0.86rem; font-weight: 600;">
                                            <input type="radio" name="attendance[<?= $s['id'] ?>][status]" value="excused" <?= $currentStatus === 'excused' ? 'checked' : '' ?>>
                                            <span style="color: var(--sky);">Excused</span>
                                        </label>
                                    </div>
                                </td>
                                <td>
                                    <input type="time" name="attendance[<?= $s['id'] ?>][time_in]" class="form-control" value="<?= htmlspecialchars($timeIn) ?>" style="max-width: 130px; font-size: 0.88rem; padding: 6px 10px;">
                                </td>
                                <td>
                                    <input type="text" name="attendance[<?= $s['id'] ?>][remarks]" class="form-control" value="<?= htmlspecialchars($s['att_remarks'] ?? '') ?>" placeholder="e.g. Energetic, arrived late with parent" style="font-size: 0.88rem; padding: 6px 10px;">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div style="padding: 16px 20px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end;">
            <button type="submit" class="btn btn-primary btn-lg">
                💾 Save Attendance Records
            </button>
        </div>
    </div>
</form>

<script>
function markAll(status) {
    if (status === 'present') {
        document.querySelectorAll('.att-radio-present').forEach(radio => radio.checked = true);
    }
}
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
