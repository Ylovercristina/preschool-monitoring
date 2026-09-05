<?php
/**
 * School Activities & Events Calendar (Parent View)
 * Preschool Monitoring System
 * Fulfills: "Calendar: As a parent, I want to know the school calendar to track school activities of my child."
 */

$pageTitle = 'School Activities & Events Calendar';
$pageSubtitle = 'Track upcoming school activities, theme days, holidays and parent-teacher conferences';

require_once dirname(__DIR__) . '/includes/auth.php';
checkAuth(['parent']);

$db = getDB();

// Fetch Events
$events = $db->query("SELECT * FROM events ORDER BY event_date ASC")->fetchAll();

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
    <!-- Interactive Monthly Calendar -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <span>🗓️</span> School Calendar
            </h3>
        </div>
        <div id="parentSchoolCalendar"></div>
    </div>

    <!-- Scheduled Activities Feed -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <span>🎈</span> Upcoming Activities for Your Child
                <span class="badge badge-primary"><?= count($events) ?> Activities</span>
            </h3>
        </div>

        <div style="display: flex; flex-direction: column; gap: 14px;">
            <?php if (empty($events)): ?>
                <p style="color: var(--text-muted); text-align: center; padding: 24px;">No upcoming activities scheduled.</p>
            <?php else: ?>
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
                            <span class="badge <?= $e['event_type'] === 'Holiday' ? 'badge-danger' : ($e['event_type'] === 'Celebration' ? 'badge-warning' : 'badge-info') ?>">
                                <?= htmlspecialchars($e['event_type']) ?>
                            </span>
                            <strong style="color: var(--primary); font-size: 0.9rem;">
                                📅 <?= formatDate($e['event_date']) ?>
                            </strong>
                        </div>

                        <h4 style="margin: 0 0 6px; font-size: 1.1rem;"><?= htmlspecialchars($e['title']) ?></h4>
                        <p style="font-size: 0.88rem; color: var(--text-secondary); margin-bottom: 8px;">
                            <?= htmlspecialchars($e['description'] ?: 'Preschool activity and guided learning.') ?>
                        </p>

                        <div style="font-size: 0.78rem; color: var(--text-muted); border-top: 1px solid var(--border-subtle); padding-top: 8px; display: flex; gap: 14px;">
                            <span>⏰ <?= formatTime($e['start_time']) ?> - <?= formatTime($e['end_time']) ?></span>
                            <span>📍 <?= htmlspecialchars($e['location'] ?: 'Preschool Grounds') ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    initPreschoolCalendar('parentSchoolCalendar', <?= json_encode($eventsJson) ?>);
});
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
