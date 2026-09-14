<?php
/**
 * Google Calendar Utility Functions
 * Preschool Monitoring System
 */

require_once __DIR__ . '/google-calendar.php';

/**
 * Fetch events from Google Calendar for a date range
 * @param DateTime $startDate
 * @param DateTime $endDate
 * @return array
 */
function getGoogleCalendarEvents($startDate = null, $endDate = null) {
    $service = getGoogleCalendarService();
    if (!$service) return [];
    
    try {
        // Set date range (default: next 30 days)
        if (!$startDate) {
            $startDate = new DateTime();
        }
        if (!$endDate) {
            $endDate = (new DateTime())->add(new DateInterval('P30D'));
        }
        
        $optParams = [
            'maxResults' => GOOGLE_CALENDAR_MAX_RESULTS,
            'orderBy' => 'startTime',
            'singleEvents' => true,
            'timeMin' => $startDate->format(DateTime::RFC3339),
            'timeMax' => $endDate->format(DateTime::RFC3339),
            'timeZone' => GOOGLE_CALENDAR_TIMEZONE,
        ];
        
        $results = $service->events->listEvents(GOOGLE_CALENDAR_ID, $optParams);
        return $results->getItems() ?: [];
    } catch (Exception $e) {
        error_log('Error fetching Google Calendar events: ' . $e->getMessage());
        return [];
    }
}

/**
 * Create event in Google Calendar
 * @param array $eventData
 * @return object|null
 */
function createGoogleCalendarEvent($eventData) {
    $service = getGoogleCalendarService();
    if (!$service) return null;
    
    try {
        $event = new Google_Service_Calendar_Event();
        $event->setSummary($eventData['summary'] ?? 'School Event');
        $event->setDescription($eventData['description'] ?? '');
        
        // Set date/time
        if (isset($eventData['start_date'])) {
            $start = new Google_Service_Calendar_EventDateTime();
            $startDateTime = $eventData['start_time'] 
                ? $eventData['start_date'] . 'T' . $eventData['start_time'] 
                : $eventData['start_date'];
            $start->setDateTime(new DateTime($startDateTime));
            $start->setTimeZone(GOOGLE_CALENDAR_TIMEZONE);
            $event->setStart($start);
        }
        
        if (isset($eventData['end_date'])) {
            $end = new Google_Service_Calendar_EventDateTime();
            $endDateTime = $eventData['end_time']
                ? $eventData['end_date'] . 'T' . $eventData['end_time']
                : $eventData['end_date'];
            $end->setDateTime(new DateTime($endDateTime));
            $end->setTimeZone(GOOGLE_CALENDAR_TIMEZONE);
            $event->setEnd($end);
        }
        
        if (isset($eventData['location'])) {
            $event->setLocation($eventData['location']);
        }
        
        $createdEvent = $service->events->insert(GOOGLE_CALENDAR_ID, $event);
        return $createdEvent;
    } catch (Exception $e) {
        error_log('Error creating Google Calendar event: ' . $e->getMessage());
        return null;
    }
}

/**
 * Update event in Google Calendar
 * @param string $eventId
 * @param array $eventData
 * @return object|null
 */
function updateGoogleCalendarEvent($eventId, $eventData) {
    $service = getGoogleCalendarService();
    if (!$service) return null;
    
    try {
        $event = $service->events->get(GOOGLE_CALENDAR_ID, $eventId);
        
        if (isset($eventData['summary'])) {
            $event->setSummary($eventData['summary']);
        }
        
        if (isset($eventData['description'])) {
            $event->setDescription($eventData['description']);
        }
        
        if (isset($eventData['location'])) {
            $event->setLocation($eventData['location']);
        }
        
        $updatedEvent = $service->events->update(GOOGLE_CALENDAR_ID, $eventId, $event);
        return $updatedEvent;
    } catch (Exception $e) {
        error_log('Error updating Google Calendar event: ' . $e->getMessage());
        return null;
    }
}

/**
 * Delete event from Google Calendar
 * @param string $eventId
 * @return bool
 */
function deleteGoogleCalendarEvent($eventId) {
    $service = getGoogleCalendarService();
    if (!$service) return false;
    
    try {
        $service->events->delete(GOOGLE_CALENDAR_ID, $eventId);
        return true;
    } catch (Exception $e) {
        error_log('Error deleting Google Calendar event: ' . $e->getMessage());
        return false;
    }
}

/**
 * Sync local events to Google Calendar
 * @param array $events Local events from database
 * @return array Sync results
 */
function syncEventsToGoogleCalendar($events) {
    $results = ['created' => 0, 'updated' => 0, 'errors' => []];
    
    foreach ($events as $event) {
        try {
            $eventData = [
                'summary' => $event['title'],
                'description' => $event['description'] ?? '',
                'start_date' => $event['event_date'],
                'start_time' => $event['start_time'] ?? '09:00:00',
                'end_date' => $event['event_date'],
                'end_time' => $event['end_time'] ?? '10:00:00',
                'location' => $event['location'] ?? 'School',
            ];
            
            if (isset($event['google_calendar_id']) && $event['google_calendar_id']) {
                // Update existing event
                $result = updateGoogleCalendarEvent($event['google_calendar_id'], $eventData);
                if ($result) $results['updated']++;
            } else {
                // Create new event
                $result = createGoogleCalendarEvent($eventData);
                if ($result) {
                    $results['created']++;
                    // Store Google Calendar ID in database
                    storeGoogleEventId($event['id'], $result->getId());
                }
            }
        } catch (Exception $e) {
            $results['errors'][] = 'Error syncing event ' . $event['title'] . ': ' . $e->getMessage();
        }
    }
    
    return $results;
}

/**
 * Store Google Calendar Event ID in database
 * @param int $localEventId
 * @param string $googleEventId
 */
function storeGoogleEventId($localEventId, $googleEventId) {
    try {
        $db = getDB();
        $db->prepare("UPDATE events SET google_calendar_id = ? WHERE id = ?")
            ->execute([$googleEventId, $localEventId]);
    } catch (Exception $e) {
        error_log('Error storing Google Calendar ID: ' . $e->getMessage());
    }
}

/**
 * Format Google Calendar Event for display
 * @param Google_Service_Calendar_Event $event
 * @return array
 */
function formatGoogleEvent($event) {
    $start = $event->getStart();
    $end = $event->getEnd();
    
    return [
        'id' => $event->getId(),
        'title' => $event->getSummary(),
        'description' => $event->getDescription(),
        'location' => $event->getLocation(),
        'start_date' => $start ? substr($start->getDateTime(), 0, 10) : '',
        'start_time' => $start ? substr($start->getDateTime(), 11, 5) : '',
        'end_date' => $end ? substr($end->getDateTime(), 0, 10) : '',
        'end_time' => $end ? substr($end->getDateTime(), 11, 5) : '',
        'event_type' => 'School Event',
        'google_event_id' => $event->getId(),
    ];
}
