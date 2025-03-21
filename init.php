<?php
// set up a container, register Twig, create the app from the container, add middleware, define routes, and finally run the app.
session_start();

// Autoload all dependencies from Composer's vendor directory
require_once __DIR__ . '/vendor/autoload.php'; // include_once vs require_once - always use require_once because if the file is included more than once, it will only be included once; if the file is not there, it will throw an error. On the other hand, with include_once, if the file is not included, it will not be included without throwing any error.

// Namespace imports or import of the necessary classes and interfaces
//use Psr\Http\Message\ResponseInterface as Response; //  interfaces for HTTP messages
//use Psr\Http\Message\ServerRequestInterface as Request; //  interfaces for HTTP messages
use Slim\Factory\AppFactory; // used to create the Slim application instance
use DI\Container; // Container comes from PHP-DI, a dependency injection container
use Slim\Views\Twig; // Twig and TwigMiddleware are used to integrate Twig (a templating engine) into Slim app
use Slim\Views\TwigMiddleware;
use Ramsey\Uuid\Uuid;

use Dotenv\Dotenv;

// Load environment variables from the .env file in the project root
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Logging setup //  the library won't actually create a log file until you run your first log statement
use Monolog\Logger; // Monolog is a logging library for PHP
use Monolog\Handler\StreamHandler; // Monolog handler for writing logs to a file

//Create a log channel
$log = new Logger('my_logger'); // A new Monolog logger is created // The logger is named "my_logger"

// Add a log handler to the logger
 $log->pushHandler(new StreamHandler('applogs/everything.log', Logger::DEBUG)); // The log handler is added to the logger //  logs messages at the WARNING level and above
 $log->pushHandler(new StreamHandler('applogs/errors.log', Logger::ERROR)); // The log handler is added to the logger //  logs messages at the ERROR level and above

 $log->pushProcessor(function ($record){
    $record['extra']['ip'] = $_SERVER['REMOTE_ADDR']; // The IP address of the client is added to the log record
    return $record;
});
 
/* // Error handling setup //  the library won't actually handle errors until you run your first error statement
 $error_handler = function ($request, $response, $exception) use ($log) {
    $log->error("Error: ". $exception->getMessage());
    http_response_code(500);
    $twig = AppFactory::getContainer()['view']->getEnvironment();
    die($twig->render('error_internal.html.twig')); // The error template is rendered and sent to the client
}; */

//  using MeekroDB for database interactions instead of traditional PDO
// MeekroDB replace PDO-style calls with MeekroDB's simple static method style
if ($_SERVER['SERVER_NAME'] == 'daycaresystem.org') {
    // Database connection setup //  the library won't actually establish a database connection until you run your first query
        DB::$dbName = 'cp5114_team4';
        DB::$user = 'cp5114_team4';  
        DB::$password = '=w%S0M.pGNq_';
        DB::$host = 'fsd13.ca'; 
  } else { // hosted on external server
        DB::$dbName = 'cp5114_team4';
        DB::$user = 'cp5114_team4';  
        DB::$password = '=w%S0M.pGNq_'; 
    }

/* $db_error_handler = function ($params) {
    global $log, $container;
    $log->error("Database error: ". $params['error']);
    if (isset($params['query'])) {
        $log->error("Query: ". $params['query']);
    }
    http_response_code(500);
    $twig = $container['view']->getEnvironment();
    die($twig->render('error_internal.html.twig')); // The error template is rendered and sent to the client
};
 */
// Create Container
$container = new Container(); // A new dependency injection container (service container or central registry) is created to hold instances of classes or services that this application needs
AppFactory::setContainer($container); // The container is then set for the Slim AppFactory, allowing Slim to resolve dependencies from it

// Set view in Container
$container->set(Twig::class, function() { // A new Twig instance is created and set as the view for the Slim application //  registers Twig as a service in the container 
    // The term "service" refers to any object or class that is managed by a service container (also known as a dependency injection container)
    return Twig::create(__DIR__ . '/templates', ['cache' => __DIR__ . '/tmplcache', 'debug' => true]); //  tells the container to instantiate Twig using templates from the templates folder
});

// In Slim, the app is the main application object that handles the routing and execution of your application. It is the central point where you define routes, middleware, and configure your services. The app is created using:
// Create App from container
$app = AppFactory::createFromContainer($container); // This creates the Slim application instance using the container you set up // The app now has access to the container's dependencies

// Add Twig-View Middleware
$app->add(TwigMiddleware::create($app, $container->get(Twig::class))); // This middleware adds the Twig view engine to the Slim application, allowing it to render templates

// Add other middleware (for routing and error handling)
$app->addRoutingMiddleware(); // Handles matching incoming requests to defined routes
$app->addErrorMiddleware(true, true, true); //Provides error handling. The three boolean parameters typically control display of error details, logging, etc. In production, you’d disable error details

