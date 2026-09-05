<?php
/**
 * Role-Specific Navigation Sidebar
 * Preschool Monitoring System
 */

$user = currentUser();
$role = userRole();
$currentScript = basename($_SERVER['SCRIPT_NAME']);
$unreadNotifs = getUnreadNotificationsCount($user['id'] ?? null);
$pendingApprovals = ($role === 'admin') ? getPendingApprovalsCount() : 0;
$unreadMessages = getUnreadMessagesCount($user['id'] ?? null);
$activeEmergency = getActiveEmergencyAlert();
?>

<aside class="app-sidebar" id="appSidebar">
    <div class="sidebar-brand">
        <div class="brand-icon">🎒</div>
        <div>
            <div class="brand-title"><?= APP_NAME ?></div>
            <div class="brand-subtitle">School Portal</div>
        </div>
    </div>

    <div class="sidebar-nav">
        <?php if ($role === 'admin'): ?>
            <!-- Admin Navigation -->
            <div class="nav-section-title">Overview</div>
            <a href="<?= url('admin/index.php') ?>" class="nav-item <?= $currentScript === 'index.php' ? 'active' : '' ?>">
                <span class="nav-icon">📊</span> Dashboard
            </a>
            
            <div class="nav-section-title">Admissions & Records</div>
            <a href="<?= url('admin/students.php') ?>" class="nav-item <?= $currentScript === 'students.php' ? 'active' : '' ?>">
                <span class="nav-icon">👶</span> Students Roster
            </a>
            <a href="<?= url('admin/teachers.php') ?>" class="nav-item <?= $currentScript === 'teachers.php' ? 'active' : '' ?>">
                <span class="nav-icon">👩‍🏫</span> Teacher Files
            </a>
            <a href="<?= url('admin/progress.php') ?>" class="nav-item <?= $currentScript === 'progress.php' ? 'active' : '' ?>">
                <span class="nav-icon">📈</span> Academic Progress
            </a>

            <div class="nav-section-title">User & Account Control</div>
            <a href="<?= url('admin/approvals.php') ?>" class="nav-item <?= $currentScript === 'approvals.php' ? 'active' : '' ?>">
                <span class="nav-icon">✅</span> Parent Approvals
                <?php if ($pendingApprovals > 0): ?>
                    <span class="nav-badge badge-danger"><?= $pendingApprovals ?></span>
                <?php endif; ?>
            </a>
            <a href="<?= url('admin/users.php') ?>" class="nav-item <?= $currentScript === 'users.php' ? 'active' : '' ?>">
                <span class="nav-icon">👥</span> Add / Archive Users
            </a>

            <div class="nav-section-title">Finance & Operations</div>
            <a href="<?= url('admin/fees.php') ?>" class="nav-item <?= $currentScript === 'fees.php' ? 'active' : '' ?>">
                <span class="nav-icon">💰</span> Fee Management
            </a>
            <a href="<?= url('admin/events.php') ?>" class="nav-item <?= $currentScript === 'events.php' ? 'active' : '' ?>">
                <span class="nav-icon">🎈</span> Events & Activities
            </a>
            <a href="<?= url('admin/logs.php') ?>" class="nav-item <?= $currentScript === 'logs.php' ? 'active' : '' ?>">
                <span class="nav-icon">📜</span> System Activity Logs
            </a>
            <a href="<?= url('admin/reports.php') ?>" class="nav-item <?= $currentScript === 'reports.php' ? 'active' : '' ?>">
                <span class="nav-icon">📑</span> Reports Generator
            </a>

        <?php elseif ($role === 'teacher'): ?>
            <!-- Teacher Navigation -->
            <div class="nav-section-title">Classroom Daily</div>
            <a href="<?= url('teacher/index.php') ?>" class="nav-item <?= $currentScript === 'index.php' ? 'active' : '' ?>">
                <span class="nav-icon">🏠</span> Classroom Dashboard
            </a>
            <a href="<?= url('teacher/attendance.php') ?>" class="nav-item <?= $currentScript === 'attendance.php' ? 'active' : '' ?>">
                <span class="nav-icon">📋</span> Attendance Tracking
            </a>
            <a href="<?= url('teacher/progress.php') ?>" class="nav-item <?= $currentScript === 'progress.php' ? 'active' : '' ?>">
                <span class="nav-icon">🌟</span> Milestones & Progress
            </a>

            <div class="nav-section-title">Safety & Verification</div>
            <a href="<?= url('teacher/pickups.php') ?>" class="nav-item <?= $currentScript === 'pickups.php' ? 'active' : '' ?>">
                <span class="nav-icon">🛡️</span> Authorized Pickups
            </a>
            <a href="<?= url('teacher/emergency.php') ?>" class="nav-item <?= $currentScript === 'emergency.php' ? 'active' : '' ?>">
                <span class="nav-icon">🚨</span> Emergency Alert
                <?php if ($activeEmergency): ?>
                    <span class="nav-badge badge-danger">ACTIVE</span>
                <?php endif; ?>
            </a>

            <div class="nav-section-title">Parent Engagement</div>
            <a href="<?= url('teacher/messages.php') ?>" class="nav-item <?= $currentScript === 'messages.php' ? 'active' : '' ?>">
                <span class="nav-icon">💬</span> Parent Messaging
                <?php if ($unreadMessages > 0): ?>
                    <span class="nav-badge badge-primary"><?= $unreadMessages ?></span>
                <?php endif; ?>
            </a>
            <a href="<?= url('teacher/reminders.php') ?>" class="nav-item <?= $currentScript === 'reminders.php' ? 'active' : '' ?>">
                <span class="nav-icon">🔔</span> Send Reminders
            </a>
            <a href="<?= url('teacher/events.php') ?>" class="nav-item <?= $currentScript === 'events.php' ? 'active' : '' ?>">
                <span class="nav-icon">🎪</span> School Events
            </a>
            <a href="<?= url('teacher/students.php') ?>" class="nav-item <?= $currentScript === 'students.php' ? 'active' : '' ?>">
                <span class="nav-icon">👶</span> Enrolled Students
            </a>

        <?php elseif ($role === 'parent'): ?>
            <!-- Parent Navigation -->
            <div class="nav-section-title">My Child</div>
            <a href="<?= url('parent/index.php') ?>" class="nav-item <?= $currentScript === 'index.php' ? 'active' : '' ?>">
                <span class="nav-icon">🧸</span> Child Overview
            </a>
            <a href="<?= url('parent/progress.php') ?>" class="nav-item <?= $currentScript === 'progress.php' ? 'active' : '' ?>">
                <span class="nav-icon">⭐</span> Academic Milestones
            </a>
            <a href="<?= url('parent/attendance.php') ?>" class="nav-item <?= $currentScript === 'attendance.php' ? 'active' : '' ?>">
                <span class="nav-icon">📅</span> Attendance Record
            </a>

            <div class="nav-section-title">Security & Finances</div>
            <a href="<?= url('parent/pickups.php') ?>" class="nav-item <?= $currentScript === 'pickups.php' ? 'active' : '' ?>">
                <span class="nav-icon">🛡️</span> Authorized Pickups
            </a>
            <a href="<?= url('parent/fees.php') ?>" class="nav-item <?= $currentScript === 'fees.php' ? 'active' : '' ?>">
                <span class="nav-icon">💳</span> Fees & Payments
            </a>

            <div class="nav-section-title">Community & Updates</div>
            <a href="<?= url('parent/calendar.php') ?>" class="nav-item <?= $currentScript === 'calendar.php' ? 'active' : '' ?>">
                <span class="nav-icon">🗓️</span> School Calendar
            </a>
            <a href="<?= url('parent/messages.php') ?>" class="nav-item <?= $currentScript === 'messages.php' ? 'active' : '' ?>">
                <span class="nav-icon">💬</span> Message Teacher
                <?php if ($unreadMessages > 0): ?>
                    <span class="nav-badge badge-primary"><?= $unreadMessages ?></span>
                <?php endif; ?>
            </a>
            <a href="<?= url('parent/notifications.php') ?>" class="nav-item <?= $currentScript === 'notifications.php' ? 'active' : '' ?>">
                <span class="nav-icon">🔔</span> Notifications
                <?php if ($unreadNotifs > 0): ?>
                    <span class="nav-badge badge-danger"><?= $unreadNotifs ?></span>
                <?php endif; ?>
            </a>
        <?php endif; ?>
    </div>

    <?php if ($activeEmergency): ?>
        <div class="sidebar-emergency" role="alert">
            <div class="sidebar-emergency-title">🚨 Emergency Alert</div>
            <strong><?= htmlspecialchars($activeEmergency['title']) ?></strong>
            <p><?= htmlspecialchars($activeEmergency['message']) ?></p>
            <small><?= date('M d, h:i A', strtotime($activeEmergency['created_at'])) ?></small>
            <?php if ($role === 'teacher'): ?>
                <a href="<?= url('teacher/emergency.php') ?>" class="sidebar-emergency-link">Manage alert</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</aside>
