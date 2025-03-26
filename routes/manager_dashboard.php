<?php
// routes/manager_dashboard.php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\Twig;

$app->group('/manager', function (RouteCollectorProxy $group) {

    // GET /manager/dashboard -> Manager's overview page
    $group->get('/dashboard', function (Request $request, Response $response, array $args) {
        
        // 1) Retrieve key metrics from the database
        
        // Total number of children
        $totalChildren = DB::queryFirstField("SELECT COUNT(*) FROM children WHERE isDeleted = 0");

        // Total number of educators
        $totalEducators = DB::queryFirstField("SELECT COUNT(*) FROM users WHERE role = 'educator' AND isDeleted = 0");

        // Event participation (placeholder example）
        $eventParticipation = 25; // placeholder or DB-based count

        // 2) Render the manager dashboard template
        return $this->get(Twig::class)->render($response, 'manager_dashboard.html.twig', [
            'totalChildren'      => $totalChildren,
            'totalEducators'     => $totalEducators,
            'eventParticipation' => $eventParticipation
            // you can pass more data as needed
        ]);
    });

})->add($checkRoleMiddleware('manager'));
