<?php

use Psr\Http\Message\ResponseInterface as Response;

use Psr\Http\Message\ServerRequestInterface as Request;

use Slim\Views\Twig;



// Display the login form

$app->get('/login', function (Request $request, Response $response, $args) {

    return $this->get(Twig::class)->render($response, 'login.html.twig');

});



// Process login form submission

$app->post('/login', function (Request $request, Response $response, $args) {

    $data = $request->getParsedBody();

    

    // Validate required fields

    if (empty($data['email']) || empty($data['password'])) {

        $response->getBody()->write("Email and password are required.");

        return $response->withStatus(400);

    }

    

    $email = trim($data['email']);

    $password = $data['password'];

    

    // Validate email format

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $response->getBody()->write("Invalid email format.");

        return $response->withStatus(400);

    }

    

  /*   // (Optional: Validate reCAPTCHA if needed)

    $recaptchaResponse = $data['g-recaptcha-response'] ?? '';

    if (!verifyReCaptcha($recaptchaResponse)) {

        $response->getBody()->write("ReCaptcha verification failed.");

        return $response->withStatus(400);

    } */

    

    // Fetch user details from database

    $user = DB::queryFirstRow("SELECT * FROM users WHERE email = %s AND isDeleted = 0", $email);

    if (!$user || !password_verify($password, $user['password'])) {

        $response->getBody()->write("Invalid credentials.");

        return $response->withStatus(401);

    }

    

    // Ensure the account is activated

    if ($user['activation_status'] == 0) {

        $response->getBody()->write("Please activate your account via the email link.");

        return $response->withStatus(401);

    }

    

    // Set session variables for logged-in user

    $_SESSION['user_id']  = $user['id'];

    $_SESSION['role']     = $user['role'];

    $_SESSION['is_admin'] = $user['isAdmin'];

    

    // Redirect user based on role

    // You can adjust these routes as needed. For example, you might have different dashboards.
    switch ($user['role']) {

        case 'manager':
            $redirectUrl = '/dashboard'; // or a dedicated manager dashboard
            break;

        case 'educator':

            $redirectUrl = '/educator-dashboard';

            break;

        case 'parent':
        default:

            $redirectUrl = '/parent-dashboard';

            break;
    }
    
    return $response->withHeader('Location', $redirectUrl)->withStatus(302);

});



// Logout route

$app->get('/logout', function (Request $request, Response $response, $args) {

    // Clear session variables and destroy the session

    session_unset();

    session_destroy();

    

    // Redirect to the login page

    return $response->withHeader('Location', '/login')->withStatus(302);

});

