<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

use App\Repositories\ContactRepository;
use App\Models\Contact;

// GET /api/contacts — liste tous les contacts (paginée)
$app->get('/api/contacts', function (Request $request, Response $response) {
    try {
        $queryParams = $request->getQueryParams();
        $page  = max(1, (int) ($queryParams['page'] ?? 1));
        $limit = max(1, min(1000, (int) ($queryParams['limit'] ?? 50)));
        $offset = ($page - 1) * $limit;
        
        $segmentId = isset($queryParams['listId']) ? (int)$queryParams['listId'] : null;
        $search = $queryParams['search'] ?? null;

        $repository = new ContactRepository();
        
        if ($segmentId) {
            $contacts = $repository->findBySegmentId($segmentId, $search);
            // findBySegmentId n'est pas encore paginé dans le repo, on va le faire ici ou l'améliorer
            // Pour l'instant, on fait simple pour correspondre à l'usage
            $total = count($contacts);
            // Simulation pagination sur le résultat pour l'instant
            $contacts = array_slice($contacts, $offset, $limit);
        } else {
            $contacts = $repository->findAll($limit, $offset, $search);
            $total    = $repository->countAll($search);
        }

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
        $repository = new ContactRepository();
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

// GET /api/brevo/contacts — liste les contacts Brevo
$app->get('/api/brevo/contacts', function (Request $request, Response $response) {
    try {
        $queryParams = $request->getQueryParams();
        $page  = max(1, (int) ($queryParams['page'] ?? 1));
        $limit = max(1, min(100, (int) ($queryParams['limit'] ?? 50)));
        $listId = isset($queryParams['listId']) ? (int)$queryParams['listId'] : null;
        $offset = ($page - 1) * $limit;

        $service = new \App\Services\BrevoService();
        $data = $service->getContacts($limit, $offset, $listId);

        $total = $data['count'] ?? 0;
        $contacts = $data['contacts'] ?? [];

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
            'error'   => 'Impossible de récupérer les contacts Brevo',
            'details' => $e->getMessage()
        ], 500);
    }
});

// GET /api/brevo/lists — liste les listes Brevo
$app->get('/api/brevo/lists', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\BrevoService();
        $data = $service->getLists();

        return jsonResponse($response, [
            'success' => true,
            'data'    => $data['lists'] ?? []
        ]);
    } catch (Throwable $e) {
        return jsonResponse($response, [
            'success' => false,
            'error'   => 'Impossible de récupérer les listes Brevo',
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

        $repository = new ContactRepository();

        $existing = $repository->findByEmail($data['email']);
        if ($existing !== null) {
            return jsonResponse($response, [
                'success'          => false,
                'error'            => 'Un contact existe déjà avec cette adresse email',
                'existing_contact' => $existing
            ], 409);
        }

        // Utilisation du modèle Contact et de la méthode save du repository
        $contact = Contact::fromArray([
            'nom'                    => $data['last_name'],
            'prenom'                 => $data['first_name'],
            'email'                  => $data['email'],
            'phone'                  => $data['phone'] ?? null,
            'source'                 => 'manual',
            'date_creation'          => date('Y-m-d H:i:s'),
            'consentement_marketing' => false
        ]);

        $repository->save($contact);
        
        // On récupère l'objet fraîchement créé (ou mis à jour via le ON CONFLICT)
        $contact = $repository->findByEmail($data['email']);

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