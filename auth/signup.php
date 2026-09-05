<?php
/**
 * Account Registration (Signup)
 * Preschool Monitoring System
 * Fulfills:
 * - Admin: "Signup: As an admin, I want to create an account so that I can access the system"
 * - Parent: "Signup: As a parent, I want to create an account so that I can access the system"
 * - Admin: "approve parent accounts: As an admin, I want to view and manage approval for user"
 */

require_once dirname(__DIR__) . '/config/config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $phone = trim($_POST['phone'] ?? '');

    if (empty($name) || empty($email) || empty($password) || empty($phone)) {
        $error = 'All fields are required. Please complete the form.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please provide a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        // Public registration is intentionally limited to parent accounts.
        $role = 'parent';
        $db = getDB();
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'An account with that email address already exists. Please login instead.';
        } else {
            $status = 'pending_approval';
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $db->prepare("INSERT INTO users (name, email, password, role, phone, status, created_at) VALUES (?, ?, ?, ?, ?, ?, datetime('now'))");
            $stmt->execute([$name, $email, $hash, $role, $phone, $status]);
            $newUserId = $db->lastInsertId();

            $admin = $db->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1")->fetch();
            if ($admin) {
                $notifStmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, 'system', 'admin/approvals.php')");
                $notifStmt->execute([$admin['id'], 'New Parent Account Approval Needed', "{$name} registered as a parent and is waiting for approval."]);
            }

            logActivity('Parent Signup Submitted', "Parent {$name} submitted registration (pending approval)", $newUserId);
            setFlash('info', "Registration submitted successfully! Your account is pending Admin approval. You will be able to log in once approved by the preschool.");

            header("Location: login.php");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account | <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/css/dashboard.css') ?>">
    <style>
        .auth-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: radial-gradient(circle at 10% 20%, rgb(240, 244, 255) 0%, rgb(255, 250, 240) 90.3%);
            padding: 24px;
        }
        .auth-card {
            background: #FFFFFF;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-xl);
            max-width: 520px;
            width: 100%;
            padding: 38px;
            border: 1px solid var(--border-color);
        }
    </style>
</head>
<body>

<div class="auth-container">
    <div class="auth-card">
        <div style="text-align: center; margin-bottom: 24px;">
            <div style="font-size: 2.8rem; margin-bottom: 6px;">🌱</div>
            <h2>Create New Account</h2>
            <p style="color: var(--text-muted); font-size: 0.88rem;">Join <?= APP_NAME ?> community</p>
        </div>

        <?php if ($error): ?>
            <div class="flash-alert flash-danger">
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="signup.php">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

            <div class="form-group">
                <label class="form-label">Account Type</label>
                <div class="form-control" style="background: var(--bg-card-subtle);">Parent / Legal Guardian</div>
                <small style="color: var(--text-muted); font-size: 0.76rem;">Parent accounts undergo verification before full activation. Teachers are added by the school administrator.</small>
            </div>

            <div class="form-group">
                <label class="form-label" for="name">Full Name</label>
                <input type="text" name="name" id="name" class="form-control" placeholder="e.g. Maria Clara Santos" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="email">Email Address</label>
                <input type="email" name="email" id="email" class="form-control" placeholder="name@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="phone">Mobile / Contact Number</label>
                <input type="text" name="phone" id="phone" class="form-control" placeholder="+63 9XX XXX XXXX" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" required>
            </div>

            <div class="form-group" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div>
                    <label class="form-label" for="password">Password</label>
                    <input type="password" name="password" id="password" class="form-control" placeholder="••••••••" required>
                </div>
                <div>
                    <label class="form-label" for="confirm_password">Confirm Password</label>
                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="••••••••" required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-lg" style="width: 100%; margin-top: 10px;">
                Complete Registration
            </button>
        </form>

        <div style="text-align: center; margin-top: 22px; font-size: 0.88rem; color: var(--text-secondary);">
            Already have an account? 
            <a href="login.php" style="font-weight: 700;">Sign In</a>
        </div>
    </div>
</div>

</body>
</html>
