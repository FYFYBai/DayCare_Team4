<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use Ramsey\Uuid\Uuid; // For unique filenames if you handle file uploads

/**
 * CHILD DATA MANAGEMENT ROUTES
 *
 * As a parent, I want to view and edit my child’s profile
 * so that I can keep their information up-to-date.
 */

// Group the routes under /child, requiring parent role
$app->group('/child', function () use ($app) {
    
    // 1) List all children for the logged-in parent
    $app->get('/list', function (Request $request, Response $response, $args) {
        // Retrieve children where parent_id = current user, and isDeleted=0
        $children = DB::query("SELECT * FROM children WHERE parent_id=%i AND isDeleted=0", $_SESSION['user_id'] ?? 0);

        // Render a Twig template that displays a list of the parent's children
        return $this->get(Twig::class)->render($response, 'child_list.html.twig', [
            'children' => $children
        ]);
    });

    // 2) GET route to display an edit form for a specific child
    $app->get('/{childId}/edit', function (Request $request, Response $response, array $args) {
        $childId = (int)$args['childId'];

        // Make sure this child belongs to the logged-in parent and is not deleted
        $child = DB::queryFirstRow(
            "SELECT * FROM children WHERE id=%i AND parent_id=%i AND isDeleted=0",
            $childId,
            $_SESSION['user_id'] ?? 0
        );
        if (!$child) {
            $response->getBody()->write("Child not found or you do not have permission to edit this child's data.");
            return $response->withStatus(404);
        }

        // Render the edit form
        return $this->get(Twig::class)->render($response, 'child_edit.html.twig', [
            'child' => $child
        ]);
    });

    // 3) POST route to handle form submission and update child data
    $app->post('/{childId}/edit', function (Request $request, Response $response, array $args) {
        $childId = (int)$args['childId'];

        // Verify this child belongs to the parent
        $child = DB::queryFirstRow(
            "SELECT * FROM children WHERE id=%i AND parent_id=%i AND isDeleted=0",
            $childId,
            $_SESSION['user_id'] ?? 0
        );
        if (!$child) {
            $response->getBody()->write("Child not found or you do not have permission to edit this child's data.");
            return $response->withStatus(404);
        }

        $data = $request->getParsedBody();
        $name = trim($data['name'] ?? '');
        $dob  = trim($data['date_of_birth'] ?? '');

        // Basic validation
        if ($name === '' || $dob === '') {
            $response->getBody()->write("Name and Date of Birth are required.");
            return $response->withStatus(400);
        }
        // Optionally check date format
        if (strtotime($dob) === false) {
            $response->getBody()->write("Invalid date format for Date of Birth.");
            return $response->withStatus(400);
        }

        // Handle optional new profile photo upload
        $uploadedFiles = $request->getUploadedFiles();
        $profilePhoto  = $uploadedFiles['profile_photo'] ?? null;
        $photoPath     = $child['profile_photo_path']; // Keep existing if no new upload

        if ($profilePhoto && $profilePhoto->getError() === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/png'];
            if (!in_array($profilePhoto->getClientMediaType(), $allowedTypes)) {
                $response->getBody()->write("Invalid file type. Only JPEG and PNG allowed.");
                return $response->withStatus(400);
            }
            if ($profilePhoto->getSize() > (2 * 1024 * 1024)) {
                $response->getBody()->write("File size exceeds 2MB limit.");
                return $response->withStatus(400);
            }
            // Generate a unique filename
            $filename = Uuid::uuid4()->toString() . '-' . $profilePhoto->getClientFilename();
            $profilePhoto->moveTo(__DIR__ . '/../uploads/' . $filename);

            $photoPath = $filename; // Update new photo path
        }

        // Update record
        DB::update('children', [
            'name'              => $name,
            'date_of_birth'     => $dob,
            'profile_photo_path' => $photoPath
        ], "id=%i", $childId);

        $response->getBody()->write("Child data updated successfully.");
        return $response;
    });
})->add($checkRoleMiddleware('parent')); // Only parents can access these routes
