<?php
/**
 * Unified Login Page
 * Preschool Monitoring System
 * Features: Regular login and password verification
 */

require_once dirname(__DIR__) . '/config/config.php';

// Regular POST login
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter both your email address and password.';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] === 'pending_approval') {
                $error = 'Your account registration is pending Admin review and approval. Please check back shortly!';
            } elseif ($user['status'] === 'archived' || $user['status'] === 'rejected') {
                $error = 'This account has been deactivated. Please contact the preschool administrator.';
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user'] = $user;
                logActivity('User Login', "User {$user['name']} logged in", $user['id']);
                setFlash('success', "Welcome back, {$user['name']}!");
                header("Location: " . url($user['role'] . "/index.php"));
                exit;
            }
        } else {
            $error = 'Invalid email or password. Please try again.';
        }
    }
}

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In | <?= APP_NAME ?></title>
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
            max-width: 480px;
            width: 100%;
            padding: 40px;
            border: 1px solid var(--border-color);
        }
        .auth-header {
            text-align: center;
            margin-bottom: 28px;
        }
        .demo-pill-btn {
            background: var(--bg-card-subtle);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 10px;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            transition: all var(--trans-fast);
            color: var(--text-primary);
            text-decoration: none;
            flex: 1;
        }
        .demo-pill-btn:hover {
            border-color: var(--primary);
            background: var(--primary-light);
            transform: translateY(-2px);
        }
    </style>
</head>
<body>

<div class="auth-container">
    <div class="auth-card">
        <div class="auth-header">
            <div style="font-size: 3rem; margin-bottom: 8px;">🎒</div>
            <h2><?= APP_NAME ?></h2>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 4px;"><?= APP_SUBTITLE ?></p>
        </div>

        <?php if ($flash): ?>
            <div class="flash-alert flash-<?= htmlspecialchars($flash['type']) ?>">
                <span><?= htmlspecialchars($flash['message']) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="flash-alert flash-danger">
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            
            <div class="form-group">
                <label class="form-label" for="email">Email Address</label>
                <input type="email" name="email" id="email" class="form-control" placeholder="name@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
            </div>

            <div class="form-group">
                <div class="d-flex justify-between align-center" style="margin-bottom: 6px;">
                    <label class="form-label" for="password" style="margin-bottom: 0;">Password</label>
                    <a href="forgot-password.php" style="font-size: 0.82rem; font-weight: 600;">Forgot password?</a>
                </div>
                <input type="password" name="password" id="password" class="form-control" placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn btn-primary btn-lg" style="width: 100%; margin-top: 10px;">
                Sign In to Dashboard
            </button>
        </form>

        <div style="text-align: center; margin-top: 24px; font-size: 0.88rem; color: var(--text-secondary);">
            Don't have an account yet? 
            <a href="signup.php" style="font-weight: 700;">Create Account</a>
        </div>

        <div style="margin-top: 24px; border-top: 1px solid var(--border-subtle); padding-top: 16px; text-align: center; font-size: 0.74rem; color: var(--text-muted);">
            Preschool Monitoring System &bull; Group Project<br>
            <?= implode(' &bull; ', TEAM_MEMBERS) ?>
        </div>
    </div>
</div>

</body>
</html>
