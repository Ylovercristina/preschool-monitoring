<?php
/**
 * Teacher Classroom Dashboard
 * Preschool Monitoring System
 */

$pageTitle = 'Teacher Dashboard';
$pageSubtitle = 'Daily classroom monitoring, attendance, milestone assessments & child safety controls';

require_once dirname(__DIR__) . '/includes/auth.php';
checkAuth(['teacher']);

$db = getDB();
$teacherId = $_SESSION['user_id'];

// Get assigned classroom
$classStmt = $db->prepare("SELECT * FROM classrooms WHERE teacher_id = ? LIMIT 1");
$classStmt->execute([$teacherId]);
$classroom = $classStmt->fetch();
$classroomId = $classroom['id'] ?? 0;

// Classroom Metrics
$today = date('Y-m-d');
$enrolledStudents = $db->query("SELECT COUNT(*) FROM students WHERE classroom_id = $classroomId AND enrollment_status = 'enrolled'")->fetchColumn();

// Today's attendance
$attPresent = $db->query("SELECT COUNT(*) FROM attendance WHERE classroom_id = $classroomId AND date = '$today' AND status = 'present'")->fetchColumn();
$attLate = $db->query("SELECT COUNT(*) FROM attendance WHERE classroom_id = $classroomId AND date = '$today' AND status = 'late'")->fetchColumn();
$attAbsent = $db->query("SELECT COUNT(*) FROM attendance WHERE classroom_id = $classroomId AND date = '$today' AND status = 'absent'")->fetchColumn();
$attRecorded = $db->query("SELECT COUNT(*) FROM attendance WHERE classroom_id = $classroomId AND date = '$today'")->fetchColumn();

// Unread messages from parents
$unreadMsgs = $db->query("SELECT COUNT(*) FROM messages WHERE receiver_id = $teacherId AND is_read = 0")->fetchColumn();

