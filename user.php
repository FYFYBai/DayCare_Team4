<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use Ramsey\Uuid\Uuid; // For generating UUIDs
use MeekroDB;

require_once 'init.php';

/**
 * Helper function to validate ReCaptcha.
 * Replace 'YOUR_SECRET_KEY' with your actual ReCaptcha secret key.
 */
function verifyReCaptcha($recaptchaResponse) {
    $secret = "YOUR_SECRET_KEY";
    $url = "https://www.google.com/recaptcha/api/siteverify?secret=$secret&response=$recaptchaResponse";
    $response = file_get_contents($url);
    $responseData = json_decode($response);
    return $responseData->success;
}

$app->get('/', function (Request $request, Response $response, $args) {
    // If a user is already logged in, redirect them to the dashboard.
    if (isset($_SESSION['user_id'])) {
        return $response->withHeader('Location', '/dashboard')->withStatus(302);
    }
    
    // Otherwise, render the home page (welcome/landing page)
    return $this->get(Slim\Views\Twig::class)->render($response, 'home.html.twig');
});

/**
 * Render the registration form.
 */
$app->get('/register', function (Request $request, Response $response, $args) {
    return $this->get(Twig::class)->render($response, 'register.html.twig');
});

/**
 * User Registration Route.
 */
$app->post('/register', function (Request $request, Response $response, $args) {
    $data = $request->getParsedBody();

    // Validate required fields.
    if (empty($data['name']) || empty($data['email']) || empty($data['password']) || empty($data['role'])) {
        $response->getBody()->write("All fields (name, email, password, role) are required.");
        return $response->withStatus(400); // Bad request
    }

    $name     = trim($data['name']);
    $email    = trim($data['email']);
    $password = $data['password'];
    $role     = strtolower(trim($data['role']));

    // Validate email format.
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response->getBody()->write("Invalid email format.");
        return $response->withStatus(400);
    }

    // Validate password (minimum 8 characters).
    if (strlen($password) < 8) {
        $response->getBody()->write("Password must be at least 8 characters long.");
        return $response->withStatus(400);
    }
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Validate role.
    $allowedRoles = ['parent', 'educator', 'manager'];
    if (!in_array($role, $allowedRoles)) {
        $response->getBody()->write("Invalid role selected.");
        return $response->withStatus(400);
    }

    // Block self-registration for managers.
    if ($role === 'manager') {
        $response->getBody()->write("Manager accounts must be added manually by an admin.");
        return $response->withStatus(400);
    }

    // Validate ReCaptcha (assuming the form sends 'g-recaptcha-response').
    $recaptchaResponse = $data['g-recaptcha-response'] ?? '';
    if (!verifyReCaptcha($recaptchaResponse)) {
        $response->getBody()->write("ReCaptcha verification failed.");
        return $response->withStatus(400);
    }

    // For self-registration, only allow parents or educators (managers are added manually).
    $isAdmin = 0; // Default non-manager.

    // Check if the email already exists.
    $exists = DB::queryFirstField("SELECT id FROM users WHERE email = %s AND isDeleted = 0", $email);
    if ($exists) {
        $response->getBody()->write("Email already registered.");
        return $response->withStatus(400);
    }

    // Handle profile photo upload (only for educators/managers).
    $filename = 'default.png';
    if ($role !== 'parent') {
        $uploadedFiles = $request->getUploadedFiles();
        $profilePhoto  = $uploadedFiles['profile_photo'] ?? null;
        if ($profilePhoto && $profilePhoto->getError() === UPLOAD_ERR_OK) {
            // Validate file type.
            $allowedTypes = ['image/jpeg', 'image/png'];
            if (!in_array($profilePhoto->getClientMediaType(), $allowedTypes)) {
                $response->getBody()->write("Invalid file type. Only JPEG and PNG are allowed.");
                return $response->withStatus(400);
            }
            // Validate file size (limit to 2MB).
            if ($profilePhoto->getSize() > (2 * 1024 * 1024)) {
                $response->getBody()->write("File size exceeds 2MB limit.");
                return $response->withStatus(400);
            }
            // Generate a UUID and prepend it to the original filename.
            $filename = Uuid::uuid4()->toString() . '-' . $profilePhoto->getClientFilename();
            $profilePhoto->moveTo(__DIR__ . '/uploads/' . $filename);
        }
    }

    // Generate activation token.
    $activation_token = bin2hex(random_bytes(32));

    // Insert the new user record.
    DB::insert('users', [
        'name'              => $name,
        'email'             => $email,
        'password'          => $hashedPassword,
        'role'              => $role,
        'isAdmin'           => $isAdmin,
        'activation_status' => 0,
        'isDeleted'         => 0,
        'profile_photo_path'=> $filename, // This requires that your users table has this column.
        'created_at'        => date("Y-m-d H:i:s")
    ]);

    // Store the activation token in the DB (assuming a column named activation_token exists).
    DB::update('users', ['activation_token' => $activation_token], "email=%s", $email);

    // Send an activation email with the activation link.
    $activation_link = "https://team4.fsd13.ca/activate?token=$activation_token";
    mail($email, "Activate Your Account", "Click this link to activate your account: $activation_link");

    $response->getBody()->write("Registration successful. Please check your email to activate your account.");
    return $response;
});

/**
 * User Login Route.
 */
$app->post('/login', function (Request $request, Response $response, $args) {
    $data = $request->getParsedBody();

    // Validate required fields.
    if (empty($data['email']) || empty($data['password'])) {
        $response->getBody()->write("Email and password are required.");
        return $response->withStatus(400);
    }

    $email    = trim($data['email']);
    $password = $data['password'];

    // Validate email format.
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response->getBody()->write("Invalid email format.");
        return $response->withStatus(400);
    }

    // Validate ReCaptcha on login as well.
    $recaptchaResponse = $data['g-recaptcha-response'] ?? '';
    if (!verifyReCaptcha($recaptchaResponse)) {
        $response->getBody()->write("ReCaptcha verification failed.");
        return $response->withStatus(400);
    }

    // Fetch user details.
    $user = DB::queryFirstRow("SELECT * FROM users WHERE email = %s AND isDeleted = 0", $email);
    if (!$user || !password_verify($password, $user['password'])) {
        $response->getBody()->write("Invalid credentials.");
        return $response->withStatus(401);
    }

    // Ensure the account is activated.
    if ($user['activation_status'] == 0) {
        $response->getBody()->write("Please activate your account via the email link.");
        return $response->withStatus(401);
    }

    // Set session variables.
    $_SESSION['user_id']  = $user['id'];
    $_SESSION['role']     = $user['role'];
    $_SESSION['is_admin'] = $user['isAdmin'];

    return $response->withHeader('Location', '/dashboard')->withStatus(302);
});

/**
 * Example Dashboard Route.
 */
$app->get('/dashboard', function (Request $request, Response $response, $args) {
    return $this->get(Twig::class)->render($response, 'dashboard.html.twig', [
        'is_admin' => $_SESSION['is_admin']
    ]);
});

$app->get('/logout', function (Request $request, Response $response, $args) {
    // Clear all session variables.
    session_unset();
    // Destroy the session.
    session_destroy();
    // Redirect to the login page.
    return $response->withHeader('Location', '/login')->withStatus(302);
});
