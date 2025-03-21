<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use Ramsey\Uuid\Uuid; // For generating UUIDs
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/vendor/autoload.php';
require_once 'init.php';

// Function to send activation email using PHPMailer and Mailtrap
function sendActivationEmail($toEmail, $toName, $activationLink) {
    $mail = new PHPMailer(true);

    try {
        // Use SMTP
        $mail->isSMTP();
        // Set your Mailtrap SMTP server details (find these in your Mailtrap dashboard)
        $mail->Host       = 'sandbox.smtp.mailtrap.io';      // typically smtp.mailtrap.io
        $mail->SMTPAuth   = true;
        $mail->Username   = '5e6d6d4e086657';  // from Mailtrap
        $mail->Password   = 'b855d8085d5af3';  // from Mailtrap
        $mail->Port       = 2525;                     // or the port provided by Mailtrap (2525, 587, or 25)
        
        // Set sender and recipient details
        $mail->setFrom('no-reply@yourdomain.com', 'DayCare System');
        $mail->addAddress($toEmail, $toName);
        
        // Email content settings
        $mail->isHTML(true);
        $mail->Subject = 'Activate Your Account';
        $mail->Body    = "Click the following link to activate your account: <a href='{$activationLink}'>Activate Account</a>";
        $mail->AltBody = "Copy and paste this link in your browser to activate your account: {$activationLink}";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Activation email could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

/**
 * Helper function to validate ReCaptcha.
 * Replace 'YOUR_SECRET_KEY' with your actual ReCaptcha secret key.
 */
function verifyReCaptcha($recaptchaResponse) {
    // Load the secret key securely (you can also set this directly if needed)
    $secret = getenv('RECAPTCHA_SECRET') ?: '6LdiBfwqAAAAAKVUa71C5VQ3-uBA4YtEkF-Gfmdp';
    
    // If no response is provided, immediately fail.
    if (empty($recaptchaResponse)) {
        return false;
    }
    
    // Prepare POST data for the ReCaptcha verification
    $data = http_build_query([
        'secret'   => $secret,
        'response' => $recaptchaResponse,
        // 'remoteip' => $_SERVER['REMOTE_ADDR'], // Optionally include the client's IP
    ]);
    
    // Set up cURL options
    $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    // Execute the request // cURL Request function sends a POST request to Google's ReCaptcha verification API using cURL with the required parameters
    $response = curl_exec($ch);
    if(curl_errno($ch)){
        // Log error if necessary: curl_error($ch)
        curl_close($ch);
        return false;
    }
    curl_close($ch);
    
    // Decode the JSON response
    $responseData = json_decode($response);
    
    // Check if 'success' exists and is true.
    return isset($responseData->success) && $responseData->success;
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
    if (
        strlen($password) < 8 ||
        !preg_match('/[A-Z]/', $password) ||
        !preg_match('/[a-z]/', $password) ||
        !preg_match('/[0-9]/', $password) ||
        !preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)
    ) {
        $response->getBody()->write("Password must be at least 8 characters long and include at least one uppercase letter, one lowercase letter, one number, and one special character.");
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
    $recaptchaResponse = $data['g-recaptcha-response'] ?? ''; // The ?? '' operator ensures that if the field isn’t set (for example, if something went wrong on the client-side), $recaptchaResponse will be an empty string rather than causing an error.
    if (!verifyReCaptcha($recaptchaResponse)) {
        $response->getBody()->write("ReCaptcha verification failed.");
        return $response->withStatus(400); // A 400 status indicates that the client submitted a request that doesn't meet the required criteria—in this case, failing the ReCaptcha test.
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
        'profile_photo_path' => $filename, // This requires that your users table has this column.
        'created_at'        => date("Y-m-d H:i:s")
    ]);

    // Store the activation token in the DB (assuming a column named activation_token exists).
    DB::update('users', ['activation_token' => $activation_token], "email=%s", $email);

    // Send an activation email with the activation link.
    //$activation_link = "https://team4.fsd13.ca/activate?token=$activation_token";
    $activation_link = "http://daycaresystem.org:8080/activate?token=$activation_token";
   // In your registration route, replace the mail() call with:
    if(sendActivationEmail($email, $name, $activation_link)){
        $message = "Registration successful. Please check your email to activate your account.";
    } else {
        $message = "Registration successful, but activation email failed to send.";
    }

    $response->getBody()->write($message);
    return $response;
});

$app->get('/activate', function (Request $request, Response $response, $args) {
    // Retrieve the token from the query parameters
    $token = $request->getQueryParams()['token'] ?? null;
    
    if (!$token) {
        $response->getBody()->write("No activation token provided.");
        return $response->withStatus(400);
    }
    
    // Look up the user with the provided token and update activation_status
    $updated = DB::update('users', ['activation_status' => 1], "activation_token=%s", $token);
    
    if ($updated) {
        return $response->withHeader('Location', '/login')->withStatus(302);
    } else {
        $response->getBody()->write("Invalid or expired activation token.");
        return $response->withStatus(400);
    }
    
    return $response;
});

$app->get('/login', function (Request $request, Response $response, $args) {
    return $this->get(Twig::class)->render($response, 'login.html.twig');
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

  /*   // Validate ReCaptcha on login as well.
    $recaptchaResponse = $data['g-recaptcha-response'] ?? '';
    if (!verifyReCaptcha($recaptchaResponse)) {
        $response->getBody()->write("ReCaptcha verification failed.");
        return $response->withStatus(400);
    } */

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
        'is_admin' => $_SESSION['is_admin'] ?? false // null coalescing operator?? - If $_SESSION['is_admin'] is set and is not null, assign its value to $isAdmin.
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
