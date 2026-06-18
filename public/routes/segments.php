<?php

declare(strict_types=1);

/** @var \Slim\App $app */

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

use App\Repositories\SegmentRepository;
use App\Repositories\ContactRepository;

$app->get('/api/segments', function (Request $request, Response $response) {
    try {
        $repository = new SegmentRepository();
        $segments = $repository->findAll();

        return jsonResponse($response, [
            'success' => true,
            'data'    => $segments
        ]);
    } catch (Throwable $e) {
        return jsonResponse($response, [
            'success' => false,
            'error'   => 'Impossible de récupérer les segments',
            'details' => $e->getMessage()
        ], 500);
    }
});

$app->get('/api/segments/{id}/contacts', function (Request $request, Response $response, array $args) {
    try {
        $segmentId = (int) $args['id'];
        $contactRepo = new ContactRepository();
        $segmentRepo = new SegmentRepository();

        $segment = $segmentRepo->findById($segmentId);
        if (!$segment) {
            return jsonResponse($response, [
                'success' => false,
                'error'   => 'Segment inconnu'
            ], 404);
        }

        $contacts = $contactRepo->findBySegmentId($segmentId);

        return jsonResponse($response, [
            'success' => true,
            'segment' => $segment->getNomSegment(),
            'data'    => $contacts
        ]);
    } catch (Throwable $e) {
        return jsonResponse($response, [
            'success' => false,
            'error'   => 'Impossible de récupérer les contacts du segment',
            'details' => $e->getMessage()
        ], 500);
    }
});

$app->post('/api/segments/create-from-visitors', function (Request $request, Response $response) {
    try {
        $data = json_decode($request->getBody()->getContents(), true);
        $segmentName = $data['name'] ?? null;
        $contactIds = $data['contact_ids'] ?? [];

        if (!$segmentName || empty($contactIds)) {
            return jsonResponse($response, [
                'success' => false,
                'error'   => 'Nom du segment ou liste de contacts manquante'
            ], 400);
        }

        $repository = new SegmentRepository();
        
        // Vérifier si le segment existe déjà
        $existing = $repository->findByName($segmentName);
        if ($existing) {
            $segmentId = $existing->getIdSegment();
        } else {
            // Créer le segment
            $segment = new App\Models\Segment(null, $segmentName, new DateTimeImmutable());
            $repository->save($segment);
            // Récupérer l'ID (on pourrait améliorer SegmentRepository::save pour retourner l'ID)
            $newSegment = $repository->findByName($segmentName);
            $segmentId = $newSegment->getIdSegment();
        }

        // Ajouter les contacts
        foreach ($contactIds as $id) {
            $repository->addContactToSegment((int)$id, $segmentId);
        }

        return jsonResponse($response, [
            'success' => true,
            'message' => 'Segment créé et contacts ajoutés avec succès',
            'segment_id' => $segmentId
        ]);
    } catch (Throwable $e) {
        return jsonResponse($response, [
            'success' => false,
            'error'   => 'Erreur lors de la création du segment',
            'details' => $e->getMessage()
        ], 500);
    }
});