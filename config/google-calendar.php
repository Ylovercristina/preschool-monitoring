<?php
/**
 * Google Calendar API Configuration
 * Preschool Monitoring System
 * 
 * Setup Instructions:
 * 1. Create a Google Cloud Project at https://console.cloud.google.com
 * 2. Enable Google Calendar API
 * 3. Create OAuth 2.0 Client ID (Desktop application)
 * 4. Download credentials JSON and update the path below
 */

// Google Calendar API Credentials
define('GOOGLE_CALENDAR_CLIENT_ID', getenv('GOOGLE_CALENDAR_CLIENT_ID') ?: 'YOUR_CLIENT_ID.apps.googleusercontent.com');
define('GOOGLE_CALENDAR_CLIENT_SECRET', getenv('GOOGLE_CALENDAR_CLIENT_SECRET') ?: 'YOUR_CLIENT_SECRET');
define('GOOGLE_CALENDAR_REDIRECT_URI', getenv('GOOGLE_CALENDAR_REDIRECT_URI') ?: 'http://localhost/collab/auth/google-callback.php');

// Calendar Configuration
define('GOOGLE_CALENDAR_ID', getenv('GOOGLE_CALENDAR_ID') ?: 'primary');
define('GOOGLE_CALENDAR_TIMEZONE', 'UTC');
define('GOOGLE_CALENDAR_MAX_RESULTS', 50);

// Storage for tokens (use database in production)
define('GOOGLE_CALENDAR_TOKEN_DIR', dirname(__DIR__) . '/config/tokens');

// Ensure token directory exists
if (!is_dir(GOOGLE_CALENDAR_TOKEN_DIR)) {
    @mkdir(GOOGLE_CALENDAR_TOKEN_DIR, 0755, true);
}

/**
 * Get OAuth 2.0 Client
 * @return Google_Client
 */
function getGoogleCalendarClient() {
    require_once dirname(__DIR__) . '/vendor/autoload.php';
    
    $client = new Google_Client();
    $client->setApplicationName('Play is School');
    $client->setClientId(GOOGLE_CALENDAR_CLIENT_ID);
    $client->setClientSecret(GOOGLE_CALENDAR_CLIENT_SECRET);
    $client->setRedirectUri(GOOGLE_CALENDAR_REDIRECT_URI);
    $client->addScope(Google_Service_Calendar::CALENDAR);
    $client->addScope(Google_Service_Calendar::CALENDAR_READONLY);
    
    // Load previously authorized token
    $tokenPath = GOOGLE_CALENDAR_TOKEN_DIR . '/calendar-token.json';
    if (file_exists($tokenPath)) {
        $accessToken = json_decode(file_get_contents($tokenPath), true);
        $client->setAccessToken($accessToken);
    }
    
    // Refresh token if expired
    if ($client->isAccessTokenExpired()) {
        if ($client->getRefreshToken()) {
            $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
            file_put_contents($tokenPath, json_encode($client->getAccessToken()));
        }
    }
    
    return $client;
}

/**
 * Get Google Calendar Service
 * @return Google_Service_Calendar|null
 */
function getGoogleCalendarService() {
    try {
        $client = getGoogleCalendarClient();
        return new Google_Service_Calendar($client);
    } catch (Exception $e) {
        error_log('Google Calendar Service Error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Save Google Calendar Token
 * @param array $token
 */
function saveGoogleCalendarToken($token) {
    $tokenPath = GOOGLE_CALENDAR_TOKEN_DIR . '/calendar-token.json';
    file_put_contents($tokenPath, json_encode($token));
}

/**
 * Get stored Google Calendar Token
 * @return array|null
 */
function getGoogleCalendarToken() {
    $tokenPath = GOOGLE_CALENDAR_TOKEN_DIR . '/calendar-token.json';
    if (file_exists($tokenPath)) {
        return json_decode(file_get_contents($tokenPath), true);
    }
    return null;
}
