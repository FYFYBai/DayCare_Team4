<?php
use Psr\Http\Message\ResponseInterface as Response; //  interfaces for HTTP messages
use Psr\Http\Message\ServerRequestInterface as Request; //  interfaces for HTTP messages
use Slim\Factory\AppFactory; // used to create the Slim application instance
use DI\Container; // Container comes from PHP-DI, a dependency injection container
use Slim\Views\Twig; // Twig and TwigMiddleware are used to integrate Twig (a templating engine) into Slim app
use Slim\Views\TwigMiddleware;

require_once 'init.php';

// URL handlers 
/* 
$app->get('/login', function ($request, $response, $args) use ($container) {
    $twig = $container->get(Twig::class); 
    return $twig->render($response, 'addPerson.html.twig');
});


$app->get('/logout', function ($request, $response, $args) use ($container) {
    $twig = $container->get(Twig::class);
    return $twig->render($response, 'addPerson.html.twig');
}); */

$app->get('/register', function ($request, $response, $args) {
    return $this->get(Twig::class)->render($response, 'register.html.twig');
});

// The route for user registration
// 1. Admins (manual promotion in the DB) can have is_admin = 1.
// 2. Regular users (such as parents, educators, and managers) will have is_admin = 0
$app->post('/register', function ($request, $response, $args) {
    $data = $request->getParsedBody();
    $name = $data['name'];
    $email = $data['email'];
    $password = password_hash($data['password'], PASSWORD_DEFAULT);
    $role = $data['role'];

    // Prevent users from selecting 'manager' during registration
    if (!in_array($role, ['parent', 'educator'])) {
        return $response->withStatus(400)->write("Invalid role selection.");
    }

    // Check if the email is already registered
    $stmt = $this->get('db')->prepare("SELECT id FROM users WHERE email = ? AND isDeleted = 0");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return $response->withStatus(400)->write("Email already registered.");
    }

    // Handle profile photo upload
    $uploadedFiles = $request->getUploadedFiles();
    $profilePhoto = $uploadedFiles['profile_photo'];
    $filename = 'default.png'; // Default profile picture

    if ($profilePhoto->getError() === UPLOAD_ERR_OK) {
        $filename = uniqid() . '-' . $profilePhoto->getClientFilename();
        $profilePhoto->moveTo(__DIR__ . '/uploads/' . $filename);
    }

    // Generate activation token
    $activation_token = bin2hex(random_bytes(32));

    // Insert new user (always with isAdmin = 0, activation_status = 0)
    $stmt = $this->get('db')->prepare("
        INSERT INTO users (name, email, password, role, isAdmin, activation_status, isDeleted, created_at) 
        VALUES (?, ?, ?, ?, 0, 0, 0, NOW())"
    );
    $stmt->execute([$name, $email, $password, $role]);

    // Send activation email
    $activation_link = "https://yourdomain.com/activate?token=$activation_token";
    mail($email, "Activate Your Account", "Click this link to activate your account: $activation_link");

    return $response->write("Registration successful. Check your email to activate your account.");
});



// fetch the is_admin value and store it in the session
//We fetch the user’s details based on the email.
//We verify the password.
//We store the is_admin value in the session, so we can use it later to check if the user is an admin.
$app->post('/login', function ($request, $response, $args) {
    $data = $request->getParsedBody();
    $email = $data['email'];
    $password = $data['password'];

    // Fetch user from the database
    $stmt = $this->get('db')->prepare("SELECT * FROM users WHERE email = ? AND isDeleted = 0");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // Check if the user exists and password matches
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['is_admin'] = $user['is_admin']; // Store is_admin value

        return $response->withRedirect('/dashboard'); // Redirect to dashboard
    } else {
        return $response->withStatus(401)->write("Invalid credentials.");
    }
});

// Example route that only admins should access
// We check if the user is a manager and has the is_admin flag set to 1. If not, they are denied access with a 403 status code.
$app->get('/admin/dashboard', function ($request, $response, $args) {
    if ($_SESSION['role'] !== 'manager' || $_SESSION['is_admin'] !== 1) {
        return $response->withStatus(403)->write("Unauthorized: Admin access required.");
    }
    
    // Admin dashboard logic here
    return $response->write("Welcome, Admin!");
});

// Example route to render the dashboard
$app->get('/dashboard', function ($request, $response, $args) {
    return $this->get('view')->render($response, 'dashboard.html.twig', [
        'is_admin' => $_SESSION['is_admin']
    ]);
});
