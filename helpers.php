<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

// Retrieve Mailtrap or CPanel SMTP server details based on environment
/* function getMailConfig() {
    return [
        'env' => 'dev', // change to 'prod' for production

        'mailtrap' => [
            'host' => 'sandbox.smtp.mailtrap.io',
            'username' => '5e6d6d4e086657',
            'password' => 'b855d8085d5af3',
            'port' => 2525,
            'from_email' => 'no-reply@yourdomain.com',
            'from_name' => 'DayCare System'
        ],

        'cpanel' => [
            'host' => 'mail.team4.fsd13.ca', // CHECK YOUR CPanel HOST
            'username' => 'team4@team4.fsd13.ca',
            'password' => 'your_cpanel_email_password',
            'port' => 465,
            'encryption' => 'ssl', // or 'tls'
            'from_email' => 'team4@team4.fsd13.ca',
            'from_name' => 'DayCare System'
        ]
    ];
} */

/**
 * Send an activation email using PHPMailer and Mailtrap.
 *
 * @param string $toEmail Recipient's email address.
 * @param string $toName  Recipient's name.
 * @param string $activationLink Activation URL.
 * @return bool True on success, false otherwise.
 */

/* function sendActivationEmail($toEmail, $toName, $activationLink) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        // Mailtrap SMTP server details
        $mail->Host = 'sandbox.smtp.mailtrap.io';
        $mail->SMTPAuth = true;
        $mail->Username = '5e6d6d4e086657'; // Replace with your Mailtrap username
        $mail->Password = 'b855d8085d5af3'; // Replace with your Mailtrap password
        $mail->Port = 2525;
        
        $mail->setFrom('no-reply@yourdomain.com', 'DayCare System');
        $mail->addAddress($toEmail, $toName);
        
        $mail->isHTML(true);
        $mail->Subject = 'Activate Your Account';
        $mail->Body = "Click the following link to activate your account: <a href='{$activationLink}'>Activate Account</a>";
        $mail->AltBody = "Copy and paste this link in your browser to activate your account: {$activationLink}";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Activation email could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
} */

function sendEmail($toEmail, $toName, $subject, $htmlBody, $altBody)
{
    require_once __DIR__ . '/vendor/autoload.php';

    $env = $_ENV['APP_ENV'] ?? 'dev';
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();

        if ($env === 'prod') {
            $mail->Host       = $_ENV['MAIL_HOST_PROD'];
            $mail->Username   = $_ENV['MAIL_USERNAME_PROD'];
            $mail->Password   = $_ENV['MAIL_PASSWORD_PROD'];
            $mail->Port       = $_ENV['MAIL_PORT_PROD'];
            $mail->SMTPSecure = $_ENV['MAIL_ENCRYPTION_PROD'];
            $mail->setFrom($_ENV['MAIL_FROM_EMAIL_PROD'], $_ENV['MAIL_FROM_NAME_PROD']);
        } else {
            $mail->Host       = $_ENV['MAIL_HOST'];
            $mail->Username   = $_ENV['MAIL_USERNAME'];
            $mail->Password   = $_ENV['MAIL_PASSWORD'];
            $mail->Port       = $_ENV['MAIL_PORT'];
            $mail->SMTPSecure = 'tls';
            $mail->setFrom($_ENV['MAIL_FROM_EMAIL'], $_ENV['MAIL_FROM_NAME']);
        }

        $mail->SMTPAuth = true;
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject  = $subject;
        $mail->Body     = $htmlBody;
        $mail->AltBody  = $altBody;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

function sendActivationEmail($toEmail, $toName, $activationLink)
{
    $subject  = 'Activate Your Account';
    $htmlBody = "Click the following link to activate your account: <a href='{$activationLink}'>Activate Account</a>";
    $altBody  = "Copy and paste this link in your browser to activate your account: {$activationLink}";

    return sendEmail($toEmail, $toName, $subject, $htmlBody, $altBody);
}

function sendPasswordResetEmail($toEmail, $toName, $resetLink)
{
    $subject  = 'Reset Your Password';
    $htmlBody = "Click the following link to reset your password: <a href='{$resetLink}'>Reset Password</a>";
    $altBody  = "Copy and paste this link in your browser to reset your password: {$resetLink}";

    return sendEmail($toEmail, $toName, $subject, $htmlBody, $altBody);
}

/**
 * Verify reCAPTCHA response using Google API.
 *
 * @param string $recaptchaResponse The token from reCAPTCHA.
 * @return bool True if verification is successful.
 */
function verifyReCaptcha($recaptchaResponse)
{
    $secret = $_ENV['RECAPTCHA_SECRET'] ?? 'your-default-key-here';

    if (empty($recaptchaResponse)) {
        return false;
    }

    $response = file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => 'Content-type: application/x-www-form-urlencoded',
            'content' => http_build_query([
                'secret' => $secret,
                'response' => $recaptchaResponse
            ])
        ]
    ]));

    $result = json_decode($response);
    return $result && $result->success;
}

/**
 * Middleware factory to check if the user has the required role.
 *
 * @param string $requiredRole The role required to access a route.
 * @return Closure Middleware function.
 */
$checkRoleMiddleware = function ($requiredRole): callable {
    return function (Request $request, RequestHandler $handler) use ($requiredRole): Response {
        // Retrieve the container from the global scope
        global $container;
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== $requiredRole) {
            $flash = $container->get(\Slim\Flash\Messages::class);
            $flash->addMessage('error', 'Access denied. Please log in with appropriate credentials.');
            $response = new SlimResponse();
            return $response->withHeader('Location', '/login')->withStatus(302);
        }
        return $handler->handle($request);
    };
};

/**
 * Calculate age in years based on a given date of birth (YYYY-MM-DD).
 *
 * @param string $dateOfBirth The child's date of birth (e.g. '2018-04-15').
 * @return int Age in years.
 */
function calculateAge($dateOfBirth)
{
    if (empty($dateOfBirth)) {
        return 0;
    }
    // Create DateTime objects
    $today = new DateTime('today');
    $dob   = new DateTime($dateOfBirth);

    // Calculate the difference in years
    $age = $today->diff($dob)->y;
    return $age;
};
