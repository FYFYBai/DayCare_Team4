<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

// Educator dashboard route – restricted to educators
$app->get('/educator-dashboard', function (Request $request, Response $response, $args) {
    // Retrieve educator info and child profiles (adjust query as needed)
    $user = DB::queryFirstRow("SELECT * FROM users WHERE id = %d", $_SESSION['user_id']);
    $children = DB::query("SELECT * FROM children WHERE educator_id = %d", $_SESSION['user_id']);
    
    return $this->get(Twig::class)->render($response, 'educator-dashboard.html.twig', [
        'user' => $user,
        'children' => $children
    ]);
})->add($checkRoleMiddleware('educator'));
