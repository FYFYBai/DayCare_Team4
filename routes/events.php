<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use Slim\Routing\RouteContext;

// I based and got inspired by this video: https://www.youtube.com/watch?v=hyVzloriEfo&list=PLkqwj9vc20pUyz2hLxtdUOc0cISEagtmm
// Helper function to get calendar data
function getCalendarData($month, $year) {
    // First day of the month
    $firstDay = mktime(0, 0, 0, $month, 1, $year);
    $daysInMonth = date('t', $firstDay);
    $startDay = date('w', $firstDay); // Day of week (0 for Sunday)
    
    // Get today's date
    $today = date('Y-m-d');
    
    // Get events for this month
    $startDate = date('Y-m-d', $firstDay);
    $endDate = date('Y-m-d', mktime(0, 0, 0, $month + 1, 0, $year));
    
    $events = DB::query("SELECT * FROM events WHERE 
                         DATE(start_date) BETWEEN %s AND %s 
                         AND isDeleted = 0", $startDate, $endDate);
    
    // Organize events by date
    $eventsByDate = [];
    foreach ($events as $event) {
        $date = date('Y-m-d', strtotime($event['start_date']));
        if (!isset($eventsByDate[$date])) {
            $eventsByDate[$date] = [];
        }
        $event['start_time'] = date('g:i A', strtotime($event['start_date']));
        $event['end_time'] = date('g:i A', strtotime($event['end_date']));
        $eventsByDate[$date][] = $event;
    }
    
    // Build calendar array
    $calendar = [];
    $day = 1;
    
    // Build calendar weeks
    while ($day <= $daysInMonth) {
        $week = [];
        // Fill in days from previous month
        if ($day == 1) {
            for ($i = 0; $i < $startDay; $i++) {
                $prevMonthDay = date('j', strtotime('-' . ($startDay - $i) . ' days', $firstDay));
                $prevMonthDate = date('Y-m-d', strtotime('-' . ($startDay - $i) . ' days', $firstDay));
                $week[] = [
                    'day' => $prevMonthDay,
                    'date' => $prevMonthDate,
                    'isOtherMonth' => true,
                    'isToday' => $prevMonthDate == $today,
                    'events' => $eventsByDate[$prevMonthDate] ?? []
                ];
            }
        }
        
        // Fill in days for current month
        for ($i = ($day == 1 ? $startDay : 0); $i < 7 && $day <= $daysInMonth; $i++) {
            $date = date('Y-m-d', mktime(0, 0, 0, $month, $day, $year));
            $week[] = [
                'day' => $day,
                'date' => $date,
                'isOtherMonth' => false,
                'isToday' => $date == $today,
                'events' => $eventsByDate[$date] ?? []
            ];
            $day++;
        }
        
        // Fill in days from next month
        if (count($week) < 7) {
            $daysNeeded = 7 - count($week);
            for ($i = 1; $i <= $daysNeeded; $i++) {
                $nextMonthDate = date('Y-m-d', mktime(0, 0, 0, $month + 1, $i, $year));
                $week[] = [
                    'day' => $i,
                    'date' => $nextMonthDate,
                    'isOtherMonth' => true,
                    'isToday' => $nextMonthDate == $today,
                    'events' => $eventsByDate[$nextMonthDate] ?? []
                ];
            }
        }
        
        $calendar[] = $week;
    }
    
    return $calendar;
}

// Helper to get month navigation
function getMonthNavigation($month, $year) {
    // Previous month
    if ($month == 1) {
        $prevMonth = 12;
        $prevYear = $year - 1;
    } else {
        $prevMonth = $month - 1;
        $prevYear = $year;
    }
    
    // Next month
    if ($month == 12) {
        $nextMonth = 1;
        $nextYear = $year + 1;
    } else {
        $nextMonth = $month + 1;
        $nextYear = $year;
    }
    
    return [
        'prevMonth' => $prevMonth,
        'prevYear' => $prevYear,
        'nextMonth' => $nextMonth,
        'nextYear' => $nextYear,
        'monthName' => date('F', mktime(0, 0, 0, $month, 1, $year))
    ];
}

// Public calendar view for all users (requires login)
$app->get('/events/calendar', function (Request $request, Response $response) {
    $userId = $_SESSION['user_id'] ?? null;
    
    if (!$userId) {
        return $response->withHeader('Location', '/login')->withStatus(302);
    }
    
    $user = DB::queryFirstRow("SELECT * FROM users WHERE id = %i", $userId);
    $flash = $this->get(\Slim\Flash\Messages::class);
    
    // Get upcoming events
    $today = date('Y-m-d H:i:s');
    $upcomingEvents = DB::query("SELECT * FROM events 
                                WHERE start_date >= %s 
                                AND isDeleted = 0 
                                ORDER BY start_date ASC 
                                LIMIT 10", $today);
    
    return $this->get(Twig::class)->render($response, 'events_calendar.html.twig', [
        'user' => $user,
        'role' => $_SESSION['role'] ?? '',
        'upcomingEvents' => $upcomingEvents,
        'messages' => $flash->getMessages()
    ]);
});

// Event management for educators and admins
$app->get('/events/manage', function (Request $request, Response $response) {
    $userId = $_SESSION['user_id'] ?? null;
    $userRole = $_SESSION['role'] ?? null;
    
    // Check if the user is logged in and has the required role
    if (!$userId || !in_array($userRole, ['educator', 'admin'])) {
        return $response->withHeader('Location', '/login')->withStatus(302);
    }
    
    $flash = $this->get(\Slim\Flash\Messages::class);
    $user = DB::queryFirstRow("SELECT * FROM users WHERE id = %i", $userId);
    
    // Get events created by this educator
    $educatorEvents = DB::query("SELECT * FROM events WHERE created_by = %i AND isDeleted = 0 ORDER BY start_date ASC", $userId);
    
    return $this->get(Twig::class)->render($response, 'events_manage.html.twig', [
        'user' => $user,
        'role' => $userRole,
        'educatorEvents' => $educatorEvents,
        'messages' => $flash->getMessages()
    ]);
});

// Create a new event (POST)
$app->post('/events/create', function (Request $request, Response $response) {
    $userId = $_SESSION['user_id'] ?? null;
    $userRole = $_SESSION['role'] ?? null;
    
    if (!$userId || !in_array($userRole, ['educator', 'admin'])) {
        return $response->withHeader('Location', '/login')->withStatus(302);
    }
    
    $flash = $this->get(\Slim\Flash\Messages::class);
    $data = $request->getParsedBody();
    
    try {
        // Validate input
        if (empty($data['title']) || empty($data['start_date']) || empty($data['end_date'])) {
            throw new Exception("Title, start date, and end date are required");
        }
        
        // Ensure start date is before end date
        $startDate = new DateTime($data['start_date']);
        $endDate = new DateTime($data['end_date']);
        
        if ($startDate > $endDate) {
            throw new Exception("Start date must be before end date");
        }
        
        // Create event
        DB::insert('events', [
            'title' => $data['title'],
            'description' => $data['description'] ?? '',
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'created_by' => $userId,
            'isDeleted' => 0
        ]);
        
        $flash->addMessage('success', 'Event created successfully');
    } catch (Exception $e) {
        $flash->addMessage('error', $e->getMessage());
    }
    
    return $response->withHeader('Location', '/events/manage')->withStatus(302);
});

// Edit event GET route
$app->get('/events/{id}/edit', function (Request $request, Response $response, array $args) {
    $userId = $_SESSION['user_id'] ?? null;
    $userRole = $_SESSION['role'] ?? null;
    
    if (!$userId || !in_array($userRole, ['educator', 'admin'])) {
        return $response->withHeader('Location', '/login')->withStatus(302);
    }
    
    $eventId = (int)$args['id'];
    $event = DB::queryFirstRow("SELECT * FROM events WHERE id = %i AND isDeleted = 0", $eventId);
    
    if (!$event) {
        return $response->withHeader('Location', '/events/manage?error=not_found')->withStatus(302);
    }
    
    // If not admin, check if user is the creator
    if ($userRole !== 'admin' && $event['created_by'] != $userId) {
        return $response->withHeader('Location', '/events/manage?error=not_authorized')->withStatus(302);
    }
    
    $flash = $this->get(\Slim\Flash\Messages::class);
    $user = DB::queryFirstRow("SELECT * FROM users WHERE id = %i", $userId);
    
    return $this->get(Twig::class)->render($response, 'events_edit.html.twig', [
        'user' => $user,
        'role' => $userRole,
        'event' => $event,
        'messages' => $flash->getMessages()
    ]);
});

// Update event
$app->post('/events/{id}/update', function (Request $request, Response $response, array $args) {
    $userId = $_SESSION['user_id'] ?? null;
    $userRole = $_SESSION['role'] ?? null;
    
    if (!$userId || !in_array($userRole, ['educator', 'admin'])) {
        return $response->withHeader('Location', '/login')->withStatus(302);
    }
    
    $eventId = (int)$args['id'];
    $event = DB::queryFirstRow("SELECT * FROM events WHERE id = %i AND isDeleted = 0", $eventId);
    
    if (!$event) {
        return $response->withHeader('Location', '/events/manage?error=not_found')->withStatus(302);
    }
    
    // If not admin, check if user is the creator
    if ($userRole !== 'admin' && $event['created_by'] != $userId) {
        return $response->withHeader('Location', '/events/manage?error=not_authorized')->withStatus(302);
    }
    
    $flash = $this->get(\Slim\Flash\Messages::class);
    $data = $request->getParsedBody();
    
    try {
        // Validate input
        if (empty($data['title']) || empty($data['start_date']) || empty($data['end_date'])) {
            throw new Exception("Title, start date, and end date are required");
        }
        
        // Ensure start date is before end date
        $startDate = new DateTime($data['start_date']);
        $endDate = new DateTime($data['end_date']);
        
        if ($startDate > $endDate) {
            throw new Exception("Start date must be before end date");
        }
        
        // Update event
        DB::update('events', [
            'title' => $data['title'],
            'description' => $data['description'] ?? '',
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
        ], "id = %i", $eventId);
        
        $flash->addMessage('success', 'Event updated successfully');
    } catch (Exception $e) {
        $flash->addMessage('error', $e->getMessage());
    }
    
    return $response->withHeader('Location', '/events/manage')->withStatus(302);
});

// Delete an event (POST)
$app->post('/events/delete', function (Request $request, Response $response) {
    $userId = $_SESSION['user_id'] ?? null;
    $userRole = $_SESSION['role'] ?? null;
    
    if (!$userId || !in_array($userRole, ['educator', 'admin'])) {
        return $response->withHeader('Location', '/login')->withStatus(302);
    }
    
    $flash = $this->get(\Slim\Flash\Messages::class);
    $data = $request->getParsedBody();
    $eventId = (int)($data['event_id'] ?? 0);
    
    try {
        // Ensure the event exists
        $event = DB::queryFirstRow("SELECT * FROM events WHERE id = %i", $eventId);
        
        if (!$event) {
            throw new Exception("Event not found");
        }
        
        // If not admin, check if user is the creator
        if ($userRole !== 'admin' && $event['created_by'] != $userId) {
            throw new Exception("You don't have permission to delete this event");
        }
        
        // Soft delete (update isDeleted flag)
        DB::update('events', ['isDeleted' => 1], "id = %i", $eventId);
        
        $flash->addMessage('success', 'Event deleted successfully');
    } catch (Exception $e) {
        $flash->addMessage('error', $e->getMessage());
    }
    
    return $response->withHeader('Location', '/events/manage')->withStatus(302);
});

// JSON API for fetching events (for FullCalendar)
$app->get('/events/fetch', function (Request $request, Response $response) {
    $events = DB::query(
        "SELECT * FROM events 
         WHERE isDeleted = 0 
         ORDER BY start_date"
    );
    
    $formattedEvents = array_map(function($event) {
        return [
            'id' => $event['id'],
            'title' => $event['title'],
            'start' => $event['start_date'],
            'end' => $event['end_date'],
            'description' => $event['description'] ?? ''
        ];
    }, $events);
    
    $responseData = json_encode($formattedEvents);
    $response->getBody()->write($responseData);
    return $response->withHeader('Content-Type', 'application/json');
}); 