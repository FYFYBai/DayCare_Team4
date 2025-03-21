<?php

use Psr\Http\Message\ResponseInterface as Response; //  interfaces for HTTP messages
use Psr\Http\Message\ServerRequestInterface as Request; //  interfaces for HTTP messages
use Slim\Factory\AppFactory; // used to create the Slim application instance
use DI\Container; // Container comes from PHP-DI, a dependency injection container
use Slim\Views\Twig; // Twig and TwigMiddleware are used to integrate Twig (a templating engine) into Slim app
use Slim\Views\TwigMiddleware;

// faildb url
$app->get('/faildb', function ($request, $response, $args) {
    DB::query("SELECT *** FROM wrong"); 
    return $response->getBody()->write("This should never be displayed");
}); 

// simple route returns a text greeting
$app->get('/hello/{name}', function (Request $request, Response $response, array $args) {
    $name = $args['name'];
    $response->getBody()->write("Hello, $name");
    return $response;
});

// route renders a Twig template with dynamic values
$app->get('/hello/{name}/{age}', function (Request $request, Response $response, array $args) use ($container) {
    $twig = $container->get(Twig::class); // This is a closure in php commonly used in middleware or route definitions within the Slim Framework. It uses the use ($container) clause to gain access to the DI container to retrieve the Twig instance
    // The $container is passed as a dependency, allowing you to get services registered in the container.
    $name = $args['name'];
    $age = $args['age'];
    // $response->getBody()->write("Hello, $name, you are $age y/o");
    // return $response;
    return $twig->render($response, 'hello.html.twig', ['nameVal' => $name, 'ageVal' => $age]); // It retrieves the Twig instance from the container and renders the hello.html.twig template, passing the name and age as variables
});

// STATE 1: first display of the form
$app->get('/addPerson', function ($request, $response, $args) use ($container) {
    $twig = $container->get(Twig::class);
    return $twig->render($response, 'addperson.html.twig');
});

// STATE 2&3: receiving a form submission and adding a new person to the database
$app->post('/addPerson', function ($request, $response, $args) use ($container) {
    $twig = $container->get(Twig::class);
    // extract values submitted
    $data = $request->getParsedBody();
    $name = $request->getParsedBody()['name'];
    $age = $request->getParsedBody()['age'];

    // Validate input data
    $errorList = [];

    if (empty($name)) {
        $errorList[] = 'Name is required';
    } else if (strlen($name) < 2 || strlen($name) > 100) {
        $errorList[] = 'Name must be between 2-100 characters';
    }
    if (!is_numeric($age) || $age < 0 || $age > 150) {
        $errorList[] = 'Age must be a positive integer between 0 and 150';
    }
    
    // If there are any errors, re-display the form with error messages
    if ($errorList) { // STATE 2: re-display the form with error messages
        $valuesList = ['name' => $name, 'age' => $age];
        return $twig->render($response, 'addperson.html.twig', ['v' => $valuesList, 'errorList' => $errorList]);
    } else { // STATE 3: successfully added a new person to the database
        DB::insert('friends', ['name' => $name, 'age' => $age]); //  The MeekroDB library will run all needed safety checks, such as escaping strings and making sure integers are really integers
        return $twig->render($response, 'addperson_success.html.twig', ['name' => $name, 'age' => $age]);
    }
});