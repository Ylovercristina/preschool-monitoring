<?php
/**
 * Send Reminders to Parents (Fees & Activities)
 * Preschool Monitoring System
 * Fulfills:
 * - "Fee Management (Teacher): As a teacher, I want to notify the parents/guardians if there are school fees
 *    that need to be paid and send them notifications or updates about their current fee status and the amount they need to pay."
 * - "Send reminders to parents: As a teacher, I want to send reminders to parents to inform them about the school fee
 *    and upcoming activities in the school."
 */

$pageTitle = 'Parent Reminders Center';
$pageSubtitle = 'Notify parents of pending school fee balances and upcoming classroom activities';

require_once dirname(__DIR__) . '/includes/auth.php';
checkAuth(['teacher']);

$db = getDB();
$teacherId = $_SESSION['user_id'];

// Get assigned classroom
$classStmt = $db->prepare("SELECT * FROM classrooms WHERE teacher_id = ? LIMIT 1");
$classStmt->execute([$teacherId]);
$classroom = $classStmt->fetch();
$classroomId = $classroom['id'] ?? 0;

// Handle Sending Reminders
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $reminderType = $_POST['reminder_type'] ?? '';

    if ($reminderType === 'fee') {
        $studentFeeId = (int)$_POST['student_fee_id'];
        $sf = $db->prepare("
            SELECT sf.*, s.first_name, s.last_name, s.parent_id, f.title as fee_title, (sf.amount_due - sf.amount_paid) as balance
            FROM student_fees sf
            JOIN students s ON sf.student_id = s.id
            JOIN fees f ON sf.fee_id = f.id
            WHERE sf.id = ?
        ");
        $sf->execute([$studentFeeId]);
        $data = $sf->fetch();

        if ($data && $data['parent_id']) {
            $formattedBal = formatMoney($data['balance']);
            $msg = "Friendly Reminder from Teacher: {$data['first_name']} has a pending balance of {$formattedBal} for '{$data['fee_title']}'. Please settle at your earliest convenience.";
            
            $db->prepare("INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, 'fee_reminder', 'parent/fees.php')")
               ->execute([$data['parent_id'], "School Fee Balance Notice", $msg]);

            logActivity('Fee Reminder Sent', "Teacher sent fee reminder to parent of {$data['first_name']} ({$formattedBal})");
            setFlash('success', "Fee balance reminder sent successfully to {$data['first_name']}'s parent!");
        }
        header('Location: reminders.php');
        exit;
    } elseif ($reminderType === 'activity') {
        $eventId = (int)$_POST['event_id'];
        $evt = $db->prepare("SELECT * FROM events WHERE id = ?");
        $evt->execute([$eventId]);
        $eData = $evt->fetch();

        if ($eData) {
            // Send to all parents in this classroom
            $parents = $db->query("
                SELECT DISTINCT u.id 
                FROM users u 
                JOIN students s ON s.parent_id = u.id 
                WHERE s.classroom_id = $classroomId AND u.status = 'active'
            ")->fetchAll();

            $msg = "Activity Reminder from Teacher: '{$eData['title']}' is happening on " . formatDate($eData['event_date']) . " at " . formatTime($eData['start_time']) . " ({$eData['location']}).";
            $notifStmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, 'event', 'parent/calendar.php')");

            foreach ($parents as $p) {
                $notifStmt->execute([$p['id'], "Upcoming Activity: {$eData['title']}", $msg]);
            }

            logActivity('Activity Reminder Sent', "Teacher broadcasted activity reminder for '{$eData['title']}'");
            setFlash('success', "Upcoming activity reminder for '{$eData['title']}' sent to all classroom parents!");
        }
        header('Location: reminders.php');
        exit;
    } elseif ($reminderType === 'custom') {
        $title = trim($_POST['title'] ?? 'Classroom Reminder');
        $body = trim($_POST['body'] ?? '');

        if (!empty($body)) {
            $parents = $db->query("
                SELECT DISTINCT u.id 
                FROM users u 
                JOIN students s ON s.parent_id = u.id 
                WHERE s.classroom_id = $classroomId AND u.status = 'active'
            ")->fetchAll();

            $notifStmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, 'info', 'parent/notifications.php')");
            foreach ($parents as $p) {
                $notifStmt->execute([$p['id'], $title, $body]);
            }

            logActivity('Custom Reminder Sent', "Teacher broadcasted classroom notice: '{$title}'");
            setFlash('success', "Classroom announcement sent to all parents!");
        }
        header('Location: reminders.php');
        exit;
    }
}

