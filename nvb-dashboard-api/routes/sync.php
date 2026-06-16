<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// POST /api/sync/weezevent
$app->post('/api/sync/weezevent', function (Request $request, Response $response) {
    try {
        $weezevent  = new WeezeventService();
        $pdo        = Database::getConnection();
        $repository = new ContactRepository($pdo);

        $participants = $weezevent->getParticipants();

        $created = 0;
        $updated = 0;
        $errors  = 0;

        foreach ($participants as $participant) {
            try {
                $contact = $weezevent->formatContact($participant);

                if (empty($contact['email'])) {
                    continue;
                }

                $existing = $repository->findByEmail($contact['email']);

                if ($existing === null) {
                    $repository->create($contact);
                    $created++;
                } else {
                    $updated++;
                }
            } catch (\Exception $e) {
                $errors++;
            }
        }

        return jsonResponse($response, [
            'success' => true,
            'message' => 'Synchronisation Weezevent terminée',
            'data'    => [
                'total_retrieved' => count($participants),
                'contacts_created' => $created,
                'contacts_updated' => $updated,
                'errors'           => $errors
            ]
        ]);

    } catch (Throwable $e) {
        return jsonResponse($response, [
            'success' => false,
            'error'   => 'Erreur synchronisation Weezevent',
            'details' => $e->getMessage()
        ], 500);
    }
});

// POST /api/sync/brevo
$app->post('/api/sync/brevo', function (Request $request, Response $response) {
    try {
        $data      = (array) $request->getParsedBody();
        $segmentId = $data['segment'] ?? 'tous';

        $pdo = Database::getConnection();

        if ($segmentId === 'supporters-reguliers') {
            $contacts = $pdo->query("SELECT * FROM contacts WHERE source = 'weezevent'")->fetchAll();
        } elseif ($segmentId === 'nouveaux-visiteurs') {
            $contacts = $pdo->query("SELECT * FROM contacts WHERE created_at >= NOW() - INTERVAL '7 days'")->fetchAll();
        } else {
            $contacts = $pdo->query("SELECT * FROM contacts WHERE email IS NOT NULL")->fetchAll();
        }

        $brevo   = new BrevoService();
        $results = $brevo->syncSegment($contacts);

        return jsonResponse($response, [
            'success' => true,
            'message' => 'Synchronisation Brevo terminée',
            'segment' => $segmentId,
            'data'    => $results
        ]);

    } catch (Throwable $e) {
        return jsonResponse($response, [
            'success' => false,
            'error'   => 'Erreur synchronisation Brevo',
            'details' => $e->getMessage()
        ], 500);
    }
});