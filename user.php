<?php
use Psr\Http\Message\ResponseInterface as Response; //  interfaces for HTTP messages
use Psr\Http\Message\ServerRequestInterface as Request; //  interfaces for HTTP messages
use Slim\Factory\AppFactory; // used to create the Slim application instance
use DI\Container; // Container comes from PHP-DI, a dependency injection container
use Slim\Views\Twig; // Twig and TwigMiddleware are used to integrate Twig (a templating engine) into Slim app
use Slim\Views\TwigMiddleware;

require_once 'init.php';

// URL handlers 

$app->get('/login', function ($request, $response, $args) use ($container) {
    $twig = $container->get(Twig::class);
    return $twig->render($response, 'addPerson.html.twig');
});


$app->get('/logout', function ($request, $response, $args) use ($container) {
    $twig = $container->get(Twig::class);
    return $twig->render($response, 'addPerson.html.twig');
});