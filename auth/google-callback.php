<?php
/**
 * Google Calendar OAuth Callback
 * Handles the OAuth 2.0 authorization flow
 */

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/google-calendar.php';

// Check if authorization code is provided
if (!isset($_GET['code'])) {
    http_response_code(400);
    die('Authorization code not found.');
}

try {
    require_once dirname(__DIR__) . '/vendor/autoload.php';
    
    $client = new Google_Client();
    $client->setApplicationName('Play is School');
    $client->setClientId(GOOGLE_CALENDAR_CLIENT_ID);
    $client->setClientSecret(GOOGLE_CALENDAR_CLIENT_SECRET);
    $client->setRedirectUri(GOOGLE_CALENDAR_REDIRECT_URI);
    $client->addScope(Google_Service_Calendar::CALENDAR);
    
    // Fetch access token using authorization code
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    
    if (isset($token['error'])) {
        throw new Exception('Error fetching access token: ' . $token['error']);
    }
    
    // Save token
    saveGoogleCalendarToken($token);
    
    // Redirect to success page
    header('Location: ' . GOOGLE_CALENDAR_REDIRECT_URI . '?status=success');
    exit;
} catch (Exception $e) {
    error_log('Google Calendar Authorization Error: ' . $e->getMessage());
    header('Location: ' . GOOGLE_CALENDAR_REDIRECT_URI . '?status=error&message=' . urlencode($e->getMessage()));
    exit;
}
