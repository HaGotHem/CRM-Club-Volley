<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// GET /api/contacts — liste tous les contacts (paginée)
$app->get('/api/contacts', function (Request $request, Response $response) {
    try {
        $queryParams = $request->getQueryParams();
        $page  = max(1, (int) ($queryParams['page'] ?? 1));
        $limit = max(1, min(1000, (int) ($queryParams['limit'] ?? 50)));
        $offset = ($page - 1) * $limit;

        $pdo = Database::getConnection();
        $repository = new ContactRepository($pdo);
        
        $contacts = $repository->findAll($limit, $offset);
        $total    = $repository->countAll();

        return jsonResponse($response, [
            'success' => true,
            'data'    => $contacts,
            'pagination' => [
                'current_page' => $page,
                'limit'        => $limit,
                'total_items'  => $total,
                'total_pages'  => ceil($total / $limit)
            ]
        ]);
    } catch (Throwable $e) {
        return jsonResponse($response, [
            'success' => false,
            'error'   => 'Impossible de récupérer les contacts',
            'details' => $e->getMessage()
        ], 500);
    }
});

// GET /api/contacts/{id} — détail d'un contact
$app->get('/api/contacts/{id}', function (Request $request, Response $response, array $args) {
    try {
        $id = (int) $args['id'];
        $pdo = Database::getConnection();
        $repository = new ContactRepository($pdo);
        $contact = $repository->findById($id);

        if ($contact === null) {
            return jsonResponse($response, [
                'success' => false,
                'error'   => 'Contact introuvable'
            ], 404);
        }

        return jsonResponse($response, [
            'success' => true,
            'data'    => $contact
        ]);
    } catch (Throwable $e) {
        return jsonResponse($response, [
            'success' => false,
            'error'   => 'Impossible de récupérer le contact',
            'details' => $e->getMessage()
        ], 500);
    }
});

// POST /api/contacts — créer un contact
$app->post('/api/contacts', function (Request $request, Response $response) {
    try {
        $data = (array) $request->getParsedBody();

        if (empty($data['first_name']) || empty($data['last_name']) || empty($data['email'])) {
            return jsonResponse($response, [
                'success' => false,
                'error'   => 'Les champs first_name, last_name et email sont obligatoires'
            ], 400);
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return jsonResponse($response, [
                'success' => false,
                'error'   => 'Adresse email invalide'
            ], 400);
        }

        $pdo = Database::getConnection();
        $repository = new ContactRepository($pdo);

        $existing = $repository->findByEmail($data['email']);
        if ($existing !== null) {
            return jsonResponse($response, [
                'success'          => false,
                'error'            => 'Un contact existe déjà avec cette adresse email',
                'existing_contact' => $existing
            ], 409);
        }

        $contact = $repository->create($data);

        return jsonResponse($response, [
            'success' => true,
            'message' => 'Contact créé',
            'data'    => $contact
        ], 201);
    } catch (Throwable $e) {
        return jsonResponse($response, [
            'success' => false,
            'error'   => 'Impossible de créer le contact',
            'details' => $e->getMessage()
        ], 500);
    }
});