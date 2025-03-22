<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use Ramsey\Uuid\Uuid;

// Registration form (GET)
$app->get('/register', function (Request $request, Response $response, $args) {
    return $this->get(Twig::class)->render($response, 'register.html.twig');
});

// Registration submission (POST)
$app->post('/register', function (Request $request, Response $response, $args) {
    $data = $request->getParsedBody();

    // Validate required fields
    if (empty($data['name']) || empty($data['email']) || empty($data['password']) || empty($data['role'])) {
        $response->getBody()->write("All fields (name, email, password, role) are required.");
        return $response->withStatus(400);
    }
    $name = trim($data['name']);
    $email = trim($data['email']);
    $password = $data['password'];
    $role = strtolower(trim($data['role']));

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response->getBody()->write("Invalid email format.");
        return $response->withStatus(400);
    }
    // Validate password strength
    if (strlen($password) < 8 ||
        !preg_match('/[A-Z]/', $password) ||
        !preg_match('/[a-z]/', $password) ||
        !preg_match('/[0-9]/', $password) ||
        !preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)
    ) {
        $response->getBody()->write("Password must be at least 8 characters long and include one uppercase letter, one lowercase letter, one number, and one special character.");
        return $response->withStatus(400);
    }
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Validate role and block manager self-registration
    $allowedRoles = ['parent', 'educator', 'manager'];
    if (!in_array($role, $allowedRoles)) {
        $response->getBody()->write("Invalid role selected.");
        return $response->withStatus(400);
    }
    if ($role === 'manager') {
        $response->getBody()->write("Manager accounts must be added manually by an admin.");
        return $response->withStatus(400);
    }

    // Validate reCAPTCHA (if using it in registration)
    $recaptchaResponse = $data['g-recaptcha-response'] ?? '';
    if (!verifyReCaptcha($recaptchaResponse)) {
        $response->getBody()->write("ReCaptcha verification failed.");
        return $response->withStatus(400);
    }

    // Check if email already exists
    $exists = DB::queryFirstField("SELECT id FROM users WHERE email = %s AND isDeleted = 0", $email);
    if ($exists) {
        $response->getBody()->write("Email already registered.");
        return $response->withStatus(400);
    }

    // Handle profile photo upload for educators (or managers) only
    $filename = 'default.png';
    if ($role !== 'parent') {
        $uploadedFiles = $request->getUploadedFiles();
        $profilePhoto = $uploadedFiles['profile_photo'] ?? null;
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
            $filename = Uuid::uuid4()->toString() . '-' . $profilePhoto->getClientFilename();
            $profilePhoto->moveTo(__DIR__ . '/../uploads/' . $filename);
        }
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
    $activation_link = "http://daycaresystem.org:8080/activate?token=$activation_token";
    // $activation_link = "https://team4.fsd13.ca/activate?token=$activation_token";

    // Send activation email using helper function
    if (sendActivationEmail($email, $name, $activation_link)) {
        $message = "Registration successful. Please check your email to activate your account.";
    } else {
        $message = "Registration successful, but activation email failed to send.";
    }
    $response->getBody()->write($message);
    return $response;
});

// Activation route (GET)
$app->get('/activate', function (Request $request, Response $response, $args) {
    $token = $request->getQueryParams()['token'] ?? null;
    if (!$token) {
        $response->getBody()->write("No activation token provided.");
        return $response->withStatus(400);
    }
    $updated = DB::update('users', ['activation_status' => 1], "activation_token=%s", $token);
    if ($updated) {
        // Redirect to login after successful activation
        return $response->withHeader('Location', '/login')->withStatus(302);
    } else {
        $response->getBody()->write("Invalid or expired activation token.");
        return $response->withStatus(400);
    }
});
