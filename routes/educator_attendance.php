<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

// Group attendance routes under /educator/attendance
$app->group('/educator/attendance', function() use ($app) {
    
    // GET: Display the attendance form for today
    $app->get('', function (Request $request, Response $response, $args) {
        $educatorId = $_SESSION['user_id'];
        // Fetch all children assigned to this educator (assuming children table has educator_id)
        $children = DB::query("SELECT * FROM children WHERE educator_id = %i AND isDeleted=0", $educatorId);
        return $this->get(Twig::class)->render($response, 'educator_attendance_form.html.twig', [
            'children' => $children,
            'today' => date('Y-m-d')
        ]);
    });

    // POST: Process the attendance submission for today
    $app->post('', function (Request $request, Response $response, $args) {
        $data = $request->getParsedBody();
        $educatorId = $_SESSION['user_id'];
        // Assume the form sends an array named 'attendance' with keys as child IDs and values as status ("present" or "absent")
        $attendanceDate = $data['date'] ?? date('Y-m-d');

        // Iterate over the attendance entries and insert a record for each child
        foreach ($data['attendance'] as $childId => $status) {
            DB::insert('attendance', [
                'child_id' => $childId,
                'educator_id' => $educatorId,
                'date' => $attendanceDate,
                'status' => $status
            ]);
        }
        $response->getBody()->write("Attendance recorded successfully.");
        return $response;
    });
})->add($checkRoleMiddleware('educator'));
