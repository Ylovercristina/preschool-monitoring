<?php
/**
 * Parent-Teacher Communication Hub (Teacher View)
 * Preschool Monitoring System
 * Fulfills: "Parent- Teacher Communication: As a teacher, I want to send messages to parents,
 * so that I can communicate with them about their child."
 */

$pageTitle = 'Parent-Teacher Communication';
$pageSubtitle = 'Send direct messages, updates, observations and discuss pupil progress with parents';

require_once dirname(__DIR__) . '/includes/auth.php';
checkAuth(['teacher']);

$db = getDB();
$teacherId = $_SESSION['user_id'];

// Get assigned classroom
$classStmt = $db->prepare("SELECT * FROM classrooms WHERE teacher_id = ? LIMIT 1");
$classStmt->execute([$teacherId]);
$classroom = $classStmt->fetch();
$classroomId = $classroom['id'] ?? 0;

// Fetch Parents linked to students in this classroom
$parents = $db->query("
    SELECT DISTINCT u.id, u.name, u.email, u.phone, s.id as student_id, (s.first_name || ' ' || s.last_name) as pupil_name,
           (SELECT COUNT(*) FROM messages m WHERE m.sender_id = u.id AND m.receiver_id = $teacherId AND m.is_read = 0) as unread_count
    FROM users u
    JOIN students s ON s.parent_id = u.id
    WHERE s.classroom_id = $classroomId AND u.status = 'active'
    ORDER BY unread_count DESC, u.name ASC
")->fetchAll();

$selectedParentId = (int)($_GET['parent_id'] ?? ($parents[0]['id'] ?? 0));
$selectedStudentId = (int)($_GET['student_id'] ?? ($parents[0]['student_id'] ?? 0));

// Handle Sending Message
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $receiverId = (int)$_POST['receiver_id'];
    $studentId = (int)$_POST['student_id'];
    $message = trim($_POST['message'] ?? '');

    if (!empty($message) && $receiverId > 0) {
        $stmt = $db->prepare("INSERT INTO messages (sender_id, receiver_id, student_id, message, is_read, created_at) VALUES (?, ?, ?, ?, 0, datetime('now'))");
        $stmt->execute([$teacherId, $receiverId, $studentId, $message]);

        // Notify parent
        $notifStmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, 'system', 'parent/messages.php')");
        $notifStmt->execute([
            $receiverId,
            "New Message from Teacher",
            "Teacher Sarah sent you a note: '" . substr($message, 0, 50) . "...'"
        ]);

        logActivity('Message Sent', "Teacher sent message to parent ID {$receiverId}");
        header("Location: messages.php?parent_id={$receiverId}&student_id={$studentId}");
        exit;
    }
}

// Mark messages as read for active conversation
if ($selectedParentId) {
    $db->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ?")
       ->execute([$selectedParentId, $teacherId]);
}

