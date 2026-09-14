<?php
/**
 * Google Calendar Setup & Authentication
 * Admin Page for Connecting Google Calendar API
 */

$pageTitle = 'Google Calendar Setup';
$pageSubtitle = 'Connect your school calendar';

require_once dirname(__DIR__) . '/includes/auth.php';
checkAuth(['admin']);

require_once dirname(__DIR__) . '/config/google-calendar.php';
require_once dirname(__DIR__) . '/includes/google-calendar-helper.php';

$error = '';
$success = '';

// Check for authorization callback
if (isset($_GET['status'])) {
    if ($_GET['status'] === 'success') {
        $success = 'Google Calendar connected successfully!';
    } else {
        $error = 'Failed to connect Google Calendar: ' . ($_GET['message'] ?? 'Unknown error');
    }
}

// Handle disconnect
if (isset($_POST['disconnect']) && $_POST['disconnect'] === '1') {
    $tokenPath = GOOGLE_CALENDAR_TOKEN_DIR . '/calendar-token.json';
    if (file_exists($tokenPath)) {
        unlink($tokenPath);
        $success = 'Google Calendar disconnected successfully.';
    }
}

// Check if already connected
$isConnected = getGoogleCalendarToken() !== null;

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="card" style="max-width: 600px; margin: 0 auto;">
    <div class="card-header">
        <h3 class="card-title">🔗 Google Calendar Integration</h3>
    </div>

    <?php if ($error): ?>
        <div class="flash-alert flash-danger" style="margin-bottom: 16px;">
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="flash-alert flash-success" style="margin-bottom: 16px;">
            <span><?= htmlspecialchars($success) ?></span>
        </div>
    <?php endif; ?>

    <div style="margin-bottom: 20px;">
        <h4 style="margin-bottom: 12px; font-size: 1rem;">Status</h4>
        <div style="padding: 14px; border-radius: var(--radius-md); background: <?= $isConnected ? '#E8F5F0' : '#FAFAF8' ?>; border: 1px solid var(--border-color);">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 1.2rem;"><?= $isConnected ? '✅' : '❌' ?></span>
                <span style="font-weight: 600; color: <?= $isConnected ? 'var(--primary)' : 'var(--text-muted)' ?>;">
                    <?= $isConnected ? 'Connected' : 'Not Connected' ?>
                </span>
            </div>
        </div>
    </div>

    <?php if ($isConnected): ?>
        <div style="margin-bottom: 20px; padding: 14px; border-radius: var(--radius-md); background: #FEF9E7; border: 1px solid var(--secondary);">
            <p style="margin: 0; color: var(--text-secondary); font-size: 0.9rem;">
                Your school calendar is connected to Google Calendar. Events will sync automatically.
            </p>
        </div>

        <form method="POST" style="margin-bottom: 16px;">
            <input type="hidden" name="disconnect" value="1">
            <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to disconnect Google Calendar?');">
                Disconnect Google Calendar
            </button>
        </form>
    <?php else: ?>
        <div style="margin-bottom: 20px;">
            <h4 style="margin-bottom: 12px; font-size: 1rem;">Setup Instructions</h4>
            <ol style="color: var(--text-secondary); font-size: 0.9rem; margin: 0; padding-left: 20px;">
                <li>Go to <a href="https://console.cloud.google.com" target="_blank" style="color: var(--primary);">Google Cloud Console</a></li>
                <li>Create a new project or select an existing one</li>
                <li>Enable the Google Calendar API</li>
                <li>Create OAuth 2.0 credentials (Desktop application)</li>
                <li>Copy Client ID and Client Secret</li>
                <li>Update configuration in <code style="background: #F5F3F1; padding: 2px 6px; border-radius: 4px;">config/google-calendar.php</code></li>
                <li>Return here and click "Connect"</li>
            </ol>
        </div>

        <div style="margin-bottom: 20px; padding: 14px; border-radius: var(--radius-md); background: #EAF2F8; border: 1px solid var(--sky); border-left-width: 4px; border-left-color: var(--sky);">
            <p style="margin: 0; color: var(--sky); font-size: 0.9rem;">
                <strong>Note:</strong> You must configure your Google Calendar API credentials before connecting.
            </p>
        </div>

        <a href="<?php 
            require_once dirname(__DIR__) . '/config/google-calendar.php';
            try {
                $client = new Google_Client();
                $client->setApplicationName('Play is School');
                $client->setClientId(GOOGLE_CALENDAR_CLIENT_ID);
                $client->setClientSecret(GOOGLE_CALENDAR_CLIENT_SECRET);
                $client->setRedirectUri(GOOGLE_CALENDAR_REDIRECT_URI);
                $client->addScope(Google_Service_Calendar::CALENDAR);
                $client->addScope(Google_Service_Calendar::CALENDAR_READONLY);
                echo $client->createAuthUrl();
            } catch (Exception $e) {
                echo '#error';
            }
        ?>" class="btn btn-primary">
            🔐 Connect to Google Calendar
        </a>
    <?php endif; ?>

    <hr style="border: none; border-top: 1px solid var(--border-color); margin: 24px 0;">

    <div>
        <h4 style="margin-bottom: 12px; font-size: 1rem;">Configuration Status</h4>
        <div style="font-size: 0.85rem; color: var(--text-secondary); display: grid; gap: 8px;">
            <div style="display: flex; gap: 8px;">
                <span><?= (GOOGLE_CALENDAR_CLIENT_ID !== 'YOUR_CLIENT_ID.apps.googleusercontent.com') ? '✅' : '❌' ?></span>
                <span>Client ID: <?= (GOOGLE_CALENDAR_CLIENT_ID !== 'YOUR_CLIENT_ID.apps.googleusercontent.com') ? 'Configured' : 'Not Configured' ?></span>
            </div>
            <div style="display: flex; gap: 8px;">
                <span><?= (GOOGLE_CALENDAR_CLIENT_SECRET !== 'YOUR_CLIENT_SECRET') ? '✅' : '❌' ?></span>
                <span>Client Secret: <?= (GOOGLE_CALENDAR_CLIENT_SECRET !== 'YOUR_CLIENT_SECRET') ? 'Configured' : 'Not Configured' ?></span>
            </div>
            <div style="display: flex; gap: 8px;">
                <span>✅</span>
                <span>Redirect URI: <?= htmlspecialchars(GOOGLE_CALENDAR_REDIRECT_URI) ?></span>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
