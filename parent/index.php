<?php
/**
 * Parent Child Overview Dashboard
 * Preschool Monitoring System
 */

$pageTitle = 'Parent Portal';
$pageSubtitle = 'Monitor your child\'s preschool progress, daily attendance, fees, safety and communications';

require_once dirname(__DIR__) . '/includes/auth.php';
checkAuth(['parent']);

$db = getDB();
$parentId = $_SESSION['user_id'];

// Fetch Linked Child(ren)
$childrenStmt = $db->prepare("
    SELECT s.*, c.name as class_name, c.room_number, u.name as teacher_name, u.phone as teacher_phone, u.email as teacher_email
    FROM students s
    LEFT JOIN classrooms c ON s.classroom_id = c.id
    LEFT JOIN users u ON c.teacher_id = u.id
    WHERE s.parent_id = ? AND s.enrollment_status = 'enrolled'
    ORDER BY s.id ASC
");
$childrenStmt->execute([$parentId]);
$children = $childrenStmt->fetchAll();

$child = $children[0] ?? null;
$childId = $child['id'] ?? 0;

// Metrics for Child
$today = date('Y-m-d');
$todayAtt = null;
$attStats = ['present' => 0, 'total' => 0];
$milestonesMastered = 0;
$milestonesTotal = 0;
$feesBalance = 0;
$authorizedGuardiansCount = 0;

if ($childId) {
    // Today's attendance
    $tAttStmt = $db->prepare("SELECT * FROM attendance WHERE student_id = ? AND date = ?");
    $tAttStmt->execute([$childId, $today]);
    $todayAtt = $tAttStmt->fetch();

    // Attendance stats
    $totAttStmt = $db->prepare("SELECT COUNT(*) FROM attendance WHERE student_id = ?");
    $totAttStmt->execute([$childId]);
    $attStats['total'] = (int)$totAttStmt->fetchColumn();

    $prsAttStmt = $db->prepare("SELECT COUNT(*) FROM attendance WHERE student_id = ? AND status IN ('present', 'late')");
    $prsAttStmt->execute([$childId]);
    $attStats['present'] = (int)$prsAttStmt->fetchColumn();

    // Milestones
    $mCountStmt = $db->prepare("SELECT COUNT(*) FROM student_milestones WHERE student_id = ? AND rating = 'mastered'");
    $mCountStmt->execute([$childId]);
    $milestonesMastered = (int)$mCountStmt->fetchColumn();

    $mTotStmt = $db->prepare("SELECT COUNT(*) FROM student_milestones WHERE student_id = ?");
    $mTotStmt->execute([$childId]);
    $milestonesTotal = (int)$mTotStmt->fetchColumn();

    // Fee Balance
    $fStmt = $db->prepare("SELECT COALESCE(SUM(amount_due - amount_paid), 0) FROM student_fees WHERE student_id = ?");
    $fStmt->execute([$childId]);
    $feesBalance = (float)$fStmt->fetchColumn();

    // Guardians
    $gStmt = $db->prepare("SELECT COUNT(*) FROM authorized_pickups WHERE student_id = ? AND is_active = 1");
    $gStmt->execute([$childId]);
    $authorizedGuardiansCount = (int)$gStmt->fetchColumn();
}

$attendanceRate = ($attStats['total'] > 0) ? round(($attStats['present'] / $attStats['total']) * 100) : 100;

// Recent Notifications for Parent
$recentNotifs = $db->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 4");
$recentNotifs->execute([$parentId]);
$notifications = $recentNotifs->fetchAll();

// Upcoming Events
$events = $db->query("SELECT * FROM events WHERE event_date >= date('now') ORDER BY event_date ASC LIMIT 3")->fetchAll();

require_once dirname(__DIR__) . '/includes/header.php';
?>

<?php if (!$child): ?>
    <div class="card" style="text-align: center; padding: 48px;">
        <div style="font-size: 2.5rem; margin-bottom: 12px;"><i class="bi bi-info-circle"></i></div>
        <h3>Welcome to <?= APP_NAME ?>!</h3>
        <p style="color: var(--text-secondary); max-width: 500px; margin: 8px auto 20px;">
            Your account is active. Once the school administration links your enrolled child's file to your parent account, your child's milestones, attendance, and safety dashboard will appear here.
        </p>
        <span class="badge badge-info" style="font-size: 0.85rem; padding: 8px 16px;">
            Account Status: Active & Verified
        </span>
    </div>
<?php else: ?>

    <!-- Child Welcome Hero Card -->
    <div class="card" style="background: #F0F9FF; border: 1px solid #BFE7FF; margin-bottom: 24px;">
        <div class="d-flex justify-between align-center" style="flex-wrap: wrap; gap: 20px;">
            <div class="d-flex align-center gap-4">
                <div style="width: 64px; height: 64px; border-radius: 50%; background: var(--primary); color: #FFFFFF; font-size: 1.6rem; font-weight: 800; display: flex; align-items: center; justify-content: center;">
                    <?= strtoupper(substr($child['first_name'], 0, 1)) ?>
                </div>
                <div>
                    <span class="badge" style="background: #E0F2FE; color: var(--primary); margin-bottom: 6px;">
                        <i class="bi bi-person"></i> <?= htmlspecialchars($child['gender']) ?>
                    </span>
                    <h2 style="color: var(--primary); font-size: 1.6rem; margin: 0;">
                        <?= htmlspecialchars($child['first_name'] . ' ' . $child['last_name']) ?>
                    </h2>
                    <div style="color: var(--text-secondary); font-size: 0.9rem; margin-top: 4px;">
                        <?= htmlspecialchars($child['class_name'] ?? 'Class') ?> (<?= htmlspecialchars($child['room_number'] ?? 'Room') ?>) • Teacher: <strong><?= htmlspecialchars($child['teacher_name'] ?? 'Assigned') ?></strong>
                    </div>
                </div>
            </div>

            <!-- Today's Attendance Quick Pill -->
            <div style="background: #FFFFFF; padding: 14px 20px; border-radius: var(--radius-lg); text-align: center; border: 1px solid #E5E7EB;">
                <div style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted);">Today's Attendance</div>
                <?php if ($todayAtt && in_array($todayAtt['status'], ['present', 'late'])): ?>
                    <div style="font-size: 1.25rem; font-weight: 800; color: var(--mint); margin-top: 2px;">
                        <i class="bi bi-check-circle"></i> <?= strtoupper($todayAtt['status']) ?>
                    </div>
                    <small style="font-size: 0.75rem; color: var(--text-muted);">In at <?= formatTime($todayAtt['time_in']) ?></small>
                <?php elseif ($todayAtt && $todayAtt['status'] === 'absent'): ?>
                    <div style="font-size: 1.25rem; font-weight: 800; color: var(--rose); margin-top: 2px;">
                        <i class="bi bi-x-circle"></i> ABSENT
                    </div>
                    <small style="font-size: 0.75rem; color: var(--text-muted);">Marked absent</small>
                <?php else: ?>
                    <div style="font-size: 1.15rem; font-weight: 700; color: var(--text-muted); margin-top: 2px;">
                        Pending Check-in
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Quick Metric Cards -->
    <div class="stat-grid">
        <div class="stat-card">
            <i class="stat-icon-wrapper bi bi-calendar"></i>
            <div class="stat-content">
                <div class="stat-value"><?= $attendanceRate ?>%</div>
                <div class="stat-label">Attendance Rate</div>
            </div>
        </div>

        <div class="stat-card">
            <i class="stat-icon-wrapper bi bi-star-fill"></i>
            <div class="stat-content">
                <div class="stat-value" style="color: var(--mint);"><?= $milestonesMastered ?> / <?= $milestonesTotal ?></div>
                <div class="stat-label">Milestones Mastered</div>
            </div>
        </div>

        <div class="stat-card">
            <i class="stat-icon-wrapper bi bi-credit-card"></i>
            <div class="stat-content">
                <div class="stat-value" style="color: <?= $feesBalance > 0 ? 'var(--rose)' : 'var(--mint)' ?>;">
                    <?= formatMoney($feesBalance) ?>
                </div>
                <div class="stat-label"><?= $feesBalance > 0 ? 'Outstanding Balance' : 'Fees Settled' ?></div>
            </div>
        </div>

        <div class="stat-card">
            <i class="stat-icon-wrapper bi bi-shield"></i>
            <div class="stat-content">
                <div class="stat-value"><?= $authorizedGuardiansCount ?></div>
                <div class="stat-label">Authorized Pickups</div>
            </div>
        </div>
    </div>

    <!-- Two-Column Content Grid -->
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
        <!-- Left Column: Child Operations & Health Summary -->
        <div>
            <!-- Quick Feature Actions -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="bi bi-lightning-charge"></i> Quick Features</h3>
                </div>
                <div class="d-flex gap-3" style="flex-wrap: wrap;">
                    <a href="progress.php" class="btn btn-primary">
                        <i class="bi bi-star-fill"></i> Milestones
                    </a>
                    <a href="attendance.php" class="btn btn-primary">
                        <i class="bi bi-calendar"></i> Attendance
                    </a>
                    <a href="pickups.php" class="btn btn-primary">
                        <i class="bi bi-shield"></i> Pickup PIN
                    </a>
                    <a href="fees.php" class="btn btn-primary">
                        <i class="bi bi-credit-card"></i> Fees
                    </a>
                    <a href="messages.php" class="btn btn-primary">
                        <i class="bi bi-chat-dots"></i> Messages
                    </a>
                    <a href="calendar.php" class="btn btn-primary">
                        <i class="bi bi-calendar2"></i> Calendar
                    </a>
                </div>
            </div>

            <!-- Health & Safety Card -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="bi bi-heart-pulse"></i> Safety & Medical Profile
                    </h3>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div style="background: #FFF1F2; border-left: 4px solid var(--rose); padding: 14px; border-radius: var(--radius-sm);">
                        <strong style="color: var(--rose-dark);"><i class="bi bi-exclamation-triangle"></i> Allergies:</strong>
                        <p style="margin: 4px 0 0; color: #881337; font-size: 0.9rem;">
                            <?= htmlspecialchars($child['allergies'] ?: 'None recorded.') ?>
                        </p>
                    </div>

                    <div style="background: #F0FDF4; border-left: 4px solid var(--mint); padding: 14px; border-radius: var(--radius-sm);">
                        <strong style="color: var(--mint-dark);"><i class="bi bi-card-text"></i> Medical Notes:</strong>
                        <p style="margin: 4px 0 0; color: #064E3B; font-size: 0.9rem;">
                            <?= htmlspecialchars($child['medical_notes'] ?: 'None on file.') ?>
                        </p>
                    </div>
                </div>

                <div style="margin-top: 14px; padding: 12px; background: var(--bg-card-subtle); border-radius: var(--radius-sm); font-size: 0.88rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                    <div>
                        Emergency: <strong><?= htmlspecialchars($child['emergency_contact_name'] ?? 'Not listed') ?></strong> (<?= htmlspecialchars($child['emergency_contact_phone'] ?? '') ?>)
                    </div>
                    <span class="badge badge-primary">Blood Type: <?= htmlspecialchars($child['blood_type'] ?: 'N/A') ?></span>
                </div>
            </div>
        </div>

        <!-- Right Column: Notifications Feed & Upcoming Events -->
        <div>
            <!-- Notifications Feed Card -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="bi bi-bell"></i> Notifications
                    </h3>
                    <a href="notifications.php" class="btn btn-secondary btn-sm">All &rarr;</a>
                </div>
                <?php if (empty($notifications)): ?>
                    <p style="color: var(--text-muted); font-size: 0.88rem; text-align: center; padding: 14px;">No new notifications.</p>
                <?php else: ?>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <?php foreach ($notifications as $notif): ?>
                            <div style="padding: 10px 12px; background: var(--bg-card-subtle); border-radius: var(--radius-sm); font-size: 0.84rem; border-left: 3px solid var(--primary);">
                                <strong style="color: var(--text-primary); display: block; margin-bottom: 2px;">
                                    <?= htmlspecialchars($notif['title']) ?>
                                </strong>
                                <div style="color: var(--text-secondary);"><?= htmlspecialchars($notif['message']) ?></div>
                                <small style="color: var(--text-muted); display: block; margin-top: 4px;"><?= formatDate($notif['created_at'], 'M d, h:i A') ?></small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Upcoming Activities Card -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="bi bi-calendar-event"></i> School Activities
                    </h3>
                    <a href="calendar.php" class="btn btn-secondary btn-sm">Calendar</a>
                </div>
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <?php foreach ($events as $evt): ?>
                        <div style="padding: 10px; background: var(--bg-card-subtle); border-radius: var(--radius-sm); font-size: 0.85rem;">
                            <strong><?= htmlspecialchars($evt['title']) ?></strong>
                            <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 2px;">
                                <i class="bi bi-calendar"></i> <?= formatDate($evt['event_date']) ?> • <i class="bi bi-clock"></i> <?= formatTime($evt['start_time']) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

<?php endif; ?>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
