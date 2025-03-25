<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use Monolog\Logger;

// Payment route inspired by https://www.youtube.com/watch?v=1KxD8J8CAFg
$app->get('/payment', function (Request $request, Response $response) {
    // Get user ID from session
    $userId = $_SESSION['user_id'] ?? null;
    
    if (!$userId) {
        return $response->withHeader('Location', '/login')->withStatus(302);
    }
    
    // Check if user has any completed payments
    $existingPayment = DB::queryFirstRow("SELECT * FROM payments 
        WHERE user_id = %i 
        AND payment_status = 'completed' 
        AND isDeleted = 0", 
        $userId
    );
    
    if ($existingPayment) {
        // Redirect to dashboard with message that payment is already made
        $flash = $this->get(\Slim\Flash\Messages::class);
        $flash->addMessage('info', 'You have already completed the registration payment.');
        return $response->withHeader('Location', '/parent-dashboard')->withStatus(302);
    }
    
    // Calculate the payment amount
    $paymentDetails = calculatePaymentAmount($userId);
    
    // Try the simplified test template first
    return $this->get(Twig::class)->render($response, 'payment.html.twig', [
        'userId' => $userId,
        'paymentDetails' => $paymentDetails
    ]);
});

// Checkout
$app->post('/checkout', function (Request $request, Response $response, $args) use ($container) {
    $logger = $container->get(Logger::class);
    $userId = $_SESSION['user_id'] ?? null;
    
    if (!$userId) {
        return $response->withHeader('Location', '/login')->withStatus(302);
    }
    
    try {
        // number of children in the form
        $data = $request->getParsedBody();
        $inputChildrenCount = isset($data['childCount']) ? (int)$data['childCount'] : null;
        
        // Calculate the payment amount
        $paymentDetails = calculatePaymentAmount($userId, $inputChildrenCount);
        $amountInCents = $paymentDetails['totalAmountCents'];
        
        // Create a pending payment record in database
        $paymentId = DB::insert('payments', [
            'user_id' => $userId,
            'amount' => $paymentDetails['totalAmount'],
            'payment_date' => date('Y-m-d H:i:s'),
            'payment_status' => 'pending',
            'isDeleted' => 0,
            'child_count' => $inputChildrenCount,
            'stripe_session_id' => $checkout_session->id
        ]);
        
        // Store payment ID in session to retrieve it later
        $_SESSION['pending_payment_id'] = $paymentId;
        
        // Get Stripe keys from environment variables
        $stripe_secret_key = $_ENV['STRIPE_SECRET_KEY'];
        
        // Initialize Stripe with the secret key
        \Stripe\Stripe::setApiKey($stripe_secret_key);
        
        // Create a checkout session
        $checkout_session = \Stripe\Checkout\Session::create([
            "mode" => "payment",
            "success_url" => "http://" . $_SERVER['HTTP_HOST'] . "/payment-success",
            "cancel_url" => "http://" . $_SERVER['HTTP_HOST'] . "/payment?canceled=true",
            "locale" => "auto",
            "client_reference_id" => $paymentId, // Link Stripe session to our payment ID
            "line_items" => $paymentDetails['lineItems'],
            "metadata" => [
                "payment_id" => $paymentId,
                "user_id" => $userId,
                "child_count" => $inputChildrenCount
            ]
        ]);
        
        // Log the successful checkout creation
        $logger->info('Stripe checkout session created', [
            'session_id' => $checkout_session->id,
            'payment_id' => $paymentId,
            'user_id' => $userId,
            'child_count' => $inputChildrenCount
        ]);
        
        // Redirect to Stripe's checkout page
        return $response
            ->withHeader('Location', $checkout_session->url)
            ->withStatus(303);
            
    } catch (\Exception $e) {
        // Log the error
        $logger->error('Stripe checkout failed: ' . $e->getMessage());
        
        // If the payment was created, mark it as failed
        if (isset($paymentId)) {
            DB::update('payments', [
                'payment_status' => 'failed'
            ], 'id=%i', $paymentId);
        }
        
        // Redirect back to payment page with error
        return $response
            ->withHeader('Location', '/payment?error=payment_failed')
            ->withStatus(303);
    }
});

// Payment success page
$app->get('/payment-success', function (Request $request, Response $response) use ($container) {
    $logger = $container->get(Logger::class);
    $userId = $_SESSION['user_id'] ?? null;
    $paymentId = $_SESSION['pending_payment_id'] ?? null;
    
    if (!$paymentId || !$userId) {
        return $response->withHeader('Location', '/payment?error=invalid_session')->withStatus(302);
    }
    
    // Verify the payment exists and is pending
    $payment = DB::queryFirstRow("SELECT * FROM payments 
        WHERE id = %i 
        AND user_id = %i 
        AND payment_status = 'pending'
        AND isDeleted = 0", 
        $paymentId, $userId
    );
    
    if (!$payment) {
        return $response->withHeader('Location', '/payment?error=invalid_payment')->withStatus(302);
    }
    
    try {
        // Verify with Stripe
        \Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);
        $session = \Stripe\Checkout\Session::retrieve($payment['stripe_session_id']);
        
        if ($session->payment_status === 'paid') {
            // Update payment status
            DB::update('payments', [
                'payment_status' => 'completed',
                'payment_date' => date('Y-m-d H:i:s')
            ], "id=%i", $paymentId);
            
            // Clear the pending payment from session
            unset($_SESSION['pending_payment_id']);
            
            // Log success
            $logger->info('Payment completed successfully', [
                'payment_id' => $paymentId,
                'user_id' => $userId,
                'stripe_session_id' => $session->id
            ]);
            
            return $this->get(Twig::class)->render($response, 'payment-success.html.twig');
        } else {
            throw new Exception('Payment not completed');
        }
    } catch (Exception $e) {
        $logger->error('Payment verification failed: ' . $e->getMessage());
        return $response->withHeader('Location', '/payment?error=verification_failed')->withStatus(302);
    }
});

// fucntion to calculate the payment amount
function calculatePaymentAmount($userId, $inputChildrenCount = null) {
    // Get user information
    $user = DB::queryFirstRow("SELECT * FROM users WHERE id=%i AND isDeleted=0", $userId);
    
    // Determine child count (custom or from database)
    $childrenCount = 1; // Default to at least 1 child
    
    if ($inputChildrenCount !== null) {
        // Use inputted child count
        $childrenCount = max(1, (int)$inputChildrenCount); // Ensure at least 1
    } else {
        // Otherwise get actual children count from database
        $children = DB::query("SELECT * FROM children WHERE parent_id=%i AND isDeleted=0", $userId);
        $childrenCount = count($children) > 0 ? count($children) : 1; // At least 1
    }
    
    // Registration fee per child
    $registrationFee = 100.00;
    
    // Calculate total (multiply fee by the number of children)
    $totalAmount = $registrationFee * $childrenCount;
    $totalAmountCents = (int)($totalAmount * 100);
    
    // Prepare line items for Stripe
    $lineItems = [
        [
            "quantity" => $childrenCount,
            "price_data" => [
                "currency" => "cad",
                "unit_amount" => (int)($registrationFee * 100),
                "product_data" => [
                    "name" => "Daycare Registration Fee"
                ]
            ]
        ]
    ];
    
    return [
        'baseRegistrationFee' => $registrationFee,
        'childrenCount' => $childrenCount,
        'totalAmount' => $totalAmount,
        'totalAmountCents' => $totalAmountCents,
        'lineItems' => $lineItems
    ];
} 
