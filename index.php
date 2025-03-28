<?php

use Dom\ChildNode;
// Initialize the application (session, container, logging, etc.)
require_once __DIR__ . '/init.php';

// Load helper functions and middleware
require_once __DIR__ . '/helpers.php';

// Modularize routes by requiring individual route files
require_once __DIR__ . '/routes/home.php';
require_once __DIR__ . '/routes/registration.php';
require_once __DIR__ . '/routes/auth.php';
require_once __DIR__ . '/routes/password_reset.php';
require_once __DIR__ . '/routes/events.php';
require_once __DIR__ . '/routes/dashboard.php';
require_once __DIR__ . '/routes/educator_dashboard.php';
require_once __DIR__ . '/routes/educator_attendance.php';
require_once __DIR__ . '/routes/educator_profile.php';
require_once __DIR__ . '/routes/api.php';
require_once __DIR__ . '/routes/parent_dashboard.php'; 
require_once __DIR__ . '/routes/payments.php';
require_once __DIR__ . '/routes/child_management.php';
require_once __DIR__ . '/routes/admin_user_management.php';
require_once __DIR__ . '/routes/admin_dashboard.php';
require_once __DIR__ . '/routes/manager_dashboard.php';
require_once __DIR__ . '/routes/admin_educator_assignment.php';


// You can include other route modules (e.g., admin routes, testing routes) here

// Run the Slim application to process HTTP requests
$app->run();



// TODO: detect deleted child, make webcame triggered by a button and add the feature to edit child