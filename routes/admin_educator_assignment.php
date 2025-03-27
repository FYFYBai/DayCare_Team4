<?php
// routes/admin_educator_child_list.php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\Twig;

$app->group('/admin', function (RouteCollectorProxy $group) {
    $group->get('/educator-child-list', function (Request $request, Response $response, array $args) {
        $params = $request->getQueryParams();
        $searchChild = trim($params['searchChild'] ?? '');
        $searchEducator = trim($params['searchEducator'] ?? '');
        $mode = 'default'; // default mode: no search
        $educatorData = [];
        $unassignedChildren = [];
        $childSearchResults = [];

        // Always fetch all educators for dropdowns
        $allEducators = DB::query("SELECT id, name FROM users WHERE role='educator' ORDER BY name ASC");

        if ($searchChild !== '') {
            $mode = 'childSearch';
            // Query children by name (partial match) and ensure child is not soft-deleted
            $childSearchResults = DB::query("
                SELECT c.*, u.name AS educator_name, u.id AS current_educator_id
                FROM children c
                LEFT JOIN users u ON c.educator_id = u.id
                WHERE c.name LIKE %s AND c.isDeleted = 0
                ORDER BY c.name ASC
            ", '%' . $searchChild . '%');
        } elseif ($searchEducator !== '') {
            $mode = 'educatorSearch';
            // Query educators by name (partial match)
            $educators = DB::query("
                SELECT id, name 
                FROM users 
                WHERE role='educator' AND name LIKE %s
                ORDER BY name ASC
            ", '%' . $searchEducator . '%');
            foreach ($educators as $ed) {
                $children = DB::query("
                    SELECT id, name, date_of_birth, educator_id
                    FROM children
                    WHERE educator_id = %i AND isDeleted = 0
                    ORDER BY name ASC
                ", $ed['id']);
                $educatorData[] = ['educator' => $ed, 'children' => $children];
            }
        } else {
            // Default view: show all educators and unassigned children.
            $educators = DB::query("
                SELECT id, name 
                FROM users 
                WHERE role='educator'
                ORDER BY name ASC
            ");
            foreach ($educators as $ed) {
                $children = DB::query("
                    SELECT id, name, date_of_birth, educator_id
                    FROM children
                    WHERE educator_id = %i AND isDeleted = 0
                    ORDER BY name ASC
                ", $ed['id']);
                $educatorData[] = ['educator' => $ed, 'children' => $children];
            }
            $unassignedChildren = DB::query("
                SELECT id, name, date_of_birth
                FROM children
                WHERE (educator_id IS NULL OR educator_id = 0) AND isDeleted = 0
                ORDER BY name ASC
            ");
        }

        return $this->get(Twig::class)->render($response, 'admin_educator_child_list.html.twig', [
            'mode'               => $mode,
            'searchChild'        => $searchChild,
            'searchEducator'     => $searchEducator,
            'educatorData'       => $educatorData,
            'unassignedChildren' => $unassignedChildren,
            'childSearchResults' => $childSearchResults,
            'allEducators'       => $allEducators
        ]);
    });

    $group->post('/educator-child-list/update', function (Request $request, Response $response, array $args) {
        $data = $request->getParsedBody();
        $childId = (int) ($data['child_id'] ?? 0);
        // If educator_id is blank, set to null (i.e. unassigned)
        $educatorId = (isset($data['educator_id']) && $data['educator_id'] !== '')
            ? (int) $data['educator_id']
            : null;
        if ($childId > 0) {
            DB::update('children', ['educator_id' => $educatorId], "id=%i", $childId);
        }
        // Preserve search parameters when redirecting back
        $qs = http_build_query([
            'searchChild' => $data['searchChild'] ?? '',
            'searchEducator' => $data['searchEducator'] ?? ''
        ]);
        return $response->withHeader('Location', '/admin/educator-child-list?' . $qs)->withStatus(302);
    });
})->add($checkRoleMiddleware('admin'));