// Fetch Conversation Thread
$thread = [];
if ($selectedParentId) {
    $stmt = $db->prepare("
        SELECT m.*, u.name as sender_name, u.role as sender_role
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE (m.sender_id = ? AND m.receiver_id = ?)
           OR (m.sender_id = ? AND m.receiver_id = ?)
        ORDER BY m.created_at ASC
    ");
    $stmt->execute([$teacherId, $selectedParentId, $selectedParentId, $teacherId]);
    $thread = $stmt->fetchAll();
}

// Active parent details
$activeParent = null;
foreach ($parents as $p) {
    if ($p['id'] === $selectedParentId) {
        $activeParent = $p;
        break;
    }
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div style="display: grid; grid-template-columns: 320px 1fr; gap: 24px; min-height: 600px;">
    <!-- Left Sidebar: Parent Conversation List -->
    <div class="card" style="padding: 16px; display: flex; flex-direction: column;">
        <div class="card-header" style="margin-bottom: 12px; padding-bottom: 10px;">
            <h3 class="card-title" style="font-size: 1rem;">
                <span>💬</span> Parents in Classroom
            </h3>
        </div>

        <div style="display: flex; flex-direction: column; gap: 8px; overflow-y: auto; flex: 1;">
            <?php if (empty($parents)): ?>
                <p style="color: var(--text-muted); font-size: 0.85rem; text-align: center; padding: 20px;">
                    No parents linked to enrolled students in this classroom.
                </p>
            <?php else: ?>
                <?php foreach ($parents as $p): 
                    $isSelected = ($p['id'] === $selectedParentId);
                ?>
                    <a href="messages.php?parent_id=<?= $p['id'] ?>&student_id=<?= $p['student_id'] ?>" style="
                        display: flex;
                        align-items: center;
                        gap: 12px;
                        padding: 12px;
                        border-radius: var(--radius-md);
                        background: <?= $isSelected ? 'var(--primary-light)' : 'var(--bg-card-subtle)' ?>;
                        border: 1px solid <?= $isSelected ? 'var(--primary)' : 'transparent' ?>;
                        text-decoration: none;
                        color: var(--text-primary);
                        transition: all var(--trans-fast);
                    ">
                        <div class="user-cell-avatar" style="background: <?= $isSelected ? 'var(--grad-primary)' : '#94A3B8' ?>;">
                            <?= strtoupper(substr($p['name'], 0, 1)) ?>
                        </div>
                        <div style="flex: 1; min-width: 0;">
                            <div class="d-flex justify-between align-center">
                                <strong style="font-size: 0.9rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($p['name']) ?></strong>
                                <?php if ($p['unread_count'] > 0): ?>
                                    <span class="badge badge-danger" style="font-size: 0.65rem;"><?= $p['unread_count'] ?></span>
                                <?php endif; ?>
                            </div>
                            <div style="font-size: 0.76rem; color: var(--primary); font-weight: 600;">
                                Child: <?= htmlspecialchars($p['pupil_name']) ?>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right Side: Chat Conversation Stream -->
    <div class="card" style="padding: 0; display: flex; flex-direction: column; height: 600px;">
        <?php if ($activeParent): ?>
            <!-- Thread Header -->
            <div style="padding: 16px 24px; border-bottom: 1px solid var(--border-color); background: #FFFFFF; border-radius: var(--radius-lg) var(--radius-lg) 0 0; display: flex; justify-content: space-between; align-items: center;">
                <div class="d-flex align-center gap-3">
                    <div class="user-cell-avatar" style="background: var(--grad-accent);">
                        <?= strtoupper(substr($activeParent['name'], 0, 1)) ?>
                    </div>
                    <div>
                        <h4 style="margin: 0; font-size: 1.1rem;"><?= htmlspecialchars($activeParent['name']) ?></h4>
                        <div style="font-size: 0.78rem; color: var(--text-muted);">
                            Parent of <strong><?= htmlspecialchars($activeParent['pupil_name']) ?></strong> &bull; <?= htmlspecialchars($activeParent['phone']) ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Message Bubbles Scroll Area -->
            <div style="flex: 1; overflow-y: auto; padding: 24px; background: #F8FAFC; display: flex; flex-direction: column; gap: 14px;">
                <?php if (empty($thread)): ?>
                    <div style="text-align: center; margin: auto; color: var(--text-muted); font-size: 0.9rem;">
                        No messages yet in this conversation. Start the discussion below!
                    </div>
                <?php else: ?>
                    <?php foreach ($thread as $msg): 
                        $isMe = ($msg['sender_id'] == $teacherId);
                    ?>
                        <div style="display: flex; flex-direction: column; align-items: <?= $isMe ? 'flex-end' : 'flex-start' ?>;">
                            <div style="
                                max-width: 70%;
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

            <!-- Message Input Bar -->
            <form method="POST" action="messages.php" style="padding: 16px 20px; background: #FFFFFF; border-top: 1px solid var(--border-color); border-radius: 0 0 var(--radius-lg) var(--radius-lg); display: flex; gap: 12px; align-items: center;">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="receiver_id" value="<?= $activeParent['id'] ?>">
                <input type="hidden" name="student_id" value="<?= $activeParent['student_id'] ?>">

                <input type="text" name="message" class="form-control" placeholder="Type a message to <?= htmlspecialchars($activeParent['name']) ?> about <?= htmlspecialchars($activeParent['pupil_name']) ?>..." required autocomplete="off" style="flex: 1;">
                <button type="submit" class="btn btn-primary" style="padding: 10px 20px;">
                    Send 📤
                </button>
            </form>
        <?php else: ?>
            <div style="display: flex; align-items: center; justify-content: center; height: 100%; color: var(--text-muted);">
                Select a parent from the left menu to start messaging.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
