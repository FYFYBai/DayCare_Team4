<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

// Group manager assignments routes under /manager/assignments
$app->group('/manager/assignments', function() use ($app) {
    
    // GET: Display current educator-child assignments
    $app->get('', function (Request $request, Response $response, $args) {
        // Example query: Join children with users (educators) based on educator_id
        $assignments = DB::query("SELECT c.id AS child_id, c.name AS child_name, u.id AS educator_id, u.name AS educator_name 
                                   FROM children c 
                                   LEFT JOIN users u ON c.educator_id = u.id 
                                   WHERE c.isDeleted=0");
        return $this->get(Twig::class)->render($response, 'manager_assignments.html.twig', [
            'assignments' => $assignments
        ]);
    });
    
    // POST: Update the educator assignment for a child
    $app->post('', function (Request $request, Response $response, $args) {
        $data = $request->getParsedBody();
        $childId = (int)$data['child_id'];
        $newEducatorId = (int)$data['educator_id'];

        // Optional: Validate the new educator exists and is available
        DB::update('children', [
            'educator_id' => $newEducatorId
        ], "id=%i", $childId);

        $response->getBody()->write("Assignment updated successfully.");
        return $response;
    });
})->add($checkRoleMiddleware('manager'));
