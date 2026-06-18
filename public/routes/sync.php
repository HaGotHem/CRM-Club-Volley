<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

use App\Models\Contact;
use App\Repositories\ContactRepository;
use App\Services\WeezeventService;
use App\Services\BrevoService;

$app->post('/api/sync/weezevent', function (Request $request, Response $response) {
    try {
        $weezevent  = new WeezeventService();
        $repository = new ContactRepository();

        $participants = $weezevent->getParticipants();

        $created = 0;
        $updated = 0;
        $errors  = 0;

        foreach ($participants as $participant) {
            try {
                $contactData = $weezevent->formatContact($participant);
                if (empty($contactData['email'])) continue;
                
                $contact = Contact::fromArray([
                    'nom'                    => $contactData['last_name'],
                    'prenom'                 => $contactData['first_name'],
                    'email'                  => $contactData['email'],
                    'phone'                  => $contactData['phone'] ?? null,
                    'source'                 => 'weezevent',
                    'date_creation'          => date('Y-m-d H:i:s'),
                    'consentement_marketing' => false
                ]);

                // Upsert + détection création/maj en UNE seule requête
                // (suppression du findByEmail exécuté pour chaque participant).
                if ($repository->upsertWithStatus($contact)) {
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
                'total_retrieved'  => count($participants),
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

$app->post('/api/sync/brevo', function (Request $request, Response $response) {
    try {
        $data      = (array) $request->getParsedBody();
        $segmentId = isset($data['segment']) ? (int)$data['segment'] : null;

        $repository = new ContactRepository();

        if ($segmentId) {
            $contacts = $repository->findBySegmentId($segmentId);
        } else {
            $contacts = $repository->findAll(1000, 0);
        }

        $brevo   = new BrevoService();
        $success = 0;
        $errors  = 0;
        $details = [];

        foreach ($contacts as $contact) {
            try {
                $brevo->createOrUpdateContact($contact);
                $success++;
            } catch (\Exception $e) {
                $errors++;
                $details[] = [
                    'email' => $contact->getEmail(),
                    'error' => $e->getMessage()
                ];
            }
        }

        return jsonResponse($response, [
            'success' => true,
            'message' => 'Synchronisation Brevo terminée',
            'segment' => $segmentId ?? 'tous',
            'data'    => [
                'success' => $success,
                'errors'  => $errors,
                'details' => $details
            ]
        ]);

    } catch (Throwable $e) {
        return jsonResponse($response, [
            'success' => false,
            'error'   => 'Erreur synchronisation Brevo',
            'details' => $e->getMessage()
        ], 500);
    }
});

$app->post('/api/sync/brevo/import', function (Request $request, Response $response) {
    try {
        $brevo      = new BrevoService();
        $repository = new ContactRepository();

        $created = 0;
        $updated = 0;
        $errors  = 0;
        $offset  = 0;
        $limit   = 500;
        $total   = 0;

        do {
            $result   = $brevo->getContacts($limit, $offset);
            $contacts = $result['contacts'] ?? [];
            $total    = $result['count'] ?? 0;

            foreach ($contacts as $brevoContact) {
                try {
                    $email = $brevoContact['email'] ?? '';
                    if (empty($email)) continue;

                    $attrs = $brevoContact['attributes'] ?? [];

                    $contact = Contact::fromArray([
                        'nom'                    => $attrs['LASTNAME']  ?? $attrs['NOM']    ?? 'Inconnu',
                        'prenom'                 => $attrs['FIRSTNAME'] ?? $attrs['PRENOM'] ?? 'Inconnu',
                        'email'                  => $email,
                        'phone'                  => $attrs['SMS']       ?? $attrs['TELEPHONE'] ?? null,
                        'source'                 => 'brevo',
                        'date_creation'          => date('Y-m-d H:i:s'),
                        'consentement_marketing' => true
                    ]);

                    // Upsert + détection création/maj en UNE seule requête
                    // (suppression du findByEmail exécuté pour chaque contact).
                    if ($repository->upsertWithStatus($contact)) {
                        $created++;
                    } else {
                        $updated++;
                    }

                } catch (\Exception $e) {
                    $errors++;
                }
            }

            $offset += $limit;

        } while (count($contacts) === $limit);

        return jsonResponse($response, [
            'success' => true,
            'message' => 'Import Brevo terminé',
            'data'    => [
                'total_brevo'      => $total,
                'contacts_created' => $created,
                'contacts_updated' => $updated,
                'errors'           => $errors
            ]
        ]);

    } catch (Throwable $e) {
        return jsonResponse($response, [
            'success' => false,
            'error'   => 'Erreur import Brevo',
            'details' => $e->getMessage()
        ], 500);
    }
});