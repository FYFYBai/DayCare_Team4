<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$app->get('/api/attendance-data', function (Request $request, Response $response, $args) {
    $sixMonthsAgo = date("Y-m-d", strtotime("-6 months"));
    $data = DB::query("SELECT registration_date,
                             SUM(status = 'present') AS present,
                             SUM(status = 'absent') AS absent
                        FROM registrations
                        WHERE registration_date >= %s
                        GROUP BY registration_date
                        ORDER BY registration_date ASC", $sixMonthsAgo);
    $response->getBody()->write(json_encode($data));
    return $response->withHeader('Content-Type', 'application/json');
});


$app->get('/api/child-attendance', function (Request $request, Response $response, $args) {
    $params = $request->getQueryParams();
    $childId = isset($params['child_id']) ? (int)$params['child_id'] : 0;
    $startDate = $params['start_date'] ?? date("Y-m-d", strtotime("-1 month"));
    $endDate = $params['end_date'] ?? date("Y-m-d");

    $data = DB::query("SELECT registration_date,
                             SUM(status = 'present') AS present,
                             SUM(status = 'absent') AS absent
                        FROM registrations
                        WHERE child_id = %i
                          AND registration_date BETWEEN %s AND %s
                        GROUP BY registration_date
                        ORDER BY registration_date ASC", $childId, $startDate, $endDate);
    
    $response->getBody()->write(json_encode($data));
    return $response->withHeader('Content-Type', 'application/json');
});
