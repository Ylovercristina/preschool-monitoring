<?php
/**
 * Classroom School Events & Activities (Teacher)
 * Preschool Monitoring System
 * Fulfills: "Event/Activity Management: As a teacher, I want to notify the parents/Guardians
 * of the students about what events is happening in the school."
 */

$pageTitle = 'School Events & Parent Notifications';
$pageSubtitle = 'Review scheduled school events, track upcoming activity details and dispatch notices to classroom parents';

require_once dirname(__DIR__) . '/includes/auth.php';
checkAuth(['teacher']);

$db = getDB();
$teacherId = $_SESSION['user_id'];

// Get assigned classroom
$classStmt = $db->prepare("SELECT * FROM classrooms WHERE teacher_id = ? LIMIT 1");
$classStmt->execute([$teacherId]);
$classroom = $classStmt->fetch();
$classroomId = $classroom['id'] ?? 0;

// Handle 1-click notify
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $eventId = (int)$_POST['event_id'];
    $evt = $db->prepare("SELECT * FROM events WHERE id = ?");
    $evt->execute([$eventId]);
    $eventData = $evt->fetch();

    if ($eventData) {
        $parents = $db->query("
            SELECT DISTINCT u.id 
            FROM users u 
            JOIN students s ON s.parent_id = u.id 
            WHERE s.classroom_id = $classroomId AND u.status = 'active'
        ")->fetchAll();

        $msg = "School Activity Notice from Teacher: '{$eventData['title']}' is scheduled on " . formatDate($eventData['event_date']) . " at " . formatTime($eventData['start_time']) . " ({$eventData['location']}). {$eventData['description']}";
        $notifStmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, 'event', 'parent/calendar.php')");

        foreach ($parents as $p) {
            $notifStmt->execute([$p['id'], "Class Activity: {$eventData['title']}", $msg]);
        }

        logActivity('Event Notice Dispatched', "Teacher notified classroom parents about event '{$eventData['title']}'");
        setFlash('success', "Parents notified successfully about '{$eventData['title']}'!");
        header('Location: events.php');
        exit;
    }
}

// Fetch Events
$events = $db->query("SELECT * FROM events ORDER BY event_date ASC")->fetchAll();

// Prepare JSON for calendar
$eventsJson = [];
foreach ($events as $e) {
    $eventsJson[] = [
        'title' => $e['title'],
        'date' => $e['event_date'],
        'type' => $e['event_type'],
        'description' => $e['description']
    ];
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
    <!-- Interactive Calendar Component -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <span>🗓️</span> School Activity Calendar
            </h3>
        </div>
        <div id="schoolCalendar"></div>
    </div>

    <!-- Events List with Notify Action -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <span>🎈</span> Upcoming School Events
                <span class="badge badge-primary"><?= count($events) ?> Scheduled</span>
            </h3>
        </div>

        <div style="display: flex; flex-direction: column; gap: 14px;">
            <?php foreach ($events as $e): 
                $isPast = strtotime($e['event_date']) < strtotime(date('Y-m-d'));
            ?>
                <div style="
                    padding: 16px;
                    border-radius: var(--radius-md);
                    border: 1px solid var(--border-color);
                    background: <?= $isPast ? '#F8FAFC' : '#FFFFFF' ?>;
                    opacity: <?= $isPast ? '0.7' : '1' ?>;
                ">
                    <div class="d-flex justify-between align-center" style="margin-bottom: 6px;">
                        <span class="badge <?= $e['event_type'] === 'Holiday' ? 'badge-danger' : 'badge-info' ?>">
                            <?= htmlspecialchars($e['event_type']) ?>
                        </span>
                        <strong style="font-size: 0.85rem; color: var(--primary);">
                            📅 <?= formatDate($e['event_date']) ?>
                        </strong>
                    </div>

                    <h4 style="margin: 0 0 6px; font-size: 1.05rem;"><?= htmlspecialchars($e['title']) ?></h4>
                    <p style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 10px;">
                        <?= htmlspecialchars($e['description'] ?: 'School-wide preschool activity.') ?>
                    </p>

                    <div class="d-flex justify-between align-center" style="font-size: 0.78rem; color: var(--text-muted); border-top: 1px solid var(--border-subtle); padding-top: 10px;">
                        <div>
                            <span>⏰ <?= formatTime($e['start_time']) ?> - <?= formatTime($e['end_time']) ?></span> &bull; 
                            <span>📍 <?= htmlspecialchars($e['location'] ?: 'Preschool') ?></span>
                        </div>

                        <?php if (!$isPast): ?>
                            <form method="POST" action="events.php" style="margin: 0;">
                                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                <input type="hidden" name="event_id" value="<?= $e['id'] ?>">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    📢 Notify Parents
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    initPreschoolCalendar('schoolCalendar', <?= json_encode($eventsJson) ?>);
});
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
