<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/**
 * USER MANAGEMENT ROUTES (ADMIN ONLY)
 *
 * 1) List all non-deleted users
 * 2) Display the selected user in a table for edit (GET /admin/user/{userId}/edit)
 * 3) POST route to update or delete user profile
 */

$app->group('/admin', function () use ($app) {

    // (1) GET /admin/users -> List all non-deleted users
    $app->get('/users', function (Request $request, Response $response, $args) {
        $users = DB::query("SELECT * FROM users WHERE isDeleted=0");
        return $this->get(Twig::class)->render($response, 'admin_user_list.html.twig', [
            'users' => $users
        ]);
    });

    // (2) GET /admin/user/{userId}/edit -> Show user info in a table
    $app->get('/user/{userId}/edit', function (Request $request, Response $response, array $args) {
        $userId = (int)$args['userId'];

        // Fetch the user from DB, ensuring they're not soft-deleted
        $user = DB::queryFirstRow("SELECT * FROM users WHERE id=%i AND isDeleted=0", $userId);
        if (!$user) {
            $response->getBody()->write("User not found or already deleted.");
            return $response->withStatus(404);
        }

        // Render a Twig template that displays the user's data in a table
        return $this->get(Twig::class)->render($response, 'admin_user_edit.html.twig', [
            'user' => $user
        ]);
    });

    // (3) POST /admin/user/{userId}/edit -> Update or delete user profile
    $app->post('/user/{userId}/edit', function (Request $request, Response $response, array $args) {
        $userId = (int)$args['userId'];

        // Fetch the user again
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

        // Otherwise, we are updating a specific column
        $column   = $data['column']   ?? '';
        $newValue = trim($data['newValue'] ?? '');

        // Basic checks
        if ($column === '') {
            $response->getBody()->write("No column specified for update.");
            return $response->withStatus(400);
        }

        // Prepare a list of valid columns the admin can update
        $allowedColumns = ['name', 'email', 'role', 'password'];
        if (!in_array($column, $allowedColumns)) {
            $response->getBody()->write("Invalid or uneditable column.");
            return $response->withStatus(400);
        }

        // We'll build the $updateData array to pass to DB::update
        $updateData = [];

        switch ($column) {
            case 'name':
                if ($newValue === '') {
                    $response->getBody()->write("Name cannot be empty.");
                    return $response->withStatus(400);
                }
                $updateData['name'] = $newValue;
                break;

            case 'email':
                if (!filter_var($newValue, FILTER_VALIDATE_EMAIL)) {
                    $response->getBody()->write("Invalid email format.");
                    return $response->withStatus(400);
                }
                $updateData['email'] = $newValue;
                break;

            case 'role':
                $allowedRoles = ['parent', 'educator', 'manager', 'admin'];
                if (!in_array($newValue, $allowedRoles)) {
                    $response->getBody()->write("Invalid role.");
                    return $response->withStatus(400);
                }
                $updateData['role']    = $newValue;
                $updateData['isAdmin'] = ($newValue === 'admin') ? 1 : 0;
                break;

            case 'password':
                // If blank, do nothing or handle as error
                if ($newValue === '') {
                    $response->getBody()->write("Password cannot be empty.");
                    return $response->withStatus(400);
                }
                // Optionally validate password strength
                if (strlen($newValue) < 8) {
                    $response->getBody()->write("Password must be at least 8 characters long.");
                    return $response->withStatus(400);
                }
                // Hash the new password
                $hashedPassword = password_hash($newValue, PASSWORD_DEFAULT);
                $updateData['password'] = $hashedPassword;
                break;
        }

        // Perform the update
        DB::update('users', $updateData, "id=%i", $userId);

        // Redirect back so the admin can see the updated info
        return $response
            ->withHeader('Location', '/admin/user/' . $userId . '/edit')
            ->withStatus(302);
    });
})->add($checkRoleMiddleware('admin')); // Only admins can access
