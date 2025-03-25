<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use Monolog\Logger;

session_start(); // Ensure sessions are started

// Function to calculate the payment amount
function calculatePaymentAmount($userId, $inputChildrenCount = null) {
    $user = DB::queryFirstRow("SELECT * FROM users WHERE id=%i AND isDeleted=0", $userId);
    if (!$user) {
        return ['error' => 'User not found'];
    }
    
    $childrenCount = ($inputChildrenCount !== null) ? max(1, (int)$inputChildrenCount) : max(1, count(DB::query("SELECT * FROM children WHERE parent_id=%i AND isDeleted=0", $userId)));
    $registrationFee = 100.00;
    $totalAmount = $registrationFee * $childrenCount;
    $totalAmountCents = (int)($totalAmount * 100);
    
    return [
        'baseRegistrationFee' => $registrationFee,
        'childrenCount' => $childrenCount,
        'totalAmount' => $totalAmount,
        'totalAmountCents' => $totalAmountCents,
        'lineItems' => [[
            "quantity" => $childrenCount,
            "price_data" => [
                "currency" => "cad",
                "unit_amount" => (int)($registrationFee * 100),
                "product_data" => ["name" => "Daycare Registration Fee"]
            ]
        ]]
    ];
}

$app->get('/payment', function (Request $request, Response $response) {
    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
        return $response->withHeader('Location', '/login')->withStatus(302);
    }
    $paymentDetails = calculatePaymentAmount($userId);
    return $this->get(Twig::class)->render($response, 'payment.html.twig', [
        'userId' => $userId,
        'paymentDetails' => $paymentDetails
    ]);
});

$app->post('/checkout', function (Request $request, Response $response) {
    $logger = $this->get(Logger::class);
    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
        return $response->withHeader('Location', '/login')->withStatus(302);
    }
    try {
        $data = $request->getParsedBody();
        $inputChildrenCount = isset($data['childCount']) ? (int)$data['childCount'] : null;
        $paymentDetails = calculatePaymentAmount($userId, $inputChildrenCount);
        $amountInCents = $paymentDetails['totalAmountCents'];
        
        DB::insert('payments', [
            'user_id' => $userId,
            'amount' => $paymentDetails['totalAmount'],
            'payment_date' => date('Y-m-d H:i:s'),
            'payment_status' => 'pending',
        ]);
        $paymentId = DB::insertId();
        $_SESSION['pending_payment_id'] = $paymentId;
        
        \Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);
        $checkout_session = \Stripe\Checkout\Session::create([
            "mode" => "payment",
            "success_url" => "http://" . $_SERVER['HTTP_HOST'] . "/payment-success",
            "cancel_url" => "http://" . $_SERVER['HTTP_HOST'] . "/payment?canceled=true",
            "client_reference_id" => $paymentId,
            "line_items" => $paymentDetails['lineItems'],
            "metadata" => [
                "payment_id" => $paymentId,
                "user_id" => $userId,
                "child_count" => $inputChildrenCount
            ]
        ]);
        
        $logger->info('Stripe checkout session created', [
            'session_id' => $checkout_session->id,
            'payment_id' => $paymentId,
            'user_id' => $userId,
            'child_count' => $inputChildrenCount
        ]);
        return $response->withHeader('Location', $checkout_session->url)->withStatus(303);
    } catch (\Exception $e) {
        $logger->error('Stripe checkout failed: ' . $e->getMessage());
        if (isset($paymentId)) {
            DB::update('payments', ['payment_status' => 'failed'], 'id=%i', $paymentId);
        }
        return $response->withHeader('Location', '/payment?error=payment_failed')->withStatus(303);
    }
});

$app->get('/payment-success', function (Request $request, Response $response) {
    $logger = $this->get(Logger::class);
    $userId = $_SESSION['user_id'] ?? null;
    $paymentId = $_SESSION['pending_payment_id'] ?? null;
    
    if ($paymentId && $userId) {
        DB::update('payments', ['payment_status' => 'completed'], 'id=%i', $paymentId);
        $logger->info('Payment marked as completed', ['payment_id' => $paymentId, 'user_id' => $userId]);
        unset($_SESSION['pending_payment_id']);
        return $this->get(Twig::class)->render($response, 'payment-success.html.twig');
    }
    return $response->withHeader('Location', '/payment?error=invalid_session')->withStatus(302);
});
