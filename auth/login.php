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
        .auth-wrapper {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 1fr 1fr;
            background: #f5f5f5;
            gap: 0;
        }

        .auth-sidebar {
            background: linear-gradient(135deg, #e8f5e9 0%, #f1f8f6 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 50px 40px;
            color: #2d5016;
            border-right: 1px solid #c8e6c9;
        }

        .auth-sidebar-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 40px;
            align-self: flex-start;
            color: #4caf6f;
        }

        .auth-sidebar-icon {
            width: 40px;
            height: 40px;
            background: #4caf6f;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
        }

        .auth-sidebar-header h3 {
            font-size: 15px;
            font-weight: 600;
            margin: 0;
            color: #4caf6f;
        }

        .auth-illustration {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 20px 0 40px 0;
            max-width: 280px;
        }

        .auth-illustration img {
            width: 100%;
            height: auto;
        }

        .auth-sidebar-content {
            text-align: center;
            width: 100%;
        }

        .auth-sidebar-content h3 {
            font-size: 18px;
            font-weight: 700;
            margin: 0 0 12px 0;
            color: #4caf6f;
        }

        .auth-sidebar-content p {
            font-size: 13px;
            margin: 0;
            color: #666;
            line-height: 1.6;
        }

        .auth-container {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 50px 40px;
            background: white;
        }

        .auth-card {
            width: 100%;
            max-width: 380px;
        }

        .auth-header {
            margin-bottom: 30px;
        }

        .auth-header h1 {
            font-size: 28px;
            font-weight: 700;
            color: #1a1a1a;
            margin: 0 0 8px 0;
        }

        .auth-header p {
            font-size: 13px;
            color: #999;
            margin: 0;
        }

        .role-selector {
            display: flex;
            gap: 0;
            margin: 28px 0 32px 0;
            border-bottom: 1px solid #e0e0e0;
        }

        .role-btn {
            flex: 1;
            background: transparent;
            border: none;
            border-bottom: 3px solid transparent;
            padding: 12px 16px;
            cursor: pointer;
            transition: all var(--trans-fast);
            font-size: 13px;
            font-weight: 600;
            color: #999;
            text-align: center;
        }

        .role-btn:hover {
            color: #4caf6f;
        }

        .role-btn.active {
            color: #4caf6f;
            border-bottom-color: #4caf6f;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            color: #999;
            margin-bottom: 6px;
            letter-spacing: 0.5px;
        }

        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            font-size: 13px;
            transition: all var(--trans-fast);
            background: #fafafa;
        }

        .form-control:focus {
            outline: none;
            border-color: #4caf6f;
            background: white;
            box-shadow: 0 0 0 2px rgba(76, 175, 111, 0.1);
        }

        .password-group {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
        }

        .password-group a {
            font-size: 12px;
            color: #4caf6f;
            text-decoration: none;
            font-weight: 600;
        }

        .password-group a:hover {
            text-decoration: underline;
        }

        .password-input-wrapper {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #999;
            font-size: 16px;
            padding: 0;
            line-height: 1;
        }

        .password-input-wrapper .form-control {
            padding-right: 40px;
        }

        .btn-signin {
            width: 100%;
            padding: 12px;
            background: #4caf6f;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all var(--trans-fast);
            margin-top: 8px;
        }

        .btn-signin:hover {
            background: #3d8b56;
            box-shadow: 0 2px 8px rgba(76, 175, 111, 0.3);
        }

        .auth-footer {
            text-align: center;
            margin-top: 24px;
            font-size: 13px;
            color: #999;
        }

        .auth-footer a {
            color: #4caf6f;
            text-decoration: none;
            font-weight: 700;
        }

        .auth-footer a:hover {
            text-decoration: underline;
        }

        @media (max-width: 1024px) {
            .auth-wrapper {
                grid-template-columns: 1fr;
            }

            .auth-sidebar {
                padding: 40px 30px;
                border-right: none;
                border-bottom: 1px solid #c8e6c9;
                order: -1;
            }

            .auth-container {
                padding: 40px 30px;
            }
        }
    </style>
</head>
<body>

<div class="auth-wrapper">
    <!-- Left Sidebar -->
    <div class="auth-sidebar">
        <div class="auth-sidebar-header">
            <div class="auth-sidebar-icon"></div>
            <h3><?= APP_NAME ?></h3>
        </div>

        <div class="auth-illustration">
            <img src="<?= BASE_URL ?>/img/school-illustration.svg" alt="Children learning together" onerror="this.style.display='none'">
        </div>

        <div class="auth-sidebar-content">
            <h3>Bridging Home and School Together</h3>
            <p><?= APP_SUBTITLE ?></p>
        </div>
    </div>

    <!-- Right Login Form -->
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h1>Welcome Back</h1>
                <p>Please select your role and enter credentials to sign in.</p>
            </div>

            <!-- Role Selection Tabs -->
            <div class="role-selector">
                <button type="button" class="role-btn active" data-role="parent">Parent</button>
                <button type="button" class="role-btn" data-role="teacher">Teacher</button>
                <button type="button" class="role-btn" data-role="admin">Admin</button>
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

            <form method="POST" action="login.php" id="loginForm">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                
                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <input type="email" name="email" id="email" class="form-control" placeholder="robert@gmail.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
                </div>

                <div class="form-group">
                    <div class="password-group">
                        <label class="form-label" for="password" style="margin-bottom: 0;">Password</label>
                        <a href="forgot-password.php">Forgot Password?</a>
                    </div>
                    <div class="password-input-wrapper">
                        <input type="password" name="password" id="password" class="form-control" placeholder="••••••••" required>
                        <button type="button" class="password-toggle" id="togglePassword">👁️</button>
                    </div>
                </div>

                <button type="submit" class="btn-signin">Sign In</button>
            </form>

            <div class="auth-footer">
                New parent to the school? <a href="signup.php">Create Account</a>
            </div>
        </div>
    </div>
</div>

<script>
    // Handle role selection tabs
    document.querySelectorAll('.role-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.role-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
        });
    });

    // Handle password visibility toggle
    const passwordInput = document.getElementById('password');
    const togglePasswordBtn = document.getElementById('togglePassword');

    togglePasswordBtn.addEventListener('click', function(e) {
        e.preventDefault();
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            togglePasswordBtn.textContent = '';
        } else {
            passwordInput.type = 'password';
            togglePasswordBtn.textContent = '';
        }
    });
</script>
</html>
