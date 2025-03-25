<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use Ramsey\Uuid\Uuid;
use Slim\Routing\RouteContext;

// Registration form (GET)
$app->get('/register', function (Request $request, Response $response, $args) {
    $flash = $this->get(\Slim\Flash\Messages::class);
    $messages = $flash->getMessages();

    $formData = $_SESSION['formData'] ?? [];
    unset($_SESSION['formData']); // Clear after use

    return $this->get(Twig::class)->render($response, 'register.html.twig', [
        'recaptcha_site_key' => $_ENV['RECAPTCHA_SITE_KEY'],
        'messages' => $messages,
        'formData' => $formData
    ]);
})->setName('register');

// Registration submission (POST)
$app->post('/register', function (Request $request, Response $response, $args) {
    $data = $request->getParsedBody();
    $_SESSION['formData'] = $data; // Save user input for repopulating form

    $flash = $this->get(\Slim\Flash\Messages::class);
    $router = RouteContext::fromRequest($request)->getRouteParser();

    // Validate required fields first
    if (empty($data['name']) || empty($data['email']) || empty($data['password']) || empty($data['role'])) {
        $flash->addMessage('error', "All fields (name, email, password, role) are required.");
        return $response->withHeader('Location', $router->urlFor('register'))->withStatus(302);
    }

    $name = trim($data['name']);
    $email = trim($data['email']);
    $password = $data['password'];
    $role = strtolower(trim($data['role']));

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $flash->addMessage('error', "Invalid email format.");
        return $response->withHeader('Location', $router->urlFor('register'))->withStatus(302);
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
        return $response->withHeader('Location', $router->urlFor('register'))->withStatus(302);
    }
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Validate role and block manager self-registration
    $allowedRoles = ['parent', 'educator', 'manager'];
    if (!in_array($role, $allowedRoles)) {
        $flash->addMessage('error', "Invalid role selected.");
        return $response->withHeader('Location', $router->urlFor('register'))->withStatus(302);
    }
    if ($role === 'manager') {
        $flash->addMessage('error', "Manager accounts must be added manually by an admin.");
        return $response->withHeader('Location', $router->urlFor('register'))->withStatus(302);
    }

    // Now validate reCAPTCHA after the basic validations
    $recaptchaResponse = $data['g-recaptcha-response'] ?? '';
    if (!verifyReCaptcha($recaptchaResponse)) {
        $flash->addMessage('error', "ReCaptcha verification failed. Please complete the reCAPTCHA challenge.");
        return $response->withHeader('Location', $router->urlFor('register'))->withStatus(302);
    }

    // Check if email already exists
    $exists = DB::queryFirstField("SELECT id FROM users WHERE email = %s AND isDeleted = 0", $email);
    if ($exists) {
        $flash->addMessage('error', "Email already registered.");
        return $response->withHeader('Location', $router->urlFor('register'))->withStatus(302);
    }

    // Handle profile photo upload for educators only (parents use default)
    $filename = 'default.png';
    $uploadedFiles = $request->getUploadedFiles();
    $profilePhoto = $uploadedFiles['profile_photo'] ?? null;
    if ($profilePhoto && $profilePhoto->getError() === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png'];
        if (!in_array($profilePhoto->getClientMediaType(), $allowedTypes)) {
            $flash->addMessage('error', "Invalid file type. Only JPEG and PNG allowed.");
            return $response->withHeader('Location', $router->urlFor('register'))->withStatus(302);
        }
        if ($profilePhoto->getSize() > (2 * 1024 * 1024)) {
            $flash->addMessage('error', "File size exceeds 2MB limit.");
            return $response->withHeader('Location', $router->urlFor('register'))->withStatus(302);
        }
        $filename = Uuid::uuid4()->toString() . '-' . $profilePhoto->getClientFilename();
        $profilePhoto->moveTo(__DIR__ . '/../uploads/' . $filename);
    }

    // Generate activation token
    $activation_token = bin2hex(random_bytes(32));

    // Insert user record
    DB::insert('users', [
        'name' => $name,
        'email' => $email,
        'password' => $hashedPassword,
        'role' => $role,
        'isAdmin' => 0,
        'activation_status' => 0,
        'isDeleted' => 0,
        'profile_photo_path' => $filename,
        'created_at' => date("Y-m-d H:i:s")
    ]);

    // Store activation token in DB
    DB::update('users', ['activation_token' => $activation_token], "email=%s", $email);

    // Build activation link (adjust domain/port as needed)
    $activation_link = "https://team4.fsd13.ca/activate?token=$activation_token";

    // Clear form data on successful registration
    unset($_SESSION['formData']);

    if (sendActivationEmail($email, $name, $activation_link)) {
        $devLink = "<br><strong>Dev/Test:</strong> <a href='{$activation_link}' target='_blank'>Click here to activate</a>"; // remove for production
        $flash->addMessage('success', "Registration successful. Please check your email to activate your account. $devLink");
    } else {
        $flash->addMessage('error', "Registered, but activation email failed to send.");
    }

    return $response->withHeader('Location', $router->urlFor('login'))->withStatus(302);
});

// Activation route (GET)
$app->get('/activate', function (Request $request, Response $response, $args) {
    $flash = $this->get(\Slim\Flash\Messages::class);
    $router = RouteContext::fromRequest($request)->getRouteParser();

    $token = $request->getQueryParams()['token'] ?? null;
    if (!$token) {
        $flash->addMessage('error', "No activation token provided.");
        return $response->withHeader('Location', $router->urlFor('login'))->withStatus(302);
    }
    $updated = DB::update('users', ['activation_status' => 1], "activation_token=%s", $token);
    if ($updated) {
        $flash->addMessage('success', "Account activated successfully. You can now log in.");
        return $response->withHeader('Location', $router->urlFor('login'))->withStatus(302);
    } else {
        $flash->addMessage('error', "Invalid or expired activation token.");
        return $response->withHeader('Location', $router->urlFor('login'))->withStatus(302);
    }
});


