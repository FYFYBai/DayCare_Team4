<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

// Group manager dashboard routes under /manager
$app->group('/manager', function() use ($app) {
    // GET: Manager overview dashboard
    $app->get('/dashboard', function (Request $request, Response $response, $args) {
        // Aggregate key metrics
        $totalChildren = DB::queryFirstField("SELECT COUNT(*) FROM children WHERE isDeleted=0");
        $totalEducators = DB::queryFirstField("SELECT COUNT(*) FROM users WHERE role='educator' AND isDeleted=0");
        // Assuming you have an events table for participation data:
        $totalEvents = DB::queryFirstField("SELECT COUNT(*) FROM events");
        
        return $this->get(Twig::class)->render($response, 'manager_dashboard.html.twig', [
            'totalChildren' => $totalChildren,
            'totalEducators' => $totalEducators,
            'totalEvents' => $totalEvents
        ]);
    });
})->add($checkRoleMiddleware('manager'));
