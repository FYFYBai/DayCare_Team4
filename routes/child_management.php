<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use Slim\Routing\RouteCollectorProxy;
use Ramsey\Uuid\Uuid; // For unique filenames if you handle file uploads

/**
 * CHILD DATA MANAGEMENT ROUTES
 *
 * As a parent, I want to view, create, and edit my child’s profile
 * so that I can keep their information up-to-date.
 */

$app->group('/child', function (RouteCollectorProxy $group) {

    // 1) List all children for the logged-in parent
    $group->get('/list', function (Request $request, Response $response, $args) {
        // Retrieve children for the current parent
        $children = DB::query("SELECT * FROM children WHERE parent_id=%i AND isDeleted=0", $_SESSION['user_id'] ?? 0);

        // For each child, calculate their age and store it in the array
        foreach ($children as &$child) {
            $child['age'] = calculateAge($child['date_of_birth']);
        }

        // Render the Twig template
        return $this->get(Twig::class)->render($response, 'child_list.html.twig', [
            'children' => $children
        ]);
    });

    // 2) GET route to display a form to create a new child
    $group->get('/new', function (Request $request, Response $response, $args) {
        // Render a Twig template with a form to add a new child
        return $this->get(Twig::class)->render($response, 'child_create.html.twig');
    });

    // 3) POST route to handle creation of a new child
    $group->post('/new', function (Request $request, Response $response, $args) {
        $data = $request->getParsedBody();
        $name = trim($data['name'] ?? '');
        $dob  = trim($data['date_of_birth'] ?? '');

        // Basic validation
        if ($name === '' || $dob === '') {
            $response->getBody()->write("Name and Date of Birth are required.");
            return $response->withStatus(400);
        }
        if (strtotime($dob) === false) {
            $response->getBody()->write("Invalid date format for Date of Birth.");
            return $response->withStatus(400);
        }

        $photoPath = ''; // default to empty or 'default.png' if desired

        // Handle file upload from input field "profile_photo"
        $uploadedFiles = $request->getUploadedFiles();
        $profilePhoto  = $uploadedFiles['profile_photo'] ?? null;
        if ($profilePhoto && $profilePhoto->getError() === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
            if (!in_array($profilePhoto->getClientMediaType(), $allowedTypes)) {
                $response->getBody()->write("Invalid file type. Only JPEG, JPG and PNG allowed.");
                return $response->withStatus(400);
            }
            if ($profilePhoto->getSize() > (2 * 1024 * 1024)) {
                $response->getBody()->write("File size exceeds 2MB limit.");
                return $response->withStatus(400);
            }
            // Generate a unique filename using UUID (temporary, will be overridden if captured image is provided)
            $filename = Uuid::uuid4()->toString() . '-' . $profilePhoto->getClientFilename();
            $profilePhoto->moveTo(__DIR__ . '/../uploads/' . $filename);
            $photoPath = $filename;
        }

        // Handle captured image (webcam capture)
        $capturedImage = trim($data['captured_image'] ?? '');
        if (!empty($capturedImage)) {
            // Expect a data URL like: "data:image/png;base64,...."
            $parts = explode(',', $capturedImage);
            if (count($parts) == 2) {
                $imageData = base64_decode($parts[1]);
                // Sanitize the child's name: lowercase and replace spaces with underscores
                $sanitizedChildName = strtolower(str_replace(' ', '_', $name));
                // Append a timestamp to ensure uniqueness
                $filename = $sanitizedChildName . '-' . time() . '.png';
                file_put_contents(__DIR__ . '/../uploads/' . $filename, $imageData);
                $photoPath = $filename;
            }
        }

        // Insert new child record
        DB::insert('children', [
            'parent_id'          => $_SESSION['user_id'] ?? 0,
            'name'               => $name,
            'date_of_birth'      => $dob,
            'profile_photo_path' => $photoPath,
            'isDeleted'          => 0
        ]);

        // Redirect to child list
        return $response
            ->withHeader('Location', '/child/list')
            ->withStatus(302);
    });

    // 4) GET route to display an edit form for a specific child
    $group->get('/{childId}/edit', function (Request $request, Response $response, array $args) {
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

    // 5) POST route to handle form submission and update child data
    $group->post('/{childId}/edit', function (Request $request, Response $response, array $args) {
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
        if (strtotime($dob) === false) {
            $response->getBody()->write("Invalid date format for Date of Birth.");
            return $response->withStatus(400);
        }

        // Default: keep existing image path
        $photoPath = $child['profile_photo_path'];

        // Handle file upload from input field "profile_photo"
        $uploadedFiles = $request->getUploadedFiles();
        $profilePhoto  = $uploadedFiles['profile_photo'] ?? null;
        if ($profilePhoto && $profilePhoto->getError() === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
            if (!in_array($profilePhoto->getClientMediaType(), $allowedTypes)) {
                $response->getBody()->write("Invalid file type. Only JPEG, JPG and PNG allowed.");
                return $response->withStatus(400);
            }
            if ($profilePhoto->getSize() > (2 * 1024 * 1024)) {
                $response->getBody()->write("File size exceeds 2MB limit.");
                return $response->withStatus(400);
            }
            // If an old image exists, delete it
            if (!empty($child['profile_photo_path'])) {
                $oldFile = __DIR__ . '/../uploads/' . $child['profile_photo_path'];
                if (file_exists($oldFile)) {
                    unlink($oldFile);
                }
            }
            // Generate a unique filename using UUID
            $filename = Uuid::uuid4()->toString() . '-' . $profilePhoto->getClientFilename();
            $profilePhoto->moveTo(__DIR__ . '/../uploads/' . $filename);
            $photoPath = $filename;
        }

        // Handle captured image (webcam capture)
        $capturedImage = trim($data['captured_image'] ?? '');
        if (!empty($capturedImage)) {
            // If an old image exists, delete it
            if (!empty($child['profile_photo_path'])) {
                $oldFile = __DIR__ . '/../uploads/' . $child['profile_photo_path'];
                if (file_exists($oldFile)) {
                    unlink($oldFile);
                }
            }
            // Expect a data URL like "data:image/png;base64,..."
            $parts = explode(',', $capturedImage);
            if (count($parts) == 2) {
                $imageData = base64_decode($parts[1]);
                // Sanitize child's name: lowercase and replace spaces with underscores
                $sanitizedChildName = strtolower(str_replace(' ', '_', $name));
                $filename = $sanitizedChildName . '-' . time() . '.png';
                file_put_contents(__DIR__ . '/../uploads/' . $filename, $imageData);
                $photoPath = $filename;
            }
        }

        // Update record
        DB::update('children', [
            'name'               => $name,
            'date_of_birth'      => $dob,
            'profile_photo_path' => $photoPath
        ], "id=%i", $childId);

        // Redirect back to the child list
        return $response
            ->withHeader('Location', '/child/list')
            ->withStatus(302);
    });

    // 6) POST route to handle deletion of the child
    $group->post('/{childId}/delete', function (Request $request, Response $response, array $args) {
        $childId = (int)$args['childId'];

        // Verify the child belongs to the parent
        $child = DB::queryFirstRow(
            "SELECT * FROM children WHERE id=%i AND parent_id=%i AND isDeleted=0",
            $childId,
            $_SESSION['user_id'] ?? 0
        );
        if (!$child) {
            $response->getBody()->write("Child not found or you do not have permission to delete this child's data.");
            return $response->withStatus(404);
        }

        // Delete the corresponding picture file if it exists
        if (!empty($child['profile_photo_path'])) {
            $filePath = __DIR__ . '/../uploads/' . $child['profile_photo_path'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }

        // Soft-delete the child
        DB::update('children', ['isDeleted' => 1], "id=%i", $childId);

        // Redirect back to the child list
        return $response
            ->withHeader('Location', '/child/list')
            ->withStatus(302);
    });
})->add($checkRoleMiddleware('parent')); // Only parents can access these routes

// Additional route for child profile accessible by educators
$app->get('/child/{childId}', function (Request $request, Response $response, array $args) {
    $childId = (int)$args['childId'];
    $child = DB::queryFirstRow(
        "SELECT * FROM children WHERE id = %i AND educator_id = %i AND isDeleted = 0",
        $childId,
        $_SESSION['user_id']
    );
    if (!$child) {
        throw new \Slim\Exception\HttpNotFoundException($request, "Child not found or access denied.");
    }
    return $this->get(Twig::class)->render($response, 'child_profile.html.twig', [
        'child' => $child
    ]);
})->add($checkRoleMiddleware('educator'));
