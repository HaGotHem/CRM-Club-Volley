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

$app->post('/api/segments/{id}/sync-brevo', function (Request $request, Response $response, array $args) {
    try {
        $segmentId = (int) $args['id'];
        $segmentRepo = new \App\Repositories\SegmentRepository();
        $contactRepo = new \App\Repositories\ContactRepository();
        $brevoService = new \App\Services\BrevoService();

        $segment = $segmentRepo->findById($segmentId);
        if (!$segment) {
            return jsonResponse($response, ['success' => false, 'error' => 'Segment introuvable'], 404);
        }

        $contacts = $contactRepo->findBySegmentId($segmentId);
        if (empty($contacts)) {
            return jsonResponse($response, ['success' => false, 'error' => 'Aucun contact dans ce segment'], 400);
        }

        // 1. Créer la liste sur Brevo si nécessaire
        $brevoListId = $segment->getBrevoId();
        if (!$brevoListId) {
            $brevoListId = $brevoService->createList($segment->getNomSegment());
            $segment->setBrevoId($brevoListId);
            $segmentRepo->save($segment);
        }

        // 2. Récupérer TOUS les contacts déjà présents dans la liste Brevo
        try {
            $existingEmails = $brevoService->getAllContactsFromList($brevoListId);
        } catch (\Exception $e) {
            $existingEmails = [];
        }

        // 3. Synchroniser chaque contact individuellement (création/mise à jour)
        $emailsToAdd = [];
        $successCount = 0;
        foreach ($contacts as $contact) {
            try {
                $brevoService->createOrUpdateContact($contact);
                $email = $contact->getEmail();
                
                // On n'ajoute que si l'email n'est pas déjà dans la liste Brevo
                if (!in_array($email, $existingEmails)) {
                    $emailsToAdd[] = $email;
                }
                $successCount++;
            } catch (\Exception $e) {
                // On continue même si un contact échoue
            }
        }

        // 4. Ajouter les nouveaux contacts à la liste Brevo par lots
        if (!empty($emailsToAdd)) {
            $chunks = array_chunk($emailsToAdd, 100);
            foreach ($chunks as $chunk) {
                try {
                    $brevoService->addContactsToList($brevoListId, $chunk);
                } catch (\Exception $e) {
                    // Si le lot échoue, on tente un par un pour isoler l'erreur
                    foreach ($chunk as $singleEmail) {
                        try {
                            $brevoService->addContactsToList($brevoListId, [$singleEmail]);
                        } catch (\Exception $e2) {
                            // Si ça échoue encore, c'est probablement un contact problématique
                            // On ignore pour ne pas bloquer tout le processus
                        }
                    }
                }
            }
        }

        return jsonResponse($response, [
            'success' => true,
            'message' => 'Synchronisation effectuée avec succès',
            'details' => [
                'total' => count($contacts),
                'synchronized' => $successCount,
                'brevo_list_id' => $brevoListId
            ]
        ]);
    } catch (\Throwable $e) {
        return jsonResponse($response, [
            'success' => false,
            'error' => 'Erreur lors de la synchronisation Brevo',
            'details' => $e->getMessage()
        ], 500);
    }
});

$app->delete('/api/segments/{id}', function (Request $request, Response $response, array $args) {
    try {
        $id = (int) $args['id'];
        $repository = new \App\Repositories\SegmentRepository();
        
        $segment = $repository->findById($id);
        if (!$segment) {
            return jsonResponse($response, [
                'success' => false,
                'error'   => 'Segment introuvable'
            ], 404);
        }

        $success = $repository->delete($id);

        if (!$success) {
            return jsonResponse($response, [
                'success' => false,
                'error'   => 'Impossible de supprimer le segment'
            ], 500);
        }

        return jsonResponse($response, [
            'success' => true,
            'message' => 'Segment supprimé avec succès (Dashboard uniquement)'
        ]);
    } catch (\Throwable $e) {
        return jsonResponse($response, [
            'success' => false,
            'error'   => 'Une erreur est survenue lors de la suppression',
            'details' => $e->getMessage()
        ], 500);
    }
});