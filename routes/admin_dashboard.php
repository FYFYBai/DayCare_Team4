<?php
// routes/admin_dashboard.php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\Twig;

/**
 * ADMIN DASHBOARD ROUTE (ADMIN ONLY)
 */
$app->group('/admin', function (RouteCollectorProxy $group) {

    // GET /admin/dashboard -> Admin landing page
    $group->get('/dashboard', function (Request $request, Response $response, array $args) {
        // Render a Twig template for the admin dashboard
        return $this->get(Twig::class)->render($response, 'admin_dashboard.html.twig', []);
    });
})->add($checkRoleMiddleware('admin')); // Only admins can access
