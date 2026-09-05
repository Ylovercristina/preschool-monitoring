<?php
/**
 * Child Attendance History (Parent View)
 * Preschool Monitoring System
 * Fulfills: "Attendance Tracking: As a parent, I want to view my child's attendance record,
 * so that I can know if my child attended school."
 */

$pageTitle = 'Child Attendance Record';
$pageSubtitle = 'Monitor daily preschool attendance logs, arrival times, dismissals and teacher remarks';

require_once dirname(__DIR__) . '/includes/auth.php';
checkAuth(['parent']);

$db = getDB();
$parentId = $_SESSION['user_id'];

// Fetch Child
$childStmt = $db->prepare("SELECT s.*, c.name as class_name FROM students s LEFT JOIN classrooms c ON s.classroom_id = c.id WHERE s.parent_id = ? LIMIT 1");
$childStmt->execute([$parentId]);
$child = $childStmt->fetch();
$childId = $child['id'] ?? 0;

// Fetch Attendance History
$attendanceRecords = [];
$totalDays = 0; $presentDays = 0; $absentDays = 0; $lateDays = 0;

if ($childId) {
    $attStmt = $db->prepare("
        SELECT a.*, u.name as recorded_by_name
        FROM attendance a
        LEFT JOIN users u ON a.recorded_by = u.id
        WHERE a.student_id = ?
        ORDER BY a.date DESC
    ");
    $attStmt->execute([$childId]);
    $attendanceRecords = $attStmt->fetchAll();

    $totalDays = count($attendanceRecords);
    foreach ($attendanceRecords as $ar) {
        if ($ar['status'] === 'present') $presentDays++;
        elseif ($ar['status'] === 'absent') $absentDays++;
        elseif ($ar['status'] === 'late') $lateDays++;
    }
}

$attendanceRate = ($totalDays > 0) ? round((($presentDays + $lateDays) / $totalDays) * 100) : 100;

require_once dirname(__DIR__) . '/includes/header.php';
?>

<?php if (!$child): ?>
    <div class="card" style="text-align: center; padding: 40px;">
        <p style="color: var(--text-muted);">No student record currently linked to your parent account.</p>
    </div>
<?php else: ?>

    <!-- Attendance Performance Stats -->
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-icon-wrapper stat-icon-primary">📊</div>
            <div class="stat-content">
                <div class="stat-value"><?= $attendanceRate ?>%</div>
                <div class="stat-label">Overall Attendance Rate</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon-wrapper stat-icon-mint">✓</div>
            <div class="stat-content">
                <div class="stat-value" style="color: var(--mint);"><?= $presentDays ?></div>
                <div class="stat-label">Days Present</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon-wrapper stat-icon-accent">⏰</div>
            <div class="stat-content">
                <div class="stat-value" style="color: var(--accent-hover);"><?= $lateDays ?></div>
                <div class="stat-label">Tardy / Late</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon-wrapper stat-icon-rose">✕</div>
            <div class="stat-content">
                <div class="stat-value" style="color: var(--rose);"><?= $absentDays ?></div>
                <div class="stat-label">Days Absent</div>
            </div>
        </div>
    </div>

    <!-- Attendance Log Card -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <span>📅</span> Complete Daily Attendance Log: <?= htmlspecialchars($child['first_name'] . ' ' . $child['last_name']) ?>
                <span class="badge badge-primary"><?= $totalDays ?> Days Recorded</span>
            </h3>
            <button class="btn btn-secondary btn-sm" onclick="triggerPrint()">
                🖨️ Print Attendance
            </button>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date & Day</th>
                        <th>Attendance Status</th>
                        <th>Time In (Arrival)</th>
                        <th>Time Out (Dismissal)</th>
                        <th>Classroom Remarks / Notes</th>
                        <th>Recorded By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($attendanceRecords)): ?>
                        <tr><td colspan="6" style="text-align: center; padding: 24px; color: var(--text-muted);">No attendance entries recorded yet for this school term.</td></tr>
                    <?php else: ?>
                        <?php foreach ($attendanceRecords as $att): ?>
                            <tr>
                                <td>
                                    <strong><?= formatDate($att['date']) ?></strong><br>
                                    <small style="color: var(--text-muted);"><?= date('l', strtotime($att['date'])) ?></small>
                                </td>
                                <td>
                                    <?php if ($att['status'] === 'present'): ?>
                                        <span class="badge badge-success">PRESENT</span>
                                    <?php elseif ($att['status'] === 'late'): ?>
                                        <span class="badge badge-warning">LATE</span>
                                    <?php elseif ($att['status'] === 'absent'): ?>
                                        <span class="badge badge-danger">ABSENT</span>
                                    <?php else: ?>
                                        <span class="badge badge-info"><?= strtoupper($att['status']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= $att['time_in'] ? formatTime($att['time_in']) : '<span style="color:var(--text-muted);">--:--</span>' ?>
                                </td>
                                <td>
                                    <?= $att['time_out'] ? formatTime($att['time_out']) : '<span style="color:var(--text-muted);">In Class / Standard</span>' ?>
                                </td>
                                <td>
                                    <div style="font-size: 0.88rem; color: var(--text-secondary);">
                                        <?= htmlspecialchars($att['remarks'] ?: 'Attended classroom instructions.') ?>
                                    </div>
                                </td>
                                <td>
                                    <small style="color: var(--text-muted);"><?= htmlspecialchars($att['recorded_by_name'] ?? 'Teacher') ?></small>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php endif; ?>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
