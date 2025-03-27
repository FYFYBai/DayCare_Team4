<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

// GET route: Display educator profile
// GET route: Display educator profile
$app->get('/educator/profile', function (Request $request, Response $response, $args) {
    $educatorId = $_SESSION['user_id'];

    // Fetch educator details
    $user = DB::queryFirstRow("SELECT * FROM users WHERE id = %d", $educatorId);

    // Count children assigned to this educator
    $childCount = DB::queryFirstField("SELECT COUNT(*) FROM children WHERE educator_id = %d", $educatorId);

    return $this->get(Twig::class)->render($response, 'educator_profile.html.twig', [
        'user' => $user,
        'childCount' => $childCount
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
