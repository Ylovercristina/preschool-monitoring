<?php
/**
 * User Account Management (Add, Edit, Archive)
 * Preschool Monitoring System
 * Fulfills:
 * - "Add, Archive, Edit: As an admin, I want to be able to add, archive and edit users information."
 * - "manage parent and teacher accounts... As an admin, I want to manage parent and teacher accounts"
 */

$pageTitle = 'User Account Management';
$pageSubtitle = 'Add, edit, archive and manage administrative, faculty and parent user profiles';

require_once dirname(__DIR__) . '/includes/auth.php';
checkAuth(['admin']);

$db = getDB();

// Handle User Actions (Create, Update, Archive, Restore)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = $_POST['role'] ?? 'parent';
        $phone = trim($_POST['phone'] ?? '');
        $status = $_POST['status'] ?? 'active';

        if (empty($name) || empty($email)) {
            setFlash('danger', 'Name and email are required fields.');
        } else {
            if ($action === 'create') {
                $password = !empty($_POST['password']) ? $_POST['password'] : 'preschool123';
                $hash = password_hash($password, PASSWORD_DEFAULT);

                // Check uniqueness
                $check = $db->prepare("SELECT id FROM users WHERE email = ?");
                $check->execute([$email]);
                if ($check->fetch()) {
                    setFlash('danger', 'A user with that email already exists.');
                } else {
                    $stmt = $db->prepare("INSERT INTO users (name, email, password, role, phone, status, created_at) VALUES (?, ?, ?, ?, ?, ?, datetime('now'))");
                    $stmt->execute([$name, $email, $hash, $role, $phone, $status]);
                    logActivity('User Created', "Admin created user account for {$name} ({$role})");
                    setFlash('success', "User account for {$name} created successfully!");
                }
            } else {
                $id = (int)$_POST['id'];
                if (!empty($_POST['password'])) {
                    $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $stmt = $db->prepare("UPDATE users SET name=?, email=?, role=?, phone=?, status=?, password=? WHERE id=?");
                    $stmt->execute([$name, $email, $role, $phone, $status, $hash, $id]);
                } else {
                    $stmt = $db->prepare("UPDATE users SET name=?, email=?, role=?, phone=?, status=? WHERE id=?");
                    $stmt->execute([$name, $email, $role, $phone, $status, $id]);
                }
                logActivity('User Updated', "Admin updated user details for ID {$id} ({$name})");
                setFlash('success', "User account updated successfully!");
            }
            header('Location: users.php');
            exit;
        }
    } elseif ($action === 'archive') {
        $id = (int)$_POST['id'];
        if ($id === (int)$_SESSION['user_id']) {
            setFlash('danger', 'You cannot archive your own active admin account.');
        } else {
            $db->prepare("UPDATE users SET status = 'archived' WHERE id = ?")->execute([$id]);
            logActivity('User Archived', "Admin archived user ID {$id}");
            setFlash('warning', 'User account archived.');
        }
        header('Location: users.php');
        exit;
    } elseif ($action === 'restore') {
        $id = (int)$_POST['id'];
        $db->prepare("UPDATE users SET status = 'active' WHERE id = ?")->execute([$id]);
        logActivity('User Restored', "Admin restored user ID {$id}");
        setFlash('success', 'User account restored to active status.');
        header('Location: users.php');
        exit;
    }
}

