<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

// GET route: Display educator profile
$app->get('/educator/profile', function (Request $request, Response $response, $args) {
    $user = DB::queryFirstRow("SELECT * FROM users WHERE id = %d", $_SESSION['user_id']);
    return $this->get(Twig::class)->render($response, 'educator_profile.html.twig', [
        'user' => $user
    ]);
})->add($checkRoleMiddleware('educator'));

// POST route: Update educator profile
$app->post('/educator/profile', function (Request $request, Response $response, $args) {
    $data = $request->getParsedBody();
    DB::update('users', [
        'name' => trim($data['name']),
        'email' => trim($data['email'])
        // add other fields as needed
    ], "id=%d", $_SESSION['user_id']);
    $flash = $this->get(\Slim\Flash\Messages::class);
    $flash->addMessage('success', "Profile updated successfully.");
    return $response->withHeader('Location', '/educator/profile')->withStatus(302);
})->add($checkRoleMiddleware('educator'));
