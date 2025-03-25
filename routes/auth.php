<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use Slim\Routing\RouteContext;

// Display login form
$app->get('/login', function (Request $request, Response $response, $args) {
    $flash = $this->get(\Slim\Flash\Messages::class);
    $messages = $flash->getMessages();

    $formData = $_SESSION['loginForm'] ?? [];
    unset($_SESSION['loginForm']); // clear it once used

    return $this->get(Twig::class)->render($response, 'login.html.twig', [
        'messages' => $messages,
        'formData' => $formData
    ]);
})->setName('login');  // <-- Set the route name here

// Process login form submission
$app->post('/login', function (Request $request, Response $response, $args) {
    $data = $request->getParsedBody();
    $_SESSION['loginForm'] = $data;

    $flash = $this->get(\Slim\Flash\Messages::class);
    $router = RouteContext::fromRequest($request)->getRouteParser();

    if (empty($data['email']) || empty($data['password'])) {
        $flash->addMessage('error', 'Email and password are required.');
        return $response->withHeader('Location', $router->urlFor('login'))->withStatus(302);
    }

    $email = trim($data['email']);
    $password = $data['password'];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $flash->addMessage('error', 'Invalid email format.');
        return $response->withHeader('Location', $router->urlFor('login'))->withStatus(302);
    }

    $user = DB::queryFirstRow("SELECT * FROM users WHERE email = %s AND isDeleted = 0", $email);

    if (!$user || !password_verify($password, $user['password'])) {
        $flash->addMessage('error', 'Invalid credentials.');
        return $response->withHeader('Location', $router->urlFor('login'))->withStatus(302);
    }

    if ($user['activation_status'] == 0) {
        $flash->addMessage('error', 'Please activate your account via the email link.');
        return $response->withHeader('Location', $router->urlFor('login'))->withStatus(302);
    }

    // Auth success: set session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['is_admin'] = $user['isAdmin'];

    // Clear login form data on success
    unset($_SESSION['loginForm']);

    // Role-based redirect
    switch ($user['role']) {
        case 'manager':
            $redirectUrl = '/dashboard';
            break;
        case 'educator':
            $redirectUrl = '/educator-dashboard';
            break;
        case 'parent':
            $redirectUrl = '/parent-dashboard';
            break;
        default:
            $redirectUrl = '/dashboard';
    }

    return $response->withHeader('Location', $redirectUrl)->withStatus(302);
});

// Logout route
$app->get('/logout', function (Request $request, Response $response, $args) {
    session_unset();
    session_destroy();
    return $response->withHeader('Location', '/login')->withStatus(302);
});
