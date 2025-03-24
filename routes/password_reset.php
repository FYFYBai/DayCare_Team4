<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

// Display forgot-password form
$app->get('/forgot-password', function (Request $request, Response $response, $args) {
    return $this->get(Twig::class)->render($response, 'forgot-password.html.twig');
});

// Process forgot-password form submission
$app->post('/forgot-password', function (Request $request, Response $response, $args) {
    $data = $request->getParsedBody();
    $email = trim($data['email']);
    
    $user = DB::queryFirstRow("SELECT * FROM users WHERE email = %s AND isDeleted = 0", $email);
    if (!$user) {
        $response->getBody()->write("If the email exists, a password reset link has been sent.");
        return $response;
    }
    
    $reset_token = bin2hex(random_bytes(32));
    $expires_at = date("Y-m-d H:i:s", strtotime("+1 hour"));
    
    DB::update('users', [
        'reset_token' => $reset_token,
        'reset_expires' => $expires_at
    ], "email=%s", $email);
    
    $reset_link = "http://daycaresystem.org:8080/reset-password?token=" . $reset_token;
    // $reset_link = "https://team4.fsd13.ca/reset-password?token=" . $reset_token;
    
    if (sendActivationEmail($email, $user['name'], $reset_link)) {
        $message = "If the email exists, a password reset link has been sent.";
    } else {
        $message = "An error occurred sending the password reset email.";
    }
    $response->getBody()->write($message);
    return $response;
});

// Display reset-password form (GET)
$app->get('/reset-password', function (Request $request, Response $response, $args) {
    $token = $request->getQueryParams()['token'] ?? null;
    if (!$token) {
        $response->getBody()->write("Invalid password reset link.");
        return $response->withStatus(400);
    }
    return $this->get(Twig::class)->render($response, 'reset-password.html.twig', [
        'token' => $token
    ]);
});

// Process reset-password form submission (POST)
$app->post('/reset-password', function (Request $request, Response $response, $args) {
    $data = $request->getParsedBody();
    $token = $data['token'] ?? null;
    $password = $data['password'] ?? '';
    $password_confirm = $data['password_confirm'] ?? '';
    
    if (!$token) {
        $response->getBody()->write("No reset token provided.");
        return $response->withStatus(400);
    }
    if ($password !== $password_confirm) {
        $response->getBody()->write("Passwords do not match.");
        return $response->withStatus(400);
    }
    
    $user = DB::queryFirstRow("SELECT * FROM users WHERE reset_token = %s AND reset_expires >= NOW()", $token);
    if (!$user) {
        $response->getBody()->write("Invalid or expired reset token.");
        return $response->withStatus(400);
    }
    
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    DB::update('users', [
        'password' => $hashedPassword,
        'reset_token' => null,
        'reset_expires' => null
    ], "id=%d", $user['id']);
    
    $response->getBody()->write("Password updated successfully. You may now log in.");
    return $response;
});
