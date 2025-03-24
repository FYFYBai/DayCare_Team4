<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

/**
 * Send an activation email using PHPMailer and Mailtrap.
 *
 * @param string $toEmail Recipient's email address.
 * @param string $toName  Recipient's name.
 * @param string $activationLink Activation URL.
 * @return bool True on success, false otherwise.
 */
function sendActivationEmail($toEmail, $toName, $activationLink) {
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
}

/**
 * Verify reCAPTCHA response using Google API.
 *
 * @param string $recaptchaResponse The token from reCAPTCHA.
 * @return bool True if verification is successful.
 */
function verifyReCaptcha($recaptchaResponse) {
    $secret = getenv('RECAPTCHA_SECRET') ?: '6LdiBfwqAAAAAKVUa71C5VQ3-uBA4YtEkF-Gfmdp';
    if (empty($recaptchaResponse)) {
        return false;
    }
    $data = http_build_query([
        'secret' => $secret,
        'response' => $recaptchaResponse,
    ]);
    $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        curl_close($ch);
        return false;
    }
    curl_close($ch);
    $responseData = json_decode($response);
    return isset($responseData->success) && $responseData->success;
}

/**
 * Middleware factory to check if the user has the required role.
 *
 * @param string $requiredRole The role required to access a route.
 * @return Closure Middleware function.
 */
$checkRoleMiddleware = function ($requiredRole) {
    return function (Request $request, RequestHandler $handler) use ($requiredRole): Response {
        // Check if the session role is set and matches the required role.
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== $requiredRole) {
            $response = new Response();
            $response->getBody()->write("Access Denied.");
            return $response->withStatus(403);
        }
        // If role check passes, delegate processing to the next middleware/handler.
        return $handler->handle($request);
    };
};