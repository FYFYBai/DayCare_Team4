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
    
    // Validate child count (between 1-10)
    $childrenCount = ($inputChildrenCount !== null) 
        ? max(1, min(10, (int)$inputChildrenCount)) 
        : max(1, count(DB::query("SELECT * FROM children WHERE parent_id=%i AND isDeleted=0", $userId)));
    
    // Hardcoded registration fee (if more time, I would have added a registration fee table in the database and store it there)
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
    
    // Check if user already has a completed payment
    $completedPayment = DB::queryFirstRow("SELECT * FROM payments 
        WHERE user_id=%i AND payment_status='completed' AND isDeleted=0", $userId);
    
    if ($completedPayment) {
        // Redirect to dashboard if already paid
        return $response->withHeader('Location', '/parent-dashboard')->withStatus(302);
    }
    
    $paymentDetails = calculatePaymentAmount($userId);
    return $this->get(Twig::class)->render($response, 'payment.html.twig', [
        'userId' => $userId,
        'paymentDetails' => $paymentDetails
    ]);
});

$app->post('/checkout', function (Request $request, Response $response) {
    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
        return $response->withHeader('Location', '/login')->withStatus(302);
    }
    
    try {
        // Check if user already has a completed payment
        $completedPayment = DB::queryFirstRow("SELECT * FROM payments 
            WHERE user_id=%i AND payment_status='completed' AND isDeleted=0", $userId);
        
        if ($completedPayment) {
            // Prevent duplicate payments
            return $response->withHeader('Location', '/parent-dashboard')->withStatus(302);
        }
        
        $data = $request->getParsedBody();
        $inputChildrenCount = isset($data['childCount']) ? (int)$data['childCount'] : null;
        
        // Validate child count
        if ($inputChildrenCount < 1 || $inputChildrenCount > 10) {
            return $response->withHeader('Location', '/payment?error=invalid_child_count')->withStatus(302);
        }
        
        $paymentDetails = calculatePaymentAmount($userId, $inputChildrenCount);
        
        // Create Stripe session
        \Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);
        $checkout_session = \Stripe\Checkout\Session::create([
            "mode" => "payment",
            "success_url" => "https://" . $_SERVER['HTTP_HOST'] . "/payment-success?session_id={CHECKOUT_SESSION_ID}",
            "cancel_url" => "https://" . $_SERVER['HTTP_HOST'] . "/payment?canceled=true",
            "line_items" => $paymentDetails['lineItems'],
            "metadata" => [
                "user_id" => $userId,
                "child_count" => $inputChildrenCount
            ]
        ]);
        
        // Create payment record with session ID
        DB::insert('payments', [
            'user_id' => $userId,
            'amount' => $paymentDetails['totalAmount'],
            'payment_date' => date('Y-m-d H:i:s'),
            'payment_status' => 'pending',
            'child_count' => $inputChildrenCount,
            'stripe_session_id' => $checkout_session->id
        ]);
        
        return $response->withHeader('Location', $checkout_session->url)->withStatus(303);
    } catch (\Exception $e) {
        return $response->withHeader('Location', '/payment?error=payment_failed')->withStatus(303);
    }
});

$app->get('/payment-success', function (Request $request, Response $response) {
    $userId = $_SESSION['user_id'] ?? null;
    
    if (!$userId) {
        return $response->withHeader('Location', '/login')->withStatus(302);
    }
    
    try {
        // Get the session ID from URL
        $session_id = $request->getQueryParams()['session_id'] ?? null;
        
        if (!$session_id) {
            throw new Exception('No session ID provided');
        }
        
        // Verify with Stripe
        \Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);
        $session = \Stripe\Checkout\Session::retrieve($session_id);
        
        // Get the payment record using session ID
        $payment = DB::queryFirstRow("SELECT * FROM payments 
            WHERE stripe_session_id = %s 
            AND user_id = %i 
            AND isDeleted = 0", 
            $session_id, $userId
        );
        
        if (!$payment) {
            throw new Exception('Payment record not found');
        }
        
        // Update payment status to completed
        DB::update('payments', [
            'payment_status' => 'completed',
            'payment_date' => date('Y-m-d H:i:s')
        ], "id=%i", $payment['id']);
        
        // Get updated payment record
        $updatedPayment = DB::queryFirstRow("SELECT * FROM payments WHERE id = %i", $payment['id']);
        
        return $this->get(Twig::class)->render($response, 'payment-success.html.twig', [
            'payment' => $updatedPayment
        ]);
        
    } catch (Exception $e) {
        return $response->withHeader('Location', '/payment?error=verification_failed')->withStatus(302);
    }
});