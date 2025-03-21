<?php

require_once 'init.php';

// URL HANDLERS GO BELOW

require_once 'user.php'; 

require_once 'testing.php'; 

require_once 'admin.php';

// DO NOT FORGET APP RUN - to handle HTTP requests
$app->run(); // This tells Slim to process the incoming HTTP request, match it to a route, execute the route callback, and send back the response.

// A container in this context is simply a centralized place for storing objects and their configuration. A dependency injection (DI) container is a specialized container that manages dependencies between different parts of your application
// Decoupling and Flexibility: By using a DI container, your code becomes more decoupled. Classes do not need to know how to instantiate their dependencies; they simply declare what they need, and the container provides it. This leads to code that is easier to test and maintain
// DI makes your code cleaner and more modular

// App: The main Slim application object that defines and manages the routes.
// Route: A mapping between an HTTP method (like GET, POST) and a URL pattern (like /login, /register).
// Route Handler: A function or method executed when a request matches a defined route. It processes the request and returns an appropriate response.