<?php
/**
 * School Activities & Events Calendar (Parent View)
 * Preschool Monitoring System - Connected with Google Calendar
 * Fulfills: "Calendar: As a parent, I want to know the school calendar to track school activities of my child."
 */

$pageTitle = 'School Calendar';
$pageSubtitle = 'Track school activities and events';

require_once dirname(__DIR__) . '/includes/auth.php';
checkAuth(['parent']);

require_once dirname(__DIR__) . '/includes/google-calendar-helper.php';

$db = getDB();

// Fetch local events from database
$events = $db->query("SELECT * FROM events ORDER BY event_date ASC")->fetchAll();

// Fetch Google Calendar events
$googleEvents = [];
try {
    $googleCalendarEvents = getGoogleCalendarEvents();
    foreach ($googleCalendarEvents as $gEvent) {
        $googleEvents[] = formatGoogleEvent($gEvent);
    }
} catch (Exception $e) {
    // Silently fail if Google Calendar is not connected
}

// Merge and format events
$allEvents = array_merge($events, $googleEvents);
usort($allEvents, function($a, $b) {
    $aDate = strtotime($a['event_date'] ?? $a['start_date']);
    $bDate = strtotime($b['event_date'] ?? $b['start_date']);
    return $aDate - $bDate;
});

$eventsJson = [];
foreach ($allEvents as $e) {
    $eventsJson[] = [
        'title' => $e['title'],
        'date' => $e['event_date'] ?? $e['start_date'],
        'type' => $e['event_type'] ?? 'Activity',
        'description' => $e['description'] ?? ''
    ];
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">
    <!-- Interactive Monthly Calendar -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">📅 Calendar</h3>
        </div>
        <div id="parentSchoolCalendar"></div>
    </div>

    <!-- Upcoming Activities Feed -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">🎈 Upcoming Activities</h3>
        </div>

        <div style="display: flex; flex-direction: column; gap: 12px; max-height: 400px; overflow-y: auto;">
            <?php if (empty($allEvents)): ?>
                <p style="color: var(--text-muted); text-align: center; padding: 24px; font-size: 0.9rem;">No upcoming activities scheduled.</p>
            <?php else: ?>
                <?php foreach (array_slice($allEvents, 0, 10) as $e): 
                    $eventDate = $e['event_date'] ?? $e['start_date'];
                    $isPast = strtotime($eventDate) < strtotime(date('Y-m-d'));
                ?>
                    <div style="
                        padding: 14px;
                        border-radius: var(--radius-md);
                        border: 1px solid var(--border-color);
                        background: <?= $isPast ? '#FAFAF8' : '#FFFFFF' ?>;
                        opacity: <?= $isPast ? '0.7' : '1' ?>;
                    ">
                        <div class="d-flex justify-between align-center" style="margin-bottom: 4px; gap: 8px;">
                            <span class="badge <?= ($e['event_type'] ?? 'Activity') === 'Holiday' ? 'badge-danger' : (($e['event_type'] ?? 'Activity') === 'Celebration' ? 'badge-warning' : 'badge-primary') ?>">
                                <?= htmlspecialchars($e['event_type'] ?? 'Activity') ?>
                            </span>
                            <small style="color: var(--primary); font-weight: 600;">
                                <?= date('M d, Y', strtotime($eventDate)) ?>
                            </small>
                        </div>

                        <h4 style="margin: 0 0 4px; font-size: 1rem; font-weight: 600;"><?= htmlspecialchars($e['title']) ?></h4>
                        <p style="font-size: 0.85rem; color: var(--text-secondary); margin: 0;">
                            <?= htmlspecialchars(substr($e['description'] ?? 'School activity', 0, 60)) ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Google Calendar Connect Notice -->
<?php if (empty($googleEvents)): ?>
<div class="card" style="background: #FEF9E7; border-color: var(--secondary);">
    <div class="card-header">
        <h3 class="card-title">🔗 Connect Google Calendar</h3>
    </div>
    <p style="color: var(--text-secondary); margin-bottom: 16px;">
        Connect your school's Google Calendar to see all events in one place.
    </p>
    <a href="<?= url('admin/settings.php?action=google-calendar') ?>" class="btn btn-secondary">
        Connect Calendar
    </a>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    initPreschoolCalendar('parentSchoolCalendar', <?= json_encode($eventsJson) ?>);
});
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
