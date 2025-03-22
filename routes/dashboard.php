<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

// Dashboard route for logged-in users
$app->get('/dashboard', function (Request $request, Response $response, $args) {
    return $this->get(Twig::class)->render($response, 'dashboard.html.twig', [
        'is_admin' => $_SESSION['is_admin'] ?? false
    ]);
});
