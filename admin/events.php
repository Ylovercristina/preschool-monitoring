<?php
/**
 * School Events & Activity Management (Admin)
 * Preschool Monitoring System
 * Fulfills: "Event/Activity Management: As an admin, I want to manage school events and activities,
 * so that I can keep track of and organize upcoming school activities."
 */

$pageTitle = 'School Events & Activity Management';
$pageSubtitle = 'Organize, schedule and broadcast upcoming school activities, celebrations and holidays';

require_once dirname(__DIR__) . '/includes/auth.php';
checkAuth(['admin']);

$db = getDB();

// Handle Actions (Create, Update, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $eventDate = $_POST['event_date'] ?? '';
        $startTime = $_POST['start_time'] ?? null;
        $endTime = $_POST['end_time'] ?? null;
        $location = trim($_POST['location'] ?? 'Preschool Grounds');
        $eventType = $_POST['event_type'] ?? 'School Activity';

        if (empty($title) || empty($eventDate)) {
            setFlash('danger', 'Event title and event date are required.');
        } else {
            if ($action === 'create') {
                $stmt = $db->prepare("INSERT INTO events (title, description, event_date, start_time, end_time, location, event_type, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$title, $description, $eventDate, $startTime, $endTime, $location, $eventType, $_SESSION['user_id']]);
                $eventId = $db->lastInsertId();

                // Notify all active parents if checked
                if (!empty($_POST['notify_parents'])) {
                    $parents = $db->query("SELECT id FROM users WHERE role = 'parent' AND status = 'active'")->fetchAll();
                    $notifStmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, 'event', 'parent/calendar.php')");
                    foreach ($parents as $p) {
                        $notifStmt->execute([
                            $p['id'],
                            "New Event: {$title}",
                            "School event '{$title}' is scheduled on " . formatDate($eventDate) . " at {$location}."
                        ]);
                    }
                }

                logActivity('Event Created', "Scheduled new school event: '{$title}' on {$eventDate}");
                setFlash('success', "Event '{$title}' scheduled successfully!");
            } else {
                $id = (int)$_POST['id'];
                $stmt = $db->prepare("UPDATE events SET title=?, description=?, event_date=?, start_time=?, end_time=?, location=?, event_type=? WHERE id=?");
                $stmt->execute([$title, $description, $eventDate, $startTime, $endTime, $location, $eventType, $id]);
                logActivity('Event Updated', "Updated event ID {$id} ('{$title}')");
                setFlash('success', "Event details updated successfully!");
            }
            header('Location: events.php');
            exit;
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $db->prepare("DELETE FROM events WHERE id = ?")->execute([$id]);
        logActivity('Event Deleted', "Deleted event ID {$id}");
        setFlash('warning', 'School event removed.');
        header('Location: events.php');
        exit;
    }
}

