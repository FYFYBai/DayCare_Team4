<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use Monolog\Logger;

// Payment route
$app->get('/payment', function (Request $request, Response $response) {
    // Get user ID from session
    $userId = $_SESSION['user_id'] ?? null;
    
    if (!$userId) {
        return $response->withHeader('Location', '/login')->withStatus(302);
    }
    
    // Calculate the payment amount
    $paymentDetails = calculatePaymentAmount($userId);
    
    $view = Twig::fromRequest($request);
    return $view->render($response, 'payment.html.twig', [
        'paymentDetails' => $paymentDetails
    ]);
});