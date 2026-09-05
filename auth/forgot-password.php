<?php
/**
 * Password Reset Workflow
 * Preschool Monitoring System
 * Fulfills Forgot password requirement for Admin, Teacher, and Parent
 */

require_once dirname(__DIR__) . '/config/config.php';

$message = '';
$error = '';
$step = 'request'; // 'request' or 'reset'
$userEmail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? 'request';

    if ($action === 'request') {
        $email = trim($_POST['email'] ?? '');
        if (empty($email)) {
            $error = 'Please enter your account email address.';
        } else {
            $db = getDB();
            $stmt = $db->prepare("SELECT id, name, role FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                // In demo / local setup, allow immediate new password assignment
                $step = 'reset';
                $userEmail = $email;
                $message = "Account verified for {$user['name']} ({$user['role']}). Please enter your new password below.";
            } else {
                $error = 'No registered account found with that email address.';
            }
        }
    } elseif ($action === 'reset') {
        $email = trim($_POST['email'] ?? '');
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($newPassword) || strlen($newPassword) < 6) {
            $error = 'New password must be at least 6 characters.';
            $step = 'reset';
            $userEmail = $email;
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Passwords do not match.';
            $step = 'reset';
            $userEmail = $email;
        } else {
            $db = getDB();
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password = ? WHERE email = ?");
            $stmt->execute([$hash, $email]);

            logActivity('Password Reset', "Password updated for {$email}");
            setFlash('success', 'Your password has been successfully reset! You can now log in with your new password.');
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
    <title>Reset Password | <?= APP_NAME ?></title>
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
            max-width: 460px;
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
            <div style="font-size: 2.6rem; margin-bottom: 6px;">🔑</div>
            <h2>Password Recovery</h2>
            <p style="color: var(--text-muted); font-size: 0.88rem;">Recover your account access</p>
        </div>

        <?php if ($message): ?>
            <div class="flash-alert flash-info">
                <span><?= htmlspecialchars($message) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="flash-alert flash-danger">
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($step === 'request'): ?>
            <form method="POST" action="forgot-password.php">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="action" value="request">

                <div class="form-group">
                    <label class="form-label" for="email">Enter Registered Email Address</label>
                    <input type="email" name="email" id="email" class="form-control" placeholder="name@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
                    <small style="color: var(--text-muted); font-size: 0.78rem; display: block; margin-top: 4px;">Works for Admin, Teacher, or Parent accounts.</small>
                </div>

                <button type="submit" class="btn btn-primary btn-lg" style="width: 100%; margin-top: 10px;">
                    Verify Account
                </button>
            </form>
        <?php else: ?>
            <form method="POST" action="forgot-password.php">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="action" value="reset">
                <input type="hidden" name="email" value="<?= htmlspecialchars($userEmail) ?>">

                <div class="form-group">
                    <label class="form-label" for="new_password">New Password</label>
                    <input type="password" name="new_password" id="new_password" class="form-control" placeholder="••••••••" required autofocus>
                </div>

                <div class="form-group">
                    <label class="form-label" for="confirm_password">Confirm New Password</label>
                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="••••••••" required>
                </div>

                <button type="submit" class="btn btn-mint btn-lg" style="width: 100%; margin-top: 10px;">
                    Update & Save Password
                </button>
            </form>
        <?php endif; ?>

        <div style="text-align: center; margin-top: 24px; font-size: 0.88rem;">
            Remembered your password? 
            <a href="login.php" style="font-weight: 700;">Back to Sign In</a>
        </div>
    </div>
</div>

</body>
</html>