// Filter by Role
$roleFilter = $_GET['role'] ?? 'all';
$query = "SELECT * FROM users";
$params = [];
if ($roleFilter !== 'all' && in_array($roleFilter, ['admin', 'teacher', 'parent'])) {
    $query .= " WHERE role = ?";
    $params[] = $roleFilter;
}
$query .= " ORDER BY status ASC, role ASC, name ASC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$usersList = $stmt->fetchAll();

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="d-flex justify-between align-center" style="margin-bottom: 24px; flex-wrap: wrap; gap: 14px;">
    <!-- Live Search & Role Tabs -->
    <div class="d-flex gap-3 align-center" style="flex: 1; max-width: 600px;">
        <input type="text" class="form-control" data-table-search="usersTable" placeholder="🔍 Search user by name, email, or phone...">
        <div class="d-flex gap-1" style="background: #E2E8F0; padding: 3px; border-radius: var(--radius-md);">
            <a href="users.php?role=all" class="btn btn-sm <?= $roleFilter === 'all' ? 'btn-primary' : 'btn-secondary' ?>">All</a>
            <a href="users.php?role=admin" class="btn btn-sm <?= $roleFilter === 'admin' ? 'btn-primary' : 'btn-secondary' ?>">Admin</a>
            <a href="users.php?role=teacher" class="btn btn-sm <?= $roleFilter === 'teacher' ? 'btn-primary' : 'btn-secondary' ?>">Teachers</a>
            <a href="users.php?role=parent" class="btn btn-sm <?= $roleFilter === 'parent' ? 'btn-primary' : 'btn-secondary' ?>">Parents</a>
        </div>
    </div>

    <!-- Action Button -->
    <button class="btn btn-primary" onclick="openUserModal()">
        <span>+</span> Add New User
    </button>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <span>👥</span> Registered System Accounts
            <span class="badge badge-primary"><?= count($usersList) ?> Users</span>
        </h3>
    </div>

    <div class="table-responsive">
        <table class="table" id="usersTable">
            <thead>
                <tr>
                    <th>User Profile</th>
                    <th>Email Address</th>
                    <th>Role</th>
                    <th>Mobile</th>
                    <th>Account Status</th>
                    <th>Created Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usersList as $u): ?>
                    <tr>
                        <td>
                            <div class="user-cell">
                                <div class="user-cell-avatar" style="background: <?= $u['role'] === 'admin' ? 'var(--grad-rose)' : ($u['role'] === 'teacher' ? 'var(--grad-mint)' : 'var(--grad-accent)') ?>;">
                                    <?= strtoupper(substr($u['name'], 0, 1)) ?>
                                </div>
                                <div>
                                    <strong class="user-cell-name"><?= htmlspecialchars($u['name']) ?></strong>
                                    <div class="user-cell-sub">User ID: #<?= $u['id'] ?></div>
                                </div>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td>
                            <span class="badge <?= $u['role'] === 'admin' ? 'badge-danger' : ($u['role'] === 'teacher' ? 'badge-primary' : 'badge-warning') ?>">
                                <?= strtoupper($u['role']) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($u['phone'] ?? 'None') ?></td>
                        <td>
                            <span class="badge <?= $u['status'] === 'active' ? 'badge-success' : ($u['status'] === 'pending_approval' ? 'badge-warning' : 'badge-danger') ?>">
                                <?= strtoupper(str_replace('_', ' ', $u['status'])) ?>
                            </span>
                        </td>
                        <td><?= formatDate($u['created_at']) ?></td>
                        <td>
                            <div class="d-flex gap-2">
                                <button class="btn btn-secondary btn-sm" onclick='editUser(<?= json_encode($u) ?>)'>
                                    ✏️ Edit
                                </button>
                                <?php if ($u['status'] === 'active'): ?>
                                    <form method="POST" action="users.php" onsubmit="return confirm('Archive this user account?');" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                        <input type="hidden" name="action" value="archive">
                                        <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Archive</button>
                                    </form>
                                <?php elseif ($u['status'] === 'archived'): ?>
                                    <form method="POST" action="users.php" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                        <input type="hidden" name="action" value="restore">
                                        <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                        <button type="submit" class="btn btn-mint btn-sm">Restore</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Add / Edit User -->
<div class="modal-overlay" id="userModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 class="modal-title" id="userModalTitle">Add New User</h3>
            <button class="modal-close" onclick="closeModal('userModal')">&times;</button>
        </div>
        <form method="POST" action="users.php">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" id="userFormAction" value="create">
            <input type="hidden" name="id" id="userId" value="">

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="name">Full Name *</label>
                    <input type="text" name="name" id="name" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">Email Address *</label>
                    <input type="email" name="email" id="email" class="form-control" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                    <div class="form-group">
                        <label class="form-label" for="role">User Role *</label>
                        <select name="role" id="role" class="form-select">
                            <option value="parent">Parent</option>
                            <option value="teacher">Teacher</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="status">Account Status *</label>
                        <select name="status" id="status" class="form-select">
                            <option value="active">Active</option>
                            <option value="pending_approval">Pending Approval</option>
                            <option value="archived">Archived</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="phone">Phone / Mobile</label>
                    <input type="text" name="phone" id="phone" class="form-control" placeholder="+63 9XX XXX XXXX">
                </div>

                <div class="form-group">
                    <label class="form-label" for="password" id="passwordLabel">Account Password</label>
                    <input type="password" name="password" id="password" class="form-control" placeholder="••••••••">
                    <small style="color: var(--text-muted); font-size: 0.78rem;" id="passwordHelp">Leave blank to keep current password when editing.</small>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('userModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save User</button>
            </div>
        </form>
    </div>
</div>

<script>
function openUserModal() {
    document.getElementById('userModalTitle').innerText = 'Add New User';
    document.getElementById('userFormAction').value = 'create';
    document.getElementById('userId').value = '';
    document.getElementById('name').value = '';
    document.getElementById('email').value = '';
    document.getElementById('role').value = 'parent';
    document.getElementById('status').value = 'active';
    document.getElementById('phone').value = '';
    document.getElementById('password').value = '';
    document.getElementById('passwordHelp').innerText = 'Default password will be preschool123 if left blank.';
    openModal('userModal');
}

function editUser(u) {
    document.getElementById('userModalTitle').innerText = 'Edit User: ' + u.name;
    document.getElementById('userFormAction').value = 'update';
    document.getElementById('userId').value = u.id;
    document.getElementById('name').value = u.name;
    document.getElementById('email').value = u.email;
    document.getElementById('role').value = u.role;
    document.getElementById('status').value = u.status;
    document.getElementById('phone').value = u.phone || '';
    document.getElementById('password').value = '';
    document.getElementById('passwordHelp').innerText = 'Leave blank to preserve existing password.';
    openModal('userModal');
}
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
