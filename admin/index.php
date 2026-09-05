<?php
/**
 * Admin Dashboard
 * Preschool Monitoring System
 */

$pageTitle = 'Admin Dashboard';
$pageSubtitle = 'Overview of preschool operations, admissions, academic progress & finances';

require_once dirname(__DIR__) . '/includes/auth.php';
checkAuth(['admin']);

$db = getDB();

// 1. Statistics
$totalStudents = $db->query("SELECT COUNT(*) FROM students WHERE enrollment_status = 'enrolled'")->fetchColumn();
$totalTeachers = $db->query("SELECT COUNT(*) FROM users WHERE role = 'teacher' AND status = 'active'")->fetchColumn();
$totalParents = $db->query("SELECT COUNT(*) FROM users WHERE role = 'parent' AND status = 'active'")->fetchColumn();
$pendingApprovals = $db->query("SELECT COUNT(*) FROM users WHERE role = 'parent' AND status = 'pending_approval'")->fetchColumn();

// Attendance Today
$today = date('Y-m-d');
$todayAttPresent = $db->query("SELECT COUNT(*) FROM attendance WHERE date = '$today' AND status IN ('present', 'late')")->fetchColumn();
$todayAttTotal = $db->query("SELECT COUNT(*) FROM attendance WHERE date = '$today'")->fetchColumn();
$attendanceRate = ($todayAttTotal > 0) ? round(($todayAttPresent / $todayAttTotal) * 100) : 100;

// Finance Summary
$totalFeesDue = $db->query("SELECT COALESCE(SUM(amount_due), 0) FROM student_fees")->fetchColumn();
$totalFeesPaid = $db->query("SELECT COALESCE(SUM(amount_paid), 0) FROM student_fees")->fetchColumn();
$totalOutstanding = $totalFeesDue - $totalFeesPaid;

// Students Needing Academic Support
$needsSupportCount = $db->query("SELECT COUNT(DISTINCT student_id) FROM academic_assessments WHERE needs_intervention = 1")->fetchColumn();