// Fetch Events sorted chronologically
$events = $db->query("
    SELECT e.*, u.name as organizer_name 
    FROM events e 
    LEFT JOIN users u ON e.created_by = u.id 
    ORDER BY e.event_date ASC
")->fetchAll();

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="d-flex justify-between align-center" style="margin-bottom: 24px;">
    <div style="max-width: 400px; width: 100%;">
        <input type="text" class="form-control" data-table-search="eventsTable" placeholder="🔍 Search school events & activities...">
    </div>
    <button class="btn btn-primary" onclick="openEventModal()">
        <span>+</span> Schedule New Event
    </button>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <span>🎈</span> Scheduled Preschool Activities & Calendar Events
            <span class="badge badge-primary"><?= count($events) ?> Events</span>
        </h3>
    </div>

    <div class="table-responsive">
        <table class="table" id="eventsTable">
            <thead>
                <tr>
                    <th>Event Date</th>
                    <th>Activity Title</th>
                    <th>Category</th>
                    <th>Schedule Time</th>
                    <th>Location / Venue</th>
                    <th>Description</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($events)): ?>
                    <tr><td colspan="7" style="text-align: center; padding: 24px; color: var(--text-muted);">No events scheduled yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($events as $e): 
                        $isPast = strtotime($e['event_date']) < strtotime(date('Y-m-d'));
                    ?>
                        <tr style="<?= $isPast ? 'opacity: 0.65;' : '' ?>">
                            <td>
                                <strong><?= formatDate($e['event_date']) ?></strong><br>
                                <small style="color: var(--text-muted);"><?= date('l', strtotime($e['event_date'])) ?></small>
                            </td>
                            <td>
                                <strong style="font-size: 0.95rem; color: var(--text-primary);"><?= htmlspecialchars($e['title']) ?></strong>
                            </td>
                            <td>
                                <span class="badge <?= $e['event_type'] === 'Holiday' ? 'badge-danger' : ($e['event_type'] === 'Celebration' ? 'badge-warning' : 'badge-info') ?>">
                                    <?= htmlspecialchars($e['event_type']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($e['start_time']): ?>
                                    <?= formatTime($e['start_time']) ?> - <?= formatTime($e['end_time']) ?>
                                <?php else: ?>
                                    All Day
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($e['location'] ?: 'Preschool') ?></td>
                            <td>
                                <div style="max-width: 250px; font-size: 0.85rem; color: var(--text-secondary);">
                                    <?= htmlspecialchars($e['description'] ?: 'No additional notes.') ?>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-secondary btn-sm" onclick='editEvent(<?= json_encode($e) ?>)'>
                                        Edit
                                    </button>
                                    <form method="POST" action="events.php" onsubmit="return confirm('Delete this event?');" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $e['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Add / Edit Event -->
<div class="modal-overlay" id="eventModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 class="modal-title" id="eventModalTitle">Schedule New School Event</h3>
            <button class="modal-close" onclick="closeModal('eventModal')">&times;</button>
        </div>
        <form method="POST" action="events.php">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" id="eventFormAction" value="create">
            <input type="hidden" name="id" id="eventId" value="">

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="event_title">Activity / Event Title *</label>
                    <input type="text" name="title" id="event_title" class="form-control" placeholder="e.g. Teddy Bear Healthy Picnic" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                    <div class="form-group">
                        <label class="form-label" for="event_type">Event Category</label>
                        <select name="event_type" id="event_type" class="form-select">
                            <option value="School Activity">School Activity</option>
                            <option value="Celebration">Celebration</option>
                            <option value="Meeting">Parent-Teacher Meeting</option>
                            <option value="Holiday">School Holiday</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="event_date">Event Date *</label>
                        <input type="date" name="event_date" id="event_date" class="form-control" required>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                    <div class="form-group">
                        <label class="form-label" for="start_time">Start Time</label>
                        <input type="time" name="start_time" id="start_time" class="form-control" value="08:30">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="end_time">End Time</label>
                        <input type="time" name="end_time" id="end_time" class="form-control" value="11:30">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="location">Venue / Location</label>
                    <input type="text" name="location" id="location" class="form-control" placeholder="e.g. Activity Hall / School Courtyard">
                </div>

                <div class="form-group">
                    <label class="form-label" for="event_description">Details & Activity Guidelines</label>
                    <textarea name="description" id="event_description" class="form-control" rows="3" placeholder="Describe the activities, special materials or dress code"></textarea>
                </div>

                <div class="form-group" id="notifyGroup">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.9rem;">
                        <input type="checkbox" name="notify_parents" value="1" checked>
                        <span>Send instant notification notice to all active parents</span>
                    </label>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('eventModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save School Event</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEventModal() {
    document.getElementById('eventModalTitle').innerText = 'Schedule New School Event';
    document.getElementById('eventFormAction').value = 'create';
    document.getElementById('eventId').value = '';
    document.getElementById('event_title').value = '';
    document.getElementById('event_type').value = 'School Activity';
    document.getElementById('event_date').value = '<?= date('Y-m-d') ?>';
    document.getElementById('start_time').value = '08:30';
    document.getElementById('end_time').value = '11:30';
    document.getElementById('location').value = 'Preschool Courtyard';
    document.getElementById('event_description').value = '';
    document.getElementById('notifyGroup').style.display = 'block';
    openModal('eventModal');
}

function editEvent(e) {
    document.getElementById('eventModalTitle').innerText = 'Edit School Event';
    document.getElementById('eventFormAction').value = 'update';
    document.getElementById('eventId').value = e.id;
    document.getElementById('event_title').value = e.title;
    document.getElementById('event_type').value = e.event_type;
    document.getElementById('event_date').value = e.event_date;
    document.getElementById('start_time').value = e.start_time || '';
    document.getElementById('end_time').value = e.end_time || '';
    document.getElementById('location').value = e.location || '';
    document.getElementById('event_description').value = e.description || '';
    document.getElementById('notifyGroup').style.display = 'none';
    openModal('eventModal');
}
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