// Students requiring support in this class
$supportCount = $db->query("
    SELECT COUNT(DISTINCT s.id) 
    FROM students s
    JOIN academic_assessments a ON a.student_id = s.id
    WHERE s.classroom_id = $classroomId AND a.needs_intervention = 1
")->fetchColumn();

// Class Students Roster for Today's Quick Actions
$students = $db->query("
    SELECT s.*, u.name as parent_name, u.phone as parent_phone,
           (SELECT status FROM attendance a WHERE a.student_id = s.id AND a.date = '$today') as today_status,
           (SELECT time_in FROM attendance a WHERE a.student_id = s.id AND a.date = '$today') as today_time_in,
           (SELECT COUNT(*) FROM authorized_pickups ap WHERE ap.student_id = s.id AND ap.is_active = 1) as pickups_count
    FROM students s
    LEFT JOIN users u ON s.parent_id = u.id
    WHERE s.classroom_id = $classroomId AND s.enrollment_status = 'enrolled'
    ORDER BY s.last_name ASC
")->fetchAll();

// Upcoming activities
$upcomingEvents = $db->query("SELECT * FROM events WHERE event_date >= '$today' ORDER BY event_date ASC LIMIT 3")->fetchAll();

require_once dirname(__DIR__) . '/includes/header.php';
?>

<!-- Classroom Greeting Banner -->
<div class="card" style="background: linear-gradient(135deg, #EEF2FF 0%, #FAF5FF 100%); border-color: #C7D2FE; margin-bottom: 24px;">
    <div class="d-flex justify-between align-center" style="flex-wrap: wrap; gap: 16px;">
        <div>
            <span class="badge badge-primary" style="margin-bottom: 6px;">MY CLASSROOM</span>
            <h2 style="font-size: 1.6rem; color: var(--primary); margin: 0;">
                <?= htmlspecialchars($classroom['name'] ?? 'Classroom Not Assigned') ?>
            </h2>
            <p style="color: var(--text-secondary); margin-top: 4px; font-size: 0.9rem;">
                <?= htmlspecialchars($classroom['room_number'] ?? 'Room') ?> &bull; Room Capacity: <?= htmlspecialchars($classroom['capacity'] ?? '20') ?> &bull; Today: <strong><?= date('l, F d, Y') ?></strong>
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="attendance.php" class="btn btn-primary">
                <span>📋</span> Daily Attendance
            </a>
            <a href="emergency.php" class="btn btn-danger">
                <span>🚨</span> Emergency Broadcast
            </a>
        </div>
    </div>
</div>

<!-- Stat Grid -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon-wrapper stat-icon-primary">👶</div>
        <div class="stat-content">
            <div class="stat-value"><?= $enrolledStudents ?></div>
            <div class="stat-label">Enrolled in Class</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon-wrapper stat-icon-mint">✓</div>
        <div class="stat-content">
            <div class="stat-value" style="color: var(--mint);"><?= $attPresent ?></div>
            <div class="stat-label">Present Today</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon-wrapper stat-icon-accent">⏰</div>
        <div class="stat-content">
            <div class="stat-value" style="color: var(--accent-hover);"><?= $attLate ?></div>
            <div class="stat-label">Tardy / Late</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon-wrapper stat-icon-rose">✕</div>
        <div class="stat-content">
            <div class="stat-value" style="color: var(--rose);"><?= $attAbsent ?></div>
            <div class="stat-label">Absent Today</div>
        </div>
    </div>
</div>

<!-- Classroom Safety & Operations Center -->
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
    <!-- Left Column: Class Roster & Today's Safety Status -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <span>🎒</span> Today's Classroom Roster & Safety Status
            </h3>
            <a href="attendance.php" class="btn btn-secondary btn-sm">Update Attendance &rarr;</a>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Pupil Name</th>
                        <th>Today's Attendance</th>
                        <th>Time In</th>
                        <th>Authorized Pickups</th>
                        <th>Release Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($students)): ?>
                        <tr><td colspan="5" style="text-align:center; padding: 24px; color: var(--text-muted);">No enrolled students in this classroom yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($students as $s): ?>
                            <tr>
                                <td>
                                    <div class="user-cell">
                                        <div class="user-cell-avatar">
                                            <?= strtoupper(substr($s['first_name'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <strong class="user-cell-name"><?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?></strong>
                                            <div class="user-cell-sub">Parent: <?= htmlspecialchars($s['parent_name'] ?? 'Not Linked') ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($s['today_status'] === 'present'): ?>
                                        <span class="badge badge-success">PRESENT</span>
                                    <?php elseif ($s['today_status'] === 'late'): ?>
                                        <span class="badge badge-warning">LATE</span>
                                    <?php elseif ($s['today_status'] === 'absent'): ?>
                                        <span class="badge badge-danger">ABSENT</span>
                                    <?php else: ?>
                                        <span class="badge badge-neutral">NOT RECORDED</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= $s['today_time_in'] ? formatTime($s['today_time_in']) : '<span style="color:var(--text-muted);">--:--</span>' ?>
                                </td>
                                <td>
                                    <span class="badge badge-info">🛡️ <?= (int)$s['pickups_count'] ?> Guardians</span>
                                </td>
                                <td>
                                    <a href="pickups.php?student_id=<?= $s['id'] ?>" class="btn btn-primary btn-sm">
                                        Verify Pickup &rarr;
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Right Column: Quick Notifications & Upcoming Events -->
    <div>
        <!-- Quick Action Tools -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">⚡ Quick Classroom Actions</h3>
            </div>
            <div style="display: flex; flex-direction: column; gap: 10px;">
                <a href="progress.php" class="btn btn-subtle" style="justify-content: flex-start;">
                    <span>🌟</span> Record Student Milestones
                </a>
                <a href="pickups.php" class="btn btn-subtle" style="justify-content: flex-start;">
                    <span>🛡️</span> Verify Child Pickup Release
                </a>
                <a href="messages.php" class="btn btn-subtle" style="justify-content: flex-start;">
                    <span>💬</span> Open Parent Chat
                    <?php if ($unreadMsgs > 0): ?>
                        <span class="badge badge-danger" style="margin-left: auto;"><?= $unreadMsgs ?> New</span>
                    <?php endif; ?>
                </a>
                <a href="reminders.php" class="btn btn-subtle" style="justify-content: flex-start;">
                    <span>🔔</span> Send Fee / Activity Reminder
                </a>
                <a href="emergency.php" class="btn btn-danger" style="justify-content: flex-start;">
                    <span>🚨</span> Send Emergency Broadcast
                </a>
            </div>
        </div>

        <!-- Upcoming Activities Card -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">🎈 Upcoming Activities</h3>
                <a href="events.php" class="btn btn-secondary btn-sm">Notify</a>
            </div>
            <div style="display: flex; flex-direction: column; gap: 10px;">
                <?php foreach ($upcomingEvents as $evt): ?>
                    <div style="background: var(--bg-card-subtle); padding: 10px; border-radius: var(--radius-sm); border-left: 3px solid var(--primary); font-size: 0.88rem;">
                        <strong><?= htmlspecialchars($evt['title']) ?></strong>
                        <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 2px;">
                            📅 <?= formatDate($evt['event_date']) ?> &bull; ⏰ <?= formatTime($evt['start_time']) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