// Recent Students
$recentStudents = $db->query("
    SELECT s.*, c.name as class_name, u.name as parent_name 
    FROM students s
    LEFT JOIN classrooms c ON s.classroom_id = c.id
    LEFT JOIN users u ON s.parent_id = u.id
    WHERE s.enrollment_status = 'enrolled'
    ORDER BY s.id DESC LIMIT 5
")->fetchAll();

// Upcoming Events
$upcomingEvents = $db->query("
    SELECT * FROM events 
    WHERE event_date >= '$today' 
    ORDER BY event_date ASC LIMIT 4
")->fetchAll();

// Recent Logs
$recentLogs = $db->query("
    SELECT l.*, u.name as user_name, u.role as user_role 
    FROM activity_logs l 
    LEFT JOIN users u ON l.user_id = u.id 
    ORDER BY l.id DESC LIMIT 6
")->fetchAll();

require_once dirname(__DIR__) . '/includes/header.php';
?>

<!-- Metric Summary Cards -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon-wrapper stat-icon-primary">👶</div>
        <div class="stat-content">
            <div class="stat-value"><?= number_format($totalStudents) ?></div>
            <div class="stat-label">Enrolled Students</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon-wrapper stat-icon-mint">👩‍🏫</div>
        <div class="stat-content">
            <div class="stat-value"><?= number_format($totalTeachers) ?></div>
            <div class="stat-label">Active Teachers</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon-wrapper stat-icon-accent">
            <?= $pendingApprovals > 0 ? '⚠️' : '✅' ?>
        </div>
        <div class="stat-content">
            <div class="stat-value"><?= number_format($pendingApprovals) ?></div>
            <div class="stat-label">
                Pending Parent Approvals
                <?php if ($pendingApprovals > 0): ?>
                    <a href="approvals.php" class="text-danger fw-bold" style="font-size:0.75rem; display:block;">Review Now &rarr;</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon-wrapper stat-icon-sky">📊</div>
        <div class="stat-content">
            <div class="stat-value"><?= $attendanceRate ?>%</div>
            <div class="stat-label">Today's Attendance Rate</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon-wrapper stat-icon-mint">💵</div>
        <div class="stat-content">
            <div class="stat-value"><?= formatMoney($totalFeesPaid) ?></div>
            <div class="stat-label">Total Fees Collected</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon-wrapper stat-icon-rose">📈</div>
        <div class="stat-content">
            <div class="stat-value" style="color: var(--rose);"><?= formatMoney($totalOutstanding) ?></div>
            <div class="stat-label">Outstanding Balances</div>
        </div>
    </div>
</div>

<!-- Quick Action Shortcuts -->
<div class="card" style="background: linear-gradient(135deg, #FFFFFF, #F8FAFC);">
    <div class="card-header" style="margin-bottom: 12px; padding-bottom: 10px;">
        <h3 class="card-title">⚡ Quick Administrative Actions</h3>
    </div>
    <div class="d-flex gap-3" style="flex-wrap: wrap;">
        <a href="students.php?action=new" class="btn btn-primary">
            <span>+</span> Admit New Student
        </a>
        <a href="fees.php?action=log" class="btn btn-mint">
            <span>💳</span> Log Fee Payment
        </a>
        <a href="events.php?action=new" class="btn btn-accent">
            <span>🎈</span> Add School Event
        </a>
        <a href="progress.php" class="btn btn-secondary">
            <span>⭐</span> Review Academic Radar
            <?php if ($needsSupportCount > 0): ?>
                <span class="badge badge-warning" style="margin-left: 4px;"><?= $needsSupportCount ?> Needs Support</span>
            <?php endif; ?>
        </a>
        <a href="reports.php" class="btn btn-secondary">
            <span>📄</span> Generate Official Reports
        </a>
    </div>
</div>

<!-- Main Two-Column Grid -->
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
    <!-- Left Column: Recent Student Enrollments -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">👶 Recent Student Enrollments</h3>
            <a href="students.php" class="btn btn-secondary btn-sm">View All Students &rarr;</a>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Student Name</th>
                        <th>Classroom</th>
                        <th>Parent / Guardian</th>
                        <th>Admission Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentStudents)): ?>
                        <tr><td colspan="5" style="text-align:center; padding: 24px; color: var(--text-muted);">No student records found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentStudents as $s): ?>
                            <tr>
                                <td>
                                    <div class="user-cell">
                                        <div class="user-cell-avatar">
                                            <?= strtoupper(substr($s['first_name'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div class="user-cell-name"><?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?></div>
                                            <div class="user-cell-sub"><?= htmlspecialchars($s['lrn'] ?? 'No LRN') ?> &bull; <?= htmlspecialchars($s['gender']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="badge badge-primary"><?= htmlspecialchars($s['class_name'] ?? 'Unassigned') ?></span></td>
                                <td><?= htmlspecialchars($s['parent_name'] ?? 'Not Linked') ?></td>
                                <td><?= formatDate($s['admission_date']) ?></td>
                                <td><span class="badge badge-success"><?= strtoupper($s['enrollment_status']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Right Column: Upcoming Events & System Activity Logs -->
    <div>
        <!-- Upcoming Events Card -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">🎈 Upcoming Activities</h3>
                <a href="events.php" class="btn btn-secondary btn-sm">Manage</a>
            </div>
            <?php if (empty($upcomingEvents)): ?>
                <p style="color: var(--text-muted); font-size: 0.88rem;">No upcoming events scheduled.</p>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <?php foreach ($upcomingEvents as $evt): ?>
                        <div style="padding: 12px; background: var(--bg-card-subtle); border-radius: var(--radius-md); border-left: 4px solid var(--primary);">
                            <div class="d-flex justify-between align-center" style="margin-bottom: 4px;">
                                <strong style="font-size: 0.9rem; color: var(--text-primary);"><?= htmlspecialchars($evt['title']) ?></strong>
                                <span class="badge badge-info" style="font-size: 0.68rem;"><?= htmlspecialchars($evt['event_type']) ?></span>
                            </div>
                            <div style="font-size: 0.78rem; color: var(--text-muted); display: flex; gap: 10px;">
                                <span>📅 <?= formatDate($evt['event_date']) ?></span>
                                <span>⏰ <?= formatTime($evt['start_time']) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Recent Activity Logs Card -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">📜 System Activity Trail</h3>
                <a href="logs.php" class="btn btn-secondary btn-sm">All Logs</a>
            </div>
            <div style="display: flex; flex-direction: column; gap: 10px; font-size: 0.84rem;">
                <?php foreach ($recentLogs as $log): ?>
                    <div style="border-bottom: 1px solid var(--border-subtle); padding-bottom: 8px;">
                        <div class="d-flex justify-between">
                            <strong style="color: var(--text-primary);"><?= htmlspecialchars($log['action']) ?></strong>
                            <small style="color: var(--text-muted);"><?= date('h:i A', strtotime($log['created_at'])) ?></small>
                        </div>
                        <div style="color: var(--text-secondary); font-size: 0.78rem;">
                            <?= htmlspecialchars($log['details']) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
