# Google Calendar Integration Setup Guide

## Overview
The "Play is School" system now integrates with Google Calendar, allowing you to sync school events across your organization.

## Requirements
- PHP 7.4+
- Google Cloud Account
- Composer (for Google API client library)

## Setup Steps

### 1. Install Google API Client
Run the following command in your project root:
```bash
composer require google/apiclient:^2.0
```

### 2. Create Google Cloud Project
1. Go to [Google Cloud Console](https://console.cloud.google.com)
2. Create a new project named "Play is School"
3. Enable the following APIs:
   - Google Calendar API

### 3. Create OAuth 2.0 Credentials
1. In Google Cloud Console, go to "Credentials"
2. Click "Create Credentials" → "OAuth 2.0 Client ID"
3. Choose "Desktop application" as the application type
4. Download the credentials JSON file
5. Note down your:
   - Client ID
   - Client Secret

### 4. Configure the Application
Edit `config/google-calendar.php` and update:
```php
define('GOOGLE_CALENDAR_CLIENT_ID', 'YOUR_CLIENT_ID.apps.googleusercontent.com');
define('GOOGLE_CALENDAR_CLIENT_SECRET', 'YOUR_CLIENT_SECRET');
define('GOOGLE_CALENDAR_REDIRECT_URI', 'http://localhost/collab/auth/google-callback.php');
define('GOOGLE_CALENDAR_ID', 'primary'); // or your calendar ID
```

Alternatively, use environment variables:
```bash
GOOGLE_CALENDAR_CLIENT_ID=your_client_id
GOOGLE_CALENDAR_CLIENT_SECRET=your_client_secret
GOOGLE_CALENDAR_REDIRECT_URI=http://localhost/collab/auth/google-callback.php
```

### 5. Create Token Directory
Ensure the token directory exists:
```bash
mkdir -p config/tokens
chmod 755 config/tokens
```

### 6. Authorize in Admin Panel
1. Log in as Administrator
2. Go to "Google Calendar Setup" page
3. Click "Connect to Google Calendar"
4. Authorize the application to access your calendar

## Features

### Automatic Sync
- Events created in your local database are automatically synced to Google Calendar
- Google Calendar events appear in your school calendar

### Event Management
- Create events through the admin panel
- Events sync to Google Calendar
- Delete events and they're removed from Google Calendar

### Parent View
- Parents can see all school events
- Events from both local database and Google Calendar are displayed
- Simple, clean event listing

## Files Added

- `config/google-calendar.php` - Configuration file
- `includes/google-calendar-helper.php` - Utility functions
- `auth/google-callback.php` - OAuth callback handler
- `admin/google-calendar-setup.php` - Admin setup page

## Testing

1. Create an event in the admin panel
2. Check your Google Calendar to see if it appears
3. Create an event in Google Calendar
4. Check if it appears in the school calendar

## Troubleshooting

### "Unable to open" error
- Check that `config/tokens` directory is writable
- Verify Client ID and Client Secret in `config/google-calendar.php`

### Events not syncing
- Click "Connect to Google Calendar" in the admin setup page
- Ensure the configured calendar ID is correct
- Check that the Google Calendar API is enabled in Google Cloud Console

### Token expiration
- Tokens are automatically refreshed when they expire
- If issues persist, disconnect and reconnect

## Security Notes

- Store Client Secret securely - never commit to version control
- Use environment variables for sensitive configuration
- Tokens are stored in `config/tokens/` - ensure this directory is not web-accessible
- Token file contains sensitive information - protect it with proper permissions

## API Reference

### Get Events
```php
$events = getGoogleCalendarEvents($startDate, $endDate);
```

### Create Event
```php
$event = createGoogleCalendarEvent([
    'summary' => 'School Event',
    'description' => 'Event details',
    'start_date' => '2024-01-15',
    'start_time' => '09:00:00',
    'end_date' => '2024-01-15',
    'end_time' => '10:00:00',
    'location' => 'School',
]);
```

### Update Event
```php
updateGoogleCalendarEvent($eventId, $eventData);
```

### Delete Event
```php
deleteGoogleCalendarEvent($eventId);
```

### Sync Events
```php
$result = syncEventsToGoogleCalendar($events);
// Returns: ['created' => 0, 'updated' => 0, 'errors' => []]
```

## Support
For issues or questions, refer to [Google Calendar API Documentation](https://developers.google.com/calendar/api/guides/overview)
