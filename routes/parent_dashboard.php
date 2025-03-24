<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

// Parent dashboard route - restricted to parents
$app->get('/parent-dashboard', function (Request $request, Response $response, $args) {
    // Get user information
    $user = DB::queryFirstRow("SELECT * FROM users WHERE id = %d", $_SESSION['user_id']);
    
    // Get all children for this parent
    $children = DB::query("SELECT * FROM children WHERE parent_id = %d AND isDeleted = 0", $_SESSION['user_id']);
    
    // Get upcoming events (if you have an events table)
    $events = []; // Replace with actual DB query if you have events
    
    return $this->get(Twig::class)->render($response, 'parent-dashboard.html.twig', [
        'user' => $user,
        'children' => $children,
        'events' => $events
    ]);
})->add($checkRoleMiddleware('parent')); 