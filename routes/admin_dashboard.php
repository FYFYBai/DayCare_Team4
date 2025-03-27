<?php
// routes/admin_dashboard.php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\Twig;

$app->group('/admin', function (RouteCollectorProxy $group) {
    $group->get('/dashboard', function (Request $request, Response $response, array $args) {
        // Get the logged-in admin’s user record
        $userId = $_SESSION['user_id'] ?? null;
        $user = ['name' => 'Admin']; // fallback
        if ($userId) {
            $found = DB::queryFirstRow("SELECT name FROM users WHERE id=%i", $userId);
            if ($found) {
                $user['name'] = $found['name'];
            }
        }

        // Count total children and total educators
        $totalChildren  = DB::queryFirstField("SELECT COUNT(*) FROM children WHERE isDeleted=0");
        $totalEducators = DB::queryFirstField("SELECT COUNT(*) FROM users WHERE role='educator' AND isDeleted=0");

        // Query daily registration counts for the last 7 days
        $rows = DB::query("
            SELECT DATE(created_at) AS date, COUNT(*) AS cnt
            FROM users
            WHERE created_at >= CURDATE() - INTERVAL 7 DAY
            GROUP BY DATE(created_at)
            ORDER BY DATE(created_at) ASC
        ");

        // Build an array of the last 7 dates
        $dates = [];
        for ($i = 6; $i >= 0; $i--) {
            $dates[] = date("Y-m-d", strtotime("-{$i} days"));
        }

        // Default daily counts to 0, then fill from $rows
        $dailyCounts = array_fill_keys($dates, 0);
        foreach ($rows as $row) {
            $dailyCounts[$row['date']] = (int)$row['cnt'];
        }

        // Calculate cumulative totals
        $cumulativeCounts = [];
        $runningTotal = 0;
        foreach ($dates as $d) {
            $runningTotal += $dailyCounts[$d];
            $cumulativeCounts[] = $runningTotal;
        }

        // Render the dashboard
        return $this->get(Twig::class)->render($response, 'admin_dashboard.html.twig', [
            'userName'         => $user['name'],
            'totalChildren'    => $totalChildren,
            'totalEducators'   => $totalEducators,
            'cumulativeDates'  => $dates,
            'cumulativeCounts' => $cumulativeCounts
        ]);
    });
})->add($checkRoleMiddleware('admin'));
