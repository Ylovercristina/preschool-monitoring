<?php
/**
 * Login Page — Play-Is-School
 * Role is determined server-side after authentication; no role selector in UI.
 */

require_once dirname(__DIR__) . '/config/config.php';

// Redirect already-authenticated users straight to their dashboard
if (isLoggedIn()) {
    $role = userRole();
    if (in_array($role, ['admin', 'teacher', 'parent'])) {
        header('Location: ' . url($role . '/index.php'));
        exit;
    }
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $email    = trim($_POST['email']    ?? '');
    $password =       $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter both your email address and password.';
    } else {
        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] === 'pending_approval') {
                $error = 'Your account is pending admin approval. Please check back shortly.';
            } elseif (in_array($user['status'], ['archived', 'rejected'])) {
                $error = 'This account has been deactivated. Please contact the school administrator.';
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user']    = $user;
                logActivity('User Login', "User {$user['name']} logged in", $user['id']);
                setFlash('success', "Welcome back, {$user['name']}!");
                // Role-based redirect — determined by the stored role, invisible to the user
                header('Location: ' . url($user['role'] . '/index.php'));
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
    <title>Sign In | <?= htmlspecialchars(APP_NAME) ?></title>
    <meta name="description" content="Sign in to <?= htmlspecialchars(APP_NAME) ?> — <?= htmlspecialchars(APP_SUBTITLE) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ── Reset & base ───────────────────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --green:        #4CAF6E;
            --green-dark:   #3a9459;
            --green-deep:   #2d6e43;
            --green-pale:   #EBF7F0;
            --green-light:  #D4EDDA;
            --green-glow:   rgba(76, 175, 110, 0.18);
            --text-dark:    #1A2130;
            --text-mid:     #5A6478;
            --text-light:   #9BA5B7;
            --border:       #D5DAE4;
            --card-border:  #1A2130;
            --page-bg:      #F5F7FA;
            --white:        #ffffff;
            --radius-card:  18px;
            --radius-input: 10px;
            --radius-btn:   10px;
            --trans:        0.22s ease;
            --shadow-card:  0 8px 40px rgba(26,33,48,0.10);
        }

        html, body {
            height: 100%;
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            background: var(--page-bg);
            color: var(--text-dark);
            -webkit-font-smoothing: antialiased;
        }

        /* ── Outer page shell — 8% margin all sides ─────────────────── */
        .page-shell {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 8vh 8vw;
            background:
                radial-gradient(ellipse 60% 40% at 15% 20%, rgba(76,175,110,0.08) 0%, transparent 70%),
                radial-gradient(ellipse 50% 35% at 85% 80%, rgba(85, 215, 130, 0.06) 0%, transparent 70%),
                var(--page-bg);
        }

        /* ── Card wrapper — the bordered container ───────────────────── */
        .login-card {
            width: 100%;
            max-width: 920px;
            display: flex;
            border: none;
            border-radius: var(--radius-card);
            overflow: hidden;
            box-shadow: 0 20px 50px rgba(76, 175, 110, 0.25), 0 4px 16px rgba(76, 175, 110, 0.10);
            background: var(--white);
            /* Subtle entry animation */
            animation: cardIn 0.45s cubic-bezier(0.22, 1, 0.36, 1) both;
        }

        @keyframes cardIn {
            from { opacity: 0; transform: translateY(22px) scale(0.985); }
            to   { opacity: 1; transform: translateY(0)    scale(1); }
        }

        /* ══════════════════════════════════════════════════════════════
           LEFT PANEL
        ══════════════════════════════════════════════════════════════ */
        .left-panel {
            flex: 0 0 44%;
            background: linear-gradient(160deg, #3fa865 0%, #52c278 50%, #6ed48e 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 48px 36px;
            position: relative;
            overflow: hidden;
        }

        /* Decorative blobs */
        .left-panel::before,
        .left-panel::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            pointer-events: none;
        }
        .left-panel::before {
            width: 320px; height: 320px;
            background: rgba(255,255,255,0.07);
            top: -80px; left: -80px;
        }
        .left-panel::after {
            width: 220px; height: 220px;
            background: rgba(255,255,255,0.06);
            bottom: -60px; right: -60px;
        }

        /* Logo badge */
        .brand-badge {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 32px;
            align-self: flex-start;
            z-index: 1;
        }

        .brand-icon {
            width: 38px; height: 38px;
            background: transparent;
            border: none;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            overflow: hidden;
        }
        .brand-icon img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
        }

        .brand-name {
            font-size: 14px;
            font-weight: 800;
            color: #fff;
            letter-spacing: -0.2px;
            line-height: 1.2;
        }
        .brand-name span {
            display: block;
            font-size: 10px;
            font-weight: 500;
            color: rgba(255,255,255,0.75);
            letter-spacing: 0.3px;
        }

        /* Illustration */
        .left-illustration {
            width: 100%;
            max-width: 260px;
            aspect-ratio: 1 / 1;
            border-radius: 16px;
            background: transparent;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 28px;
            z-index: 1;
            transition: transform var(--trans);
            padding: 12px;
        }
        .left-illustration:hover { transform: translateY(-3px); }

        .left-illustration img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
        }

        /* Headline copy */
        .left-copy { text-align: center; z-index: 1; }

        .left-copy h2 {
            font-size: 20px;
            font-weight: 800;
            color: #fff;
            line-height: 1.3;
            margin-bottom: 8px;
            text-shadow: 0 1px 4px rgba(0,0,0,0.12);
        }

        .left-copy p {
            font-size: 12.5px;
            color: rgba(255,255,255,0.82);
            line-height: 1.6;
            font-weight: 500;
        }

        /* Feature dots */
        .left-dots {
            display: flex;
            gap: 6px;
            margin-top: 20px;
            justify-content: center;
            z-index: 1;
        }
        .left-dots span {
            width: 6px; height: 6px;
            border-radius: 50%;
            background: rgba(255,255,255,0.4);
        }
        .left-dots span:first-child { background: #fff; width: 18px; border-radius: 3px; }

        /* ══════════════════════════════════════════════════════════════
           RIGHT PANEL
        ══════════════════════════════════════════════════════════════ */
        .right-panel {
            flex: 1;
            background: var(--white);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 52px 48px;
        }

        .form-wrap { width: 100%; max-width: 360px; }

        /* Welcome header */
        .form-header { margin-bottom: 32px; }

        .form-header h1 {
            font-size: 26px;
            font-weight: 800;
            color: var(--text-dark);
            letter-spacing: -0.5px;
            margin-bottom: 6px;
        }

        .form-header p {
            font-size: 13px;
            color: var(--text-mid);
            line-height: 1.55;
            font-weight: 500;
        }

        /* Flash / error alerts */
        .alert {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 12px 14px;
            border-radius: 9px;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 22px;
            line-height: 1.45;
            animation: alertIn 0.25s ease both;
        }
        @keyframes alertIn {
            from { opacity:0; transform: translateY(-6px); }
            to   { opacity:1; transform: translateY(0); }
        }
        .alert-error   { background: #FFF0F0; color: #C0392B; border: 1px solid #FFCDD2; }
        .alert-success { background: #F0FFF5; color: #2d6e43; border: 1px solid #C8E6C9; }
        .alert-warning { background: #FFFDE7; color: #7B5E02; border: 1px solid #FFF176; }
        .alert-icon { font-size: 16px; flex-shrink: 0; margin-top: 1px; }

        /* Form elements */
        .field { margin-bottom: 18px; }

        .field-label {
            display: block;
            font-size: 11.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: var(--text-mid);
            margin-bottom: 7px;
        }

        .field-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 7px;
        }
        .field-row .field-label { margin-bottom: 0; }

        .forgot-link {
            font-size: 12px;
            font-weight: 600;
            color: var(--green);
            text-decoration: none;
            transition: color var(--trans);
        }
        .forgot-link:hover { color: var(--green-dark); text-decoration: underline; }

        .input-wrap { position: relative; }

        .form-input {
            width: 100%;
            padding: 11px 14px;
            border: 1.5px solid var(--border);
            border-radius: var(--radius-input);
            font-family: inherit;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-dark);
            background: #FAFBFC;
            transition: border-color var(--trans), box-shadow var(--trans), background var(--trans);
            outline: none;
        }
        .form-input::placeholder { color: var(--text-light); font-weight: 400; }
        .form-input:focus {
            border-color: var(--green);
            background: var(--white);
            box-shadow: 0 0 0 3px var(--green-glow);
        }
        .form-input.has-toggle { padding-right: 44px; }

        /* Password toggle eye */
        .eye-btn {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: var(--text-light);
            font-size: 17px;
            line-height: 1;
            padding: 2px 4px;
            border-radius: 4px;
            transition: color var(--trans);
        }
        .eye-btn:hover { color: var(--green); }

        /* Sign-in button */
        .btn-signin {
            width: 100%;
            padding: 13px;
            background: linear-gradient(135deg, #4CAF6E 0%, #3a9459 100%);
            color: #fff;
            border: none;
            border-radius: var(--radius-btn);
            font-family: inherit;
            font-size: 14.5px;
            font-weight: 700;
            cursor: pointer;
            letter-spacing: 0.2px;
            margin-top: 6px;
            position: relative;
            overflow: hidden;
            transition: box-shadow var(--trans), transform var(--trans), filter var(--trans);
        }
        .btn-signin::after {
            content: '';
            position: absolute;
            inset: 0;
            background: rgba(255,255,255,0);
            transition: background var(--trans);
        }
        .btn-signin:hover {
            box-shadow: 0 6px 20px rgba(76,175,110,0.40);
            transform: translateY(-1px);
            filter: brightness(1.04);
        }
        .btn-signin:active { transform: translateY(0); box-shadow: none; filter: brightness(0.97); }

        .btn-signin .btn-text  { position: relative; z-index: 1; }
        .btn-signin .btn-arrow { position: relative; z-index: 1; margin-left: 6px; transition: transform var(--trans); display: inline-block; }
        .btn-signin:hover .btn-arrow { transform: translateX(3px); }

        /* Loading spinner (shows on submit) */
        .btn-signin.loading .btn-text  { opacity: 0; }
        .btn-signin.loading::before {
            content: '';
            position: absolute;
            width: 18px; height: 18px;
            border: 2.5px solid rgba(255,255,255,0.35);
            border-top-color: #fff;
            border-radius: 50%;
            top: 50%; left: 50%;
            transform: translate(-50%,-50%);
            animation: spin 0.7s linear infinite;
        }
        @keyframes spin { to { transform: translate(-50%,-50%) rotate(360deg); } }

        /* Divider */
        .divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 22px 0;
            color: var(--text-light);
            font-size: 12px;
        }
        .divider::before, .divider::after {
            content: ''; flex: 1;
            height: 1px; background: var(--border);
        }

        /* Footer link */
        .form-footer {
            text-align: center;
            font-size: 13px;
            color: var(--text-mid);
            font-weight: 500;
        }
        .form-footer a {
            color: var(--green);
            font-weight: 700;
            text-decoration: none;
            transition: color var(--trans);
        }
        .form-footer a:hover { color: var(--green-dark); text-decoration: underline; }

        /* ── Responsive — stack vertically on small screens ──────────── */
        @media (max-width: 768px) {
            .page-shell { padding: 8vw; }

            .login-card {
                flex-direction: column;
                max-width: 480px;
            }

            .left-panel {
                flex: none;
                padding: 36px 28px;
            }

            .left-illustration { max-width: 180px; margin-bottom: 20px; }

            .left-copy h2 { font-size: 17px; }

            .right-panel { padding: 36px 28px; }

            .form-header h1 { font-size: 22px; }
        }

        @media (max-width: 420px) {
            .page-shell { padding: 6vw; }
            .right-panel { padding: 28px 20px; }
            .left-panel  { padding: 28px 20px; }
        }
    </style>
</head>
<body>

<div class="page-shell">
    <div class="login-card">

        <!-- ══ LEFT PANEL ══ -->
        <div class="left-panel">
            <!-- Brand badge -->
            <div class="brand-badge">
                <div class="brand-icon">
                    <img src="<?= url('img/logo.png') ?>" alt="Play-Is-School logo">
                </div>
                <div class="brand-name">
                    <?= htmlspecialchars(APP_NAME) ?>
                    <span>Learning Platform</span>
                </div>
            </div>

            <!-- Logo -->
            <div class="left-illustration">
                <img src="<?= url('img/logo.png') ?>"
                     alt="Play-Is-School — Learning Through Play">
            </div>

            <!-- Tagline -->
            <div class="left-copy">
                <h2>Learning through Play</h2>
                <p><?= htmlspecialchars(APP_SUBTITLE) ?></p>
            </div>


        </div>

        <!-- ══ RIGHT PANEL ══ -->
        <div class="right-panel">
            <div class="form-wrap">

                <!-- Header -->
                <div class="form-header">
                    <h1>Welcome Back </h1>
                    <p>Please enter your credentials to sign in.</p>
                </div>

                <!-- Flash message (e.g. "logged out successfully") -->
                <?php if ($flash): ?>
                    <?php
                        $fType = $flash['type'] === 'success' ? 'alert-success'
                               : ($flash['type'] === 'warning' ? 'alert-warning' : 'alert-error');
                        $fIcon = $flash['type'] === 'success' ? ''
                               : ($flash['type'] === 'warning' ? '' : 'ℹ');
                    ?>
                    <div class="alert <?= $fType ?>">
                        <span class="alert-icon"><?= $fIcon ?></span>
                        <span><?= htmlspecialchars($flash['message']) ?></span>
                    </div>
                <?php endif; ?>

                <!-- Error message -->
                <?php if ($error): ?>
                    <div class="alert alert-error" id="loginError">
                        <span class="alert-icon"></span>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endif; ?>

                <!-- Login form -->
                <form method="POST" action="login.php" id="loginForm" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

                    <!-- Email -->
                    <div class="field">
                        <label class="field-label" for="email">Email Address</label>
                        <div class="input-wrap">
                            <input
                                type="email"
                                name="email"
                                id="email"
                                class="form-input"
                                placeholder="juan_delacruz@gmail.com"
                                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                required
                                autofocus
                                autocomplete="email">
                        </div>
                    </div>

                    <!-- Password -->
                    <div class="field">
                        <div class="field-row">
                            <label class="field-label" for="password">Password</label>
                            <a href="forgot-password.php" class="forgot-link">Forgot Password?</a>
                        </div>
                        <div class="input-wrap">
                            <input
                                type="password"
                                name="password"
                                id="password"
                                class="form-input has-toggle"
                                placeholder="••••••••"
                                required
                                autocomplete="current-password">
                            <button type="button" class="eye-btn" id="togglePassword" aria-label="Show/hide password">
                                <i class="bi bi-eye-slash"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Submit -->
                    <button type="submit" class="btn-signin" id="signInBtn">
                        <span class="btn-text">Sign In <span class="btn-arrow">→</span></span>
                    </button>
                </form>

                <!-- Divider + register link -->
                <div class="divider">or</div>

                <div class="form-footer">
                    New parent to the school?
                    <a href="signup.php">Create Account</a>
                </div>

            </div><!-- /form-wrap -->
        </div><!-- /right-panel -->

    </div><!-- /login-card -->
</div><!-- /page-shell -->

<script>
    /* ── Password visibility toggle ── */
    const pwInput  = document.getElementById('password');
    const eyeBtn   = document.getElementById('togglePassword');
    let   pwVis    = false;

    eyeBtn.addEventListener('click', () => {
        pwVis = !pwVis;
        pwInput.type   = pwVis ? 'text' : 'password';
        eyeBtn.textContent = pwVis ? '' : '';
    });

    /* ── Loading state on submit ── */
    const loginForm  = document.getElementById('loginForm');
    const signInBtn  = document.getElementById('signInBtn');

    loginForm.addEventListener('submit', (e) => {
        // Basic client-side guard
        const email = document.getElementById('email').value.trim();
        const pass  = pwInput.value;
        if (!email || !pass) { e.preventDefault(); return; }

        signInBtn.classList.add('loading');
        signInBtn.disabled = true;
    });

    /* ── Auto-dismiss error alert after 6 s ── */
    const errAlert = document.getElementById('loginError');
    if (errAlert) {
        setTimeout(() => {
            errAlert.style.transition = 'opacity 0.5s';
            errAlert.style.opacity    = '0';
            setTimeout(() => errAlert.remove(), 500);
        }, 6000);
    }
</script>
</body>
</html>
