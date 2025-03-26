<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\Twig;

// Declare the global container so it's available here.
global $container;

$app->group('/educator/attendance', function (RouteCollectorProxy $group) use ($container) {
    // GET: Display the attendance form for today
    $group->get('', function (Request $request, Response $response, array $args) use ($container) {
        $educatorId = $_SESSION['user_id'];
        $children = DB::query("SELECT * FROM children WHERE educator_id = %i AND isDeleted = 0", $educatorId);
        $today = date('Y-m-d');
        // Fetch existing registrations for today for these children
        $registrations = DB::query("SELECT * FROM registrations 
                                     WHERE registration_date = %s AND child_id IN 
                                       (SELECT id FROM children WHERE educator_id = %i)", $today, $educatorId);
        return $container->get(Twig::class)->render($response, 'educator_attendance_form.html.twig', [
            'children' => $children,
            'today'    => $today,
            'registrations' => $registrations
        ]);
    });
    
    
    // POST: Process the attendance submission for today
    $group->post('', function (Request $request, Response $response, array $args) use ($container) {
        $data = $request->getParsedBody();
        $registration_date = $data['date'] ?? date('Y-m-d');

        // Check if the 'attendance' array exists
        if (!isset($data['attendance']) || !is_array($data['attendance'])) {
            $flash = $container->get(\Slim\Flash\Messages::class);
            $flash->addMessage('error', "No attendance data provided.");
            return $response->withHeader('Location', '/educator/attendance')->withStatus(302);
        }

        // Iterate over the attendance entries and insert a record for each child
        foreach ($data['attendance'] as $childId => $status) {
            if (!in_array($status, ['present', 'absent'])) {
                $flash = $container->get(\Slim\Flash\Messages::class);
                $flash->addMessage('error', "Invalid status for child ID {$childId}.");
                return $response->withHeader('Location', '/educator/attendance')->withStatus(302);
            }
            // Insert into registrations table without educator_id, since the column doesn't exist
            DB::insert('registrations', [
                'child_id'          => $childId,
                'registration_date' => $registration_date,
                'status'            => $status
            ]);
        }

        $flash = $container->get(\Slim\Flash\Messages::class);
        $flash->addMessage('success', "Attendance recorded successfully.");
        return $response->withHeader('Location', '/educator-dashboard')->withStatus(302);
    });
})->add($checkRoleMiddleware('educator'));
