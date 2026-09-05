<?php
/**
 * Parent-Teacher Messaging Hub (Parent View)
 * Preschool Monitoring System
 * Fulfills: "Parent- Teacher Communication: As a parent, I want to send messages
 * to my child's teacher, so that I can ask questions and discuss my child's progress."
 */

$pageTitle = 'Message Child\'s Teacher';
$pageSubtitle = 'Direct communication channel to ask questions, share observations & discuss progress';

require_once dirname(__DIR__) . '/includes/auth.php';
checkAuth(['parent']);

$db = getDB();
$parentId = $_SESSION['user_id'];

// Fetch Child & Assigned Teacher
$childStmt = $db->prepare("
    SELECT s.*, c.name as class_name, c.room_number, u.id as teacher_id, u.name as teacher_name, u.phone as teacher_phone, u.email as teacher_email
    FROM students s
    JOIN classrooms c ON s.classroom_id = c.id
    JOIN users u ON c.teacher_id = u.id
    WHERE s.parent_id = ? AND s.enrollment_status = 'enrolled'
    LIMIT 1
");
$childStmt->execute([$parentId]);
$child = $childStmt->fetch();

$teacherId = $child['teacher_id'] ?? 0;
$childId = $child['id'] ?? 0;

// Handle Sending Message
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $message = trim($_POST['message'] ?? '');

    if (!empty($message) && $teacherId > 0) {
        $stmt = $db->prepare("INSERT INTO messages (sender_id, receiver_id, student_id, message, is_read, created_at) VALUES (?, ?, ?, ?, 0, datetime('now'))");
        $stmt->execute([$parentId, $teacherId, $childId, $message]);

        // Notify teacher
        $db->prepare("INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, 'system', 'teacher/messages.php')")
           ->execute([$teacherId, "Parent Inquiry from {$_SESSION['user']['name']}", "Regarding pupil {$child['first_name']}: " . substr($message, 0, 45) . "..."]);

        logActivity('Parent Message Sent', "Parent sent message to teacher ID {$teacherId}");
        header('Location: messages.php');
        exit;
    }
}

// Mark messages from teacher as read
if ($teacherId) {
    $db->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ?")->execute([$teacherId, $parentId]);
}

// Fetch Thread
$thread = [];
if ($teacherId) {
    $stmt = $db->prepare("
        SELECT m.*, u.name as sender_name, u.role as sender_role
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE (m.sender_id = ? AND m.receiver_id = ?)
           OR (m.sender_id = ? AND m.receiver_id = ?)
        ORDER BY m.created_at ASC
    ");
    $stmt->execute([$parentId, $teacherId, $teacherId, $parentId]);
    $thread = $stmt->fetchAll();
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<?php if (!$child || !$teacherId): ?>
    <div class="card" style="text-align: center; padding: 40px;">
        <p style="color: var(--text-muted);">Your child does not have an assigned classroom teacher yet.</p>
    </div>
<?php else: ?>

    <div style="display: grid; grid-template-columns: 300px 1fr; gap: 24px; min-height: 560px;">
        <!-- Left: Teacher Info Card -->
        <div class="card" style="display: flex; flex-direction: column; align-items: center; text-align: center; padding: 28px 20px;">
            <div class="user-cell-avatar" style="width: 72px; height: 72px; font-size: 2rem; background: var(--grad-mint); margin-bottom: 12px;">
                <?= strtoupper(substr($child['teacher_name'], 0, 1)) ?>
            </div>
            <h3 style="margin: 0; font-size: 1.25rem;"><?= htmlspecialchars($child['teacher_name']) ?></h3>
            <span class="badge badge-primary" style="margin: 6px 0 12px;">CLASSROOM TEACHER</span>

            <div style="width: 100%; border-top: 1px solid var(--border-color); padding-top: 14px; text-align: left; font-size: 0.88rem; display: flex; flex-direction: column; gap: 8px;">
                <div>
                    <span style="color: var(--text-muted); display: block; font-size: 0.76rem;">Classroom:</span>
                    <strong><?= htmlspecialchars($child['class_name']) ?> (<?= htmlspecialchars($child['room_number']) ?>)</strong>
                </div>
                <div>
                    <span style="color: var(--text-muted); display: block; font-size: 0.76rem;">Pupil:</span>
                    <strong><?= htmlspecialchars($child['first_name'] . ' ' . $child['last_name']) ?></strong>
                </div>
                <div>
                    <span style="color: var(--text-muted); display: block; font-size: 0.76rem;">Contact:</span>
                    <?= htmlspecialchars($child['teacher_phone'] ?: 'preschool office') ?>
                </div>
            </div>
        </div>

        <!-- Right: Chat Box -->
        <div class="card" style="padding: 0; display: flex; flex-direction: column; height: 560px;">
            <!-- Header -->
            <div style="padding: 16px 24px; border-bottom: 1px solid var(--border-color); background: #FFFFFF; border-radius: var(--radius-lg) var(--radius-lg) 0 0;">
                <h4 style="margin: 0; font-size: 1.05rem;">
                    Conversation with <?= htmlspecialchars($child['teacher_name']) ?>
                </h4>
                <div style="font-size: 0.78rem; color: var(--text-muted);">
                    Discussing <?= htmlspecialchars($child['first_name']) ?>'s daily activities, health notes, and learning milestones
                </div>
            </div>

            <!-- Messages Stream -->
            <div style="flex: 1; overflow-y: auto; padding: 24px; background: #F8FAFC; display: flex; flex-direction: column; gap: 14px;">
                <?php if (empty($thread)): ?>
                    <div style="text-align: center; margin: auto; color: var(--text-muted); font-size: 0.9rem;">
                        No messages yet. Send a note or inquiry to <?= htmlspecialchars($child['teacher_name']) ?> below!
                    </div>
                <?php else: ?>
                    <?php foreach ($thread as $msg): 
                        $isMe = ($msg['sender_id'] == $parentId);
                    ?>
                        <div style="display: flex; flex-direction: column; align-items: <?= $isMe ? 'flex-end' : 'flex-start' ?>;">
                            <div style="
                                max-width: 72%;
                                padding: 12px 16px;
                                border-radius: <?= $isMe ? '16px 16px 2px 16px' : '16px 16px 16px 2px' ?>;
                                background: <?= $isMe ? 'var(--primary)' : '#FFFFFF' ?>;
                                color: <?= $isMe ? '#FFFFFF' : 'var(--text-primary)' ?>;
                                box-shadow: var(--shadow-sm);
                                border: <?= $isMe ? 'none' : '1px solid var(--border-color)' ?>;
                                font-size: 0.92rem;
                            ">
                                <?= nl2br(htmlspecialchars($msg['message'])) ?>
                            </div>
                            <span style="font-size: 0.72rem; color: var(--text-muted); margin-top: 4px; padding: 0 4px;">
                                <?= $isMe ? 'You' : htmlspecialchars($msg['sender_name']) ?> &bull; <?= formatDate($msg['created_at'], 'M d, h:i A') ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Composer -->
            <form method="POST" action="messages.php" style="padding: 16px 20px; background: #FFFFFF; border-top: 1px solid var(--border-color); border-radius: 0 0 var(--radius-lg) var(--radius-lg); display: flex; gap: 12px; align-items: center;">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="text" name="message" class="form-control" placeholder="Type a message or question for Teacher <?= htmlspecialchars($child['teacher_name']) ?>..." required autocomplete="off" style="flex: 1;">
                <button type="submit" class="btn btn-primary" style="padding: 10px 22px;">
                    Send 📤
                </button>
            </form>
        </div>
    </div>

<?php endif; ?>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
