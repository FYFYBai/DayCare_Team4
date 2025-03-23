<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/**
 * USER MANAGEMENT ROUTES (ADMIN ONLY)
 *
 * As an administrator, I want to manage users (edit, delete)
 * so that I can ensure that all user data is accurate and up-to-date.
 */

// Group the routes under /admin, requiring 'admin' role
$app->group('/admin', function () use ($app) {

    // 1) List all non-deleted users
    $app->get('/users', function (Request $request, Response $response, $args) {
        $users = DB::query("SELECT * FROM users WHERE isDeleted=0");
        return $this->get(Twig::class)->render($response, 'admin_user_list.html.twig', [
            'users' => $users
        ]);
    });

    // 2) GET route: Display a form for editing a user
    $app->get('/user/{userId}/edit', function (Request $request, Response $response, array $args) {
        $userId = (int)$args['userId'];

        $user = DB::queryFirstRow("SELECT * FROM users WHERE id=%i AND isDeleted=0", $userId);
        if (!$user) {
            $response->getBody()->write("User not found or already deleted.");
            return $response->withStatus(404);
        }

        return $this->get(Twig::class)->render($response, 'admin_user_edit.html.twig', [
            'user' => $user
        ]);
    });

    // 3) POST route: Update or Delete user
    $app->post('/user/{userId}/edit', function (Request $request, Response $response, array $args) {
        $userId = (int)$args['userId'];

        $user = DB::queryFirstRow("SELECT * FROM users WHERE id=%i AND isDeleted=0", $userId);
        if (!$user) {
            $response->getBody()->write("User not found or already deleted.");
            return $response->withStatus(404);
        }

        $data = $request->getParsedBody();

        // Check if admin wants to delete
        if (isset($data['action']) && $data['action'] === 'delete') {
            // Soft-delete the user
            DB::update('users', ['isDeleted' => 1], "id=%i", $userId);
            $response->getBody()->write("User deleted successfully.");
            return $response;
        }

        // Otherwise, update user info
        $name  = trim($data['name'] ?? '');
        $email = trim($data['email'] ?? '');
        $role  = trim($data['role'] ?? $user['role']); // optional role update

        if ($name === '' || $email === '') {
            $response->getBody()->write("Name and Email are required.");
            return $response->withStatus(400);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response->getBody()->write("Invalid email format.");
            return $response->withStatus(400);
        }

        // Possibly validate role if you're allowing the admin to change it    i.e. removing admin from the list will prevent the user to promote the profile to an admin role.
        $allowedRoles = ['parent', 'educator', 'manager', 'admin'];
        if (!in_array($role, $allowedRoles)) {
            $response->getBody()->write("Invalid role.");
            return $response->withStatus(400);
        }

        DB::update('users', [
            'name' => $name,
            'email' => $email,
            'role' => $role
        ], "id=%i", $userId);

        $response->getBody()->write("User updated successfully.");
        return $response;
    });
})->add($checkRoleMiddleware('admin')); // Only admins can access these
