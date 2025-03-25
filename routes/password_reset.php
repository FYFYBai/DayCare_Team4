<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use Slim\Routing\RouteContext;

// Display forgot-password form
$app->get('/forgot-password', function (Request $request, Response $response, $args) {
    $flash = $this->get(\Slim\Flash\Messages::class);
    $messages = $flash->getMessages();

    return $this->get(Twig::class)->render($response, 'forgot-password.html.twig', [
        'messages' => $messages
    ]);
})->setName('forgot-password');

// Process forgot-password form submission
$app->post('/forgot-password', function (Request $request, Response $response, $args) {
    $data  = $request->getParsedBody();
    $email = trim($data['email']);

    $flash  = $this->get(\Slim\Flash\Messages::class);
    $router = RouteContext::fromRequest($request)->getRouteParser();

    // Query the user
    $user = DB::queryFirstRow("SELECT * FROM users WHERE email = %s AND isDeleted = 0", $email);
    if (!$user) {
        // Always respond with the same message to avoid account enumeration
        $flash->addMessage('success', "If the email exists, a password reset link has been sent.");
        return $response->withHeader('Location', $router->urlFor('forgot-password'))->withStatus(302);
    }

    // Generate reset token and expiry (1 hour)
    $reset_token = bin2hex(random_bytes(32));
    $expires_at  = date("Y-m-d H:i:s", strtotime("+1 hour"));

    DB::update('users', [
        'reset_token'   => $reset_token,
        'reset_expires' => $expires_at
    ], "email=%s", $email);

    //$reset_link = "http://daycaresystem.org:8080/reset-password?token=" . $reset_token;
    $reset_link = "https://team4.fsd13.ca/reset-password?token=" . $reset_token;

    if (sendPasswordResetEmail($email, $user['name'], $reset_link)) {
        $devLink = "<br><strong>Dev/Test:</strong> <a href='{$reset_link}' target='_blank'>Click here to reset password</a>";
        $flash->addMessage('success', "If the email exists, a password reset link has been sent..{$devLink}");
    } else {
        $flash->addMessage('error', "An error occurred sending the password reset email.");
    }
    return $response->withHeader('Location', $router->urlFor('forgot-password'))->withStatus(302);
});

// Display reset-password form (GET)
$app->get('/reset-password', function (Request $request, Response $response, $args) {
    $token = $request->getQueryParams()['token'] ?? null;
    $flash = $this->get(\Slim\Flash\Messages::class);

    if (!$token) {
        $flash->addMessage('error', "Invalid password reset link.");
        return $response->withHeader('Location', '/forgot-password')->withStatus(302);
    }
    return $this->get(Twig::class)->render($response, 'reset-password.html.twig', [
        'token'    => $token,
        'messages' => $flash->getMessages()
    ]);
});

// Process reset-password form submission (POST)
$app->post('/reset-password', function (Request $request, Response $response, $args) {
    $data             = $request->getParsedBody();
    $token            = $data['token'] ?? null;
    $password         = $data['password'] ?? '';
    $password_confirm = $data['password_confirm'] ?? '';

    $flash  = $this->get(\Slim\Flash\Messages::class);
    $router = RouteContext::fromRequest($request)->getRouteParser();

    if (!$token) {
        $flash->addMessage('error', "No reset token provided.");
        return $response->withHeader('Location', $router->urlFor('forgot-password'))->withStatus(302);
    }
    if ($password !== $password_confirm) {
        $flash->addMessage('error', "Passwords do not match.");
        return $response->withHeader('Location', '/reset-password?token=' . urlencode($token))->withStatus(302);
    }

    // Validate password strength
    if (
        strlen($password) < 8 ||
        !preg_match('/[A-Z]/', $password) ||
        !preg_match('/[a-z]/', $password) ||
        !preg_match('/[0-9]/', $password) ||
        !preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)
    ) {
        $flash->addMessage('error', "Password must be at least 8 characters long and include one uppercase letter, one lowercase letter, one number, and one special character.");
        return $response->withHeader('Location', '/reset-password?token=' . urlencode($token))->withStatus(302);
    }
    
    $user = DB::queryFirstRow("SELECT * FROM users WHERE reset_token = %s AND reset_expires >= NOW()", $token);
    if (!$user) {
        $flash->addMessage('error', "Invalid or expired reset token.");
        return $response->withHeader('Location', $router->urlFor('forgot-password'))->withStatus(302);
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    DB::update('users', [
        'password'      => $hashedPassword,
        'reset_token'   => null,
        'reset_expires' => null
    ], "id=%d", $user['id']);

    $flash->addMessage('success', "Password updated successfully. You may now log in.");
    return $response->withHeader('Location', '/login')->withStatus(302);
});