// 1. Unpaid / Partially Paid Student Fees in this Teacher's Classroom
$pendingFees = $db->query("
    SELECT sf.*, s.first_name, s.last_name, s.lrn, f.title as fee_title, f.due_date,
           (sf.amount_due - sf.amount_paid) as balance, u.name as parent_name, u.phone as parent_phone
    FROM student_fees sf
    JOIN students s ON sf.student_id = s.id
    JOIN fees f ON sf.fee_id = f.id
    LEFT JOIN users u ON s.parent_id = u.id
    WHERE s.classroom_id = $classroomId AND sf.status IN ('unpaid', 'partially_paid') AND s.enrollment_status = 'enrolled'
    ORDER BY sf.status ASC, s.last_name ASC
")->fetchAll();

// 2. Upcoming School Events
$upcomingEvents = $db->query("SELECT * FROM events WHERE event_date >= date('now') ORDER BY event_date ASC")->fetchAll();

require_once dirname(__DIR__) . '/includes/header.php';
?>

<!-- Two-Column Layout -->
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
    <!-- Left Column: Pending Fee Notice Generator -->
    <div>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <span>💰</span> Outstanding School Fees Watchlist (Classroom)
                    <span class="badge badge-warning"><?= count($pendingFees) ?> Unsettled</span>
                </h3>
            </div>
            <p style="font-size: 0.88rem; color: var(--text-muted); margin-bottom: 16px;">
                Teachers can notify parents with 1-click regarding their child's current fee status, amount due, and payment reminders.
            </p>

            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Pupil & Parent</th>
                            <th>Fee Particulars</th>
                            <th>Outstanding Balance</th>
                            <th>Status</th>
                            <th>1-Click Notice</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pendingFees)): ?>
                            <tr><td colspan="5" style="text-align:center; padding: 24px; color: var(--text-muted);">All student fee accounts in your classroom are fully settled!</td></tr>
                        <?php else: ?>
                            <?php foreach ($pendingFees as $pf): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($pf['first_name'] . ' ' . $pf['last_name']) ?></strong><br>
                                        <small style="color: var(--text-muted);">
                                            Parent: <?= htmlspecialchars($pf['parent_name'] ?? 'Not Linked') ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($pf['fee_title']) ?>
                                        <?php if ($pf['due_date']): ?>
                                            <br><small style="color: var(--text-muted);">Due: <?= formatDate($pf['due_date']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong style="color: var(--rose); font-size: 0.95rem;">
                                            <?= formatMoney($pf['balance']) ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <span class="badge <?= $pf['status'] === 'unpaid' ? 'badge-danger' : 'badge-warning' ?>">
                                            <?= strtoupper($pf['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form method="POST" action="reminders.php" style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                            <input type="hidden" name="reminder_type" value="fee">
                                            <input type="hidden" name="student_fee_id" value="<?= $pf['id'] ?>">
                                            <button type="submit" class="btn btn-subtle btn-sm">
                                                🔔 Send Fee Notice
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Right Column: Activity Reminders & Custom Broadcast -->
    <div>
        <!-- Upcoming Activity Reminders -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <span>🎈</span> Send Activity Reminder
                </h3>
            </div>
            <div style="display: flex; flex-direction: column; gap: 14px;">
                <?php if (empty($upcomingEvents)): ?>
                    <p style="color: var(--text-muted); font-size: 0.85rem;">No upcoming activities found.</p>
                <?php else: ?>
                    <?php foreach ($upcomingEvents as $evt): ?>
                        <div style="padding: 12px; background: var(--bg-card-subtle); border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                            <strong style="font-size: 0.92rem; color: var(--text-primary);"><?= htmlspecialchars($evt['title']) ?></strong>
                            <div style="font-size: 0.78rem; color: var(--text-muted); margin: 4px 0 8px;">
                                📅 <?= formatDate($evt['event_date']) ?> &bull; ⏰ <?= formatTime($evt['start_time']) ?>
                            </div>
                            <form method="POST" action="reminders.php">
                                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                <input type="hidden" name="reminder_type" value="activity">
                                <input type="hidden" name="event_id" value="<?= $evt['id'] ?>">
                                <button type="submit" class="btn btn-primary btn-sm" style="width: 100%;">
                                    🔔 Broadcast Activity Reminder
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Custom Notice Composer -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <span>📢</span> Custom Classroom Notice
                </h3>
            </div>
            <form method="POST" action="reminders.php">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="reminder_type" value="custom">

                <div class="form-group">
                    <label class="form-label" for="noticeTitle">Notice Title</label>
                    <input type="text" name="title" id="noticeTitle" class="form-control" placeholder="e.g. Bring Art Smocks Tomorrow" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="noticeBody">Notice Message</label>
                    <textarea name="body" id="noticeBody" class="form-control" rows="3" placeholder="Dear Parents, tomorrow we will have finger-painting..." required></textarea>
                </div>

                <button type="submit" class="btn btn-mint" style="width: 100%;">
                    Send Notice to All Parents
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
