<?php
session_start(); // Start PHP session

// Autoload dependencies via Composer
require_once __DIR__ . '/vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use Dotenv\Dotenv;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Load environment variables from the .env file in the project root
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Setup logging using Monolog
$log = new Logger('my_logger');
$log->pushHandler(new StreamHandler(__DIR__ . '/applogs/everything.log', Logger::DEBUG));
$log->pushHandler(new StreamHandler(__DIR__ . '/applogs/errors.log', Logger::ERROR));
$log->pushProcessor(function ($record) {
    $record['extra']['ip'] = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
    return $record;
});


/* // Register Logger (might remove, TODO:testing)
$container->set(Logger::class, function() use ($log) {
    return $log;
});
 */

// Setup database using MeekroDB (adjust as needed)
if ($_SERVER['SERVER_NAME'] == 'daycaresystem.org') {
    DB::$dbName = 'cp5114_team4';
    DB::$user = 'cp5114_team4';
    DB::$password = '=w%S0M.pGNq_';
    DB::$host = 'fsd13.ca';
} else {
    DB::$dbName = 'cp5114_team4';
    DB::$user = 'cp5114_team4';
    DB::$password = '=w%S0M.pGNq_';
}

// Create dependency injection container and set it to Slim
$container = new Container();
AppFactory::setContainer($container);

// Register Twig view in container
$container->set(Twig::class, function() {
    return Twig::create(__DIR__ . '/templates', [
        'cache' => __DIR__ . '/tmplcache',
        'debug' => true
    ]);
});

// Create Slim app from container
$app = AppFactory::createFromContainer($container);

// Set base path if the app is in a subdirectory (uncomment and adjust if needed)
//$app->setBasePath('/teamsproject');
$app->setBasePath('/daycaresystem/DayCare_Team4');

// Add Twig middleware for rendering templates
$app->add(TwigMiddleware::create($app, $container->get(Twig::class)));

// Add routing and error middleware
$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);

// Register Logger (might remove, TODO:testing)
$container->set(Logger::class, function() use ($log) {
    return $log;
});
