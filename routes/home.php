<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

// Home page route: renders home.html.twig
$app->get('/', function (Request $request, Response $response, $args) {
    return $this->get(Twig::class)->render($response, 'home.html.twig');
});
