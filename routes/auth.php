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
})->setName('login'); 

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
            $redirectUrl = '/manager/dashboard';
            break;
        case 'educator':
            $redirectUrl = '/educator-dashboard';
            break;
        case 'parent':
            $redirectUrl = '/parent-dashboard';
            break;
        case 'admin':
            $redirectUrl = '/admin/dashboard';
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

// GET route for Resend Activation Email Form
$app->get('/resend-activation', function (Request $request, Response $response, $args) {
    $flash = $this->get(\Slim\Flash\Messages::class);
    return $this->get(Twig::class)->render($response, 'resend-activation.html.twig', [
        'messages' => $flash->getMessages()
    ]);
})->setName('resend-activation');

// POST route for processing the Resend Activation Email request
$app->post('/resend-activation', function (Request $request, Response $response, $args) {
    $data = $request->getParsedBody();
    $email = trim($data['email'] ?? '');
    
    $flash  = $this->get(\Slim\Flash\Messages::class);
    $router = RouteContext::fromRequest($request)->getRouteParser();
    
    // Validate email input
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $flash->addMessage('error', "Please enter a valid email address.");
        return $response->withHeader('Location', $router->urlFor('resend-activation'))->withStatus(302);
    }
    
    // Check for user in the database
    $user = DB::queryFirstRow("SELECT * FROM users WHERE email = %s AND isDeleted = 0", $email);
    // Use a generic message to avoid account enumeration
    if (!$user) {
        $flash->addMessage('success', "If the email exists, an activation email has been sent.");
        return $response->withHeader('Location', $router->urlFor('login'))->withStatus(302);
    }
    
    // If account is already activated, inform the user
    if ($user['activation_status'] == 1) {
        $flash->addMessage('info', "Your account is already activated. Please log in.");
        return $response->withHeader('Location', $router->urlFor('login'))->withStatus(302);
    }
    
    // Generate new activation token
    $activation_token = bin2hex(random_bytes(32));
    DB::update('users', ['activation_token' => $activation_token], "email=%s", $email);
    
    // Build activation link (adjust domain/port as needed)
    $activation_link = "https://team4.fsd13.ca/activate?token=" . $activation_token;
    
    // Send the activation email using your helper function
    if (sendActivationEmail($email, $user['name'], $activation_link)) {
        $devLink = "";
        if (($_ENV['APP_ENV'] ?? 'dev') !== 'prod') {
            $devLink = "<br><strong>Dev/Test:</strong> <a href='{$activation_link}' target='_blank'>Click here to activate</a>";
        }
        $flash->addMessage('success', "Activation email sent. Please check your inbox." . $devLink);
    } else {
        $flash->addMessage('error', "Error sending activation email. Please try again.");
    }
    
    return $response->withHeader('Location', $router->urlFor('login'))->withStatus(302);
});
