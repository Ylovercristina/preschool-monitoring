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
        <div class="brand-icon">📋</div>
        <div>
            <div class="brand-title">Play is School</div>
            <div class="brand-subtitle">Learning Portal</div>
        </div>
    </div>

    <div class="sidebar-nav">
        <?php if ($role === 'admin'): ?>
            <!-- Admin Navigation -->
            <div class="nav-section-title">Overview</div>
            <a href="<?= url('admin/index.php') ?>" class="nav-item <?= $currentScript === 'index.php' ? 'active' : '' ?>">
                <i class="nav-icon bi bi-graph-up"></i> Dashboard
            </a>
            
            <div class="nav-section-title">Admissions & Records</div>
            <a href="<?= url('admin/students.php') ?>" class="nav-item <?= $currentScript === 'students.php' ? 'active' : '' ?>">
                <i class="nav-icon bi bi-people"></i> Students Roster
            </a>
            <a href="<?= url('admin/teachers.php') ?>" class="nav-item <?= $currentScript === 'teachers.php' ? 'active' : '' ?>">
                <i class="nav-icon bi bi-person-badge"></i> Teacher Files
            </a>
            <a href="<?= url('admin/progress.php') ?>" class="nav-item <?= $currentScript === 'progress.php' ? 'active' : '' ?>">
                <i class="nav-icon bi bi-trending-up"></i> Academic Progress
            </a>

            <div class="nav-section-title">User & Account Control</div>
            <a href="<?= url('admin/approvals.php') ?>" class="nav-item <?= $currentScript === 'approvals.php' ? 'active' : '' ?>">
                <i class="nav-icon bi bi-check-circle"></i> Parent Approvals
                <?php if ($pendingApprovals > 0): ?>
                    <span class="nav-badge badge-danger"><?= $pendingApprovals ?></span>
                <?php endif; ?>
            </a>
            <a href="<?= url('admin/users.php') ?>" class="nav-item <?= $currentScript === 'users.php' ? 'active' : '' ?>">
                <i class="nav-icon bi bi-people"></i> Add / Archive Users
            </a>

            <div class="nav-section-title">Finance & Operations</div>
            <a href="<?= url('admin/fees.php') ?>" class="nav-item <?= $currentScript === 'fees.php' ? 'active' : '' ?>">
                <i class="nav-icon bi bi-cash-coin"></i> Fee Management
            </a>
            <a href="<?= url('admin/events.php') ?>" class="nav-item <?= $currentScript === 'events.php' ? 'active' : '' ?>">
                <i class="nav-icon bi bi-calendar-event"></i> Events & Activities
            </a>
            <a href="<?= url('admin/logs.php') ?>" class="nav-item <?= $currentScript === 'logs.php' ? 'active' : '' ?>">
                <i class="nav-icon bi bi-file-text"></i> System Activity Logs
            </a>
            <a href="<?= url('admin/reports.php') ?>" class="nav-item <?= $currentScript === 'reports.php' ? 'active' : '' ?>">
                <i class="nav-icon bi bi-file-earmark-text"></i> Reports Generator
            </a>

        <?php elseif ($role === 'teacher'): ?>
            <!-- Teacher Navigation -->
            <div class="nav-section-title">Classroom Daily</div>
            <a href="<?= url('teacher/index.php') ?>" class="nav-item <?= $currentScript === 'index.php' ? 'active' : '' ?>">
                <i class="nav-icon bi bi-house"></i> Classroom Dashboard
            </a>
            <a href="<?= url('teacher/attendance.php') ?>" class="nav-item <?= $currentScript === 'attendance.php' ? 'active' : '' ?>">
                <i class="nav-icon bi bi-clipboard"></i> Attendance Tracking
            </a>
            <a href="<?= url('teacher/progress.php') ?>" class="nav-item <?= $currentScript === 'progress.php' ? 'active' : '' ?>">
                <i class="nav-icon bi bi-star"></i> Milestones & Progress
            </a>

            <div class="nav-section-title">Safety & Verification</div>
            <a href="<?= url('teacher/pickups.php') ?>" class="nav-item <?= $currentScript === 'pickups.php' ? 'active' : '' ?>">
                <i class="nav-icon bi bi-shield"></i> Authorized Pickups
            </a>
            <a href="<?= url('teacher/emergency.php') ?>" class="nav-item <?= $currentScript === 'emergency.php' ? 'active' : '' ?>">
                <i class="nav-icon bi bi-exclamation-triangle"></i> Emergency Alert
                <?php if ($activeEmergency): ?>
                    <span class="nav-badge badge-danger">ACTIVE</span>
                <?php endif; ?>
            </a>

            <div class="nav-section-title">Parent Engagement</div>
            <a href="<?= url('teacher/messages.php') ?>" class="nav-item <?= $currentScript === 'messages.php' ? 'active' : '' ?>">
                <i class="nav-icon bi bi-chat-dots"></i> Parent Messaging
                <?php if ($unreadMessages > 0): ?>
                    <span class="nav-badge badge-primary"><?= $unreadMessages ?></span>
                <?php endif; ?>
            </a>
            <a href="<?= url('teacher/reminders.php') ?>" class="nav-item <?= $currentScript === 'reminders.php' ? 'active' : '' ?>">
                <i class="nav-icon bi bi-bell"></i> Send Reminders
            </a>
            <a href="<?= url('teacher/events.php') ?>" class="nav-item <?= $currentScript === 'events.php' ? 'active' : '' ?>">
                <i class="nav-icon bi bi-calendar2-event"></i> School Events
            </a>
            <a href="<?= url('teacher/students.php') ?>" class="nav-item <?= $currentScript === 'students.php' ? 'active' : '' ?>">
                <i class="nav-icon bi bi-people"></i> Enrolled Students
            </a>

        <?php elseif ($role === 'parent'): ?>
            <!-- Parent Navigation -->
            <div class="nav-section-title">My Child</div>
            <a href="<?= url('parent/index.php') ?>" class="nav-item <?= $currentScript === 'index.php' ? 'active' : '' ?>">
                <i class="nav-icon bi bi-backpack"></i> Child Overview
            </a>
            <a href="<?= url('parent/progress.php') ?>" class="nav-item <?= $currentScript === 'progress.php' ? 'active' : '' ?>">
                <i class="nav-icon bi bi-star-fill"></i> Academic Milestones
            </a>
            <a href="<?= url('parent/attendance.php') ?>" class="nav-item <?= $currentScript === 'attendance.php' ? 'active' : '' ?>">
                <i class="nav-icon bi bi-calendar"></i> Attendance Record
            </a>

            <div class="nav-section-title">Security & Finances</div>
            <a href="<?= url('parent/pickups.php') ?>" class="nav-item <?= $currentScript === 'pickups.php' ? 'active' : '' ?>">
                <i class="nav-icon bi bi-shield"></i> Authorized Pickups
            </a>
            <a href="<?= url('parent/fees.php') ?>" class="nav-item <?= $currentScript === 'fees.php' ? 'active' : '' ?>">
                <i class="nav-icon bi bi-credit-card"></i> Fees & Payments
            </a>

            <div class="nav-section-title">Community & Updates</div>
            <a href="<?= url('parent/calendar.php') ?>" class="nav-item <?= $currentScript === 'calendar.php' ? 'active' : '' ?>">
                <i class="nav-icon bi bi-calendar3"></i> School Calendar
            </a>
            <a href="<?= url('parent/messages.php') ?>" class="nav-item <?= $currentScript === 'messages.php' ? 'active' : '' ?>">
                <i class="nav-icon bi bi-chat-dots"></i> Message Teacher
                <?php if ($unreadMessages > 0): ?>
                    <span class="nav-badge badge-primary"><?= $unreadMessages ?></span>
                <?php endif; ?>
            </a>
            <a href="<?= url('parent/notifications.php') ?>" class="nav-item <?= $currentScript === 'notifications.php' ? 'active' : '' ?>">
                <i class="nav-icon bi bi-bell-fill"></i> Notifications
                <?php if ($unreadNotifs > 0): ?>
                    <span class="nav-badge badge-danger"><?= $unreadNotifs ?></span>
                <?php endif; ?>
            </a>
        <?php endif; ?>
    </div>

    <?php if ($activeEmergency): ?>
        <div class="sidebar-emergency" role="alert">
            <div class="sidebar-emergency-title"><i class="bi bi-exclamation-triangle"></i> Emergency Alert</div>
            <strong><?= htmlspecialchars($activeEmergency['title']) ?></strong>
            <p><?= htmlspecialchars($activeEmergency['message']) ?></p>
            <small><?= date('M d, h:i A', strtotime($activeEmergency['created_at'])) ?></small>
            <?php if ($role === 'teacher'): ?>
                <a href="<?= url('teacher/emergency.php') ?>" class="sidebar-emergency-link">Manage alert</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- User Profile Section -->
    <div class="sidebar-profile-section">
        <div class="sidebar-profile-card">
            <div class="sidebar-profile-avatar">
                <?= strtoupper(substr($user['name'] ?? 'U', 0, 1)) ?>
            </div>
            <div class="sidebar-profile-info">
                <div class="sidebar-profile-name"><?= htmlspecialchars($user['name'] ?? 'User') ?></div>
                <div class="sidebar-profile-role"><?= strtoupper($role ?? 'User') ?></div>
            </div>
            <div class="sidebar-profile-dropdown">
                <a href="<?= url('settings.php') ?>" class="sidebar-profile-link">
                    <i class="bi bi-gear"></i> Settings
                </a>
                <a href="<?= url('profile.php') ?>" class="sidebar-profile-link">
                    <i class="bi bi-person-circle"></i> Edit Profile
                </a>
            </div>
        </div>
    </div>

</aside>
