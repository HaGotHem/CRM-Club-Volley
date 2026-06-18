<?php

declare(strict_types=1);

/** @var \Slim\App $app */

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

use App\Models\Contact;
use App\Models\Segment;
use App\Repositories\ContactRepository;
use App\Repositories\SegmentRepository;
use App\Repositories\EvenementRepository;
use App\Repositories\BilletRepository;
use App\Services\WeezeventService;
use App\Services\BrevoService;

$app->post('/api/sync/weezevent', function (Request $request, Response $response) {
    try {
        $weezevent  = new WeezeventService();
        $repository = new ContactRepository();

        $events = $weezevent->getEvents();
        $participants = [];
        foreach ($events as $event) {
            $eventId = (int)($event['id'] ?? $event['id_event'] ?? 0);
            if ($eventId > 0) {
                $evParticipants = $weezevent->getParticipants($eventId);
                // Si l'API renvoie un seul objet au lieu d'une liste (cas rare mais possible selon structure)
                if (isset($evParticipants['id']) || isset($evParticipants['participant'])) {
                    $participants[] = $evParticipants;
                } else {
                    $participants = array_merge($participants, $evParticipants);
                }
            }
        }

        $created = 0;
        $updated = 0;
        $errors  = 0;

        foreach ($participants as $participant) {
            try {
                $contactData = $weezevent->formatContact($participant);
                if (empty($contactData['email'])) continue;
                
                $existing = $repository->findByEmail($contactData['email']);

                if ($existing === null) {
                    $contact = Contact::fromArray([
                        'nom'                    => $contactData['last_name'],
                        'prenom'                 => $contactData['first_name'],
                        'email'                  => $contactData['email'],
                        'phone'                  => $contactData['phone'] ?? null,
                        'source'                 => 'weezevent',
                        'date_creation'          => date('Y-m-d H:i:s'),
                        'consentement_marketing' => false
                    ]);
                    $repository->save($contact);
                    $created++;
                } else {
                    // consolidation simple: patch non vides
                    if (!empty($contactData['last_name']) && $existing->getNom() !== $contactData['last_name']) {
                        $existing->setNom($contactData['last_name']);
                    }
                    if (!empty($contactData['first_name']) && $existing->getPrenom() !== $contactData['first_name']) {
                        $existing->setPrenom($contactData['first_name']);
                    }
                    if (!empty($contactData['phone'])) {
                        $existing->setPhone($contactData['phone']);
                    }
                    $repository->save($existing);
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

$app->get('/api/sync/weezevent/count', function (Request $request, Response $response) {
    try {
        $weezevent = new WeezeventService();
        $events = $weezevent->getEvents();
        $total = 0;
        foreach ($events as $event) {
            $eventId = (int)($event['id'] ?? $event['id_event'] ?? 0);
            if ($eventId > 0) {
                $evParticipants = $weezevent->getParticipants($eventId);
                if (isset($evParticipants['id']) || isset($evParticipants['participant'])) {
                    $total += 1;
                } else {
                    $total += count($evParticipants);
                }
            }
        }
        return jsonResponse($response, [
            'success' => true,
            'total'   => $total
        ]);
    } catch (Throwable $e) {
        return jsonResponse($response, [
            'success' => false,
            'error'   => 'Erreur comptage Weezevent',
            'details' => $e->getMessage()
        ], 500);
    }
});

$app->post('/api/sync/weezevent/import', function (Request $request, Response $response) {
    try {
        $weezevent   = new WeezeventService();
        $contactRepo = new ContactRepository();
        $eventRepo   = new EvenementRepository();
        $billetRepo  = new BilletRepository();

        $data   = (array)$request->getParsedBody();
        $offset = isset($data['offset']) ? (int)$data['offset'] : 0;
        $limit  = isset($data['limit'])  ? (int)$data['limit']  : 500;

        $events = $weezevent->getEvents();
        
        // 1) Phase initiale : synchronisation de TOUS les événements Weezevent
        $stats = [
            'contacts_created' => 0,
            'contacts_updated' => 0,
            'events_created'   => 0,
            'events_updated'   => 0,
            'tickets_created'  => 0,
            'tickets_updated'  => 0,
            'links_created'    => 0,
            'errors'           => 0,
        ];

        foreach ($events as $event) {
            $eventId = (int)($event['id'] ?? $event['id_event'] ?? 0);
            if ($eventId > 0) {
                $details = $weezevent->getEventDetails($eventId);
                $ev = $weezevent->formatEvent($details ?: $event);
                $existsEv = $eventRepo->exists($ev['id']);
                $eventRepo->save($ev);
                $stats[$existsEv ? 'events_updated' : 'events_created']++;
            }
        }

        $all = [];
        foreach ($events as $event) {
            $eventId = (int)($event['id'] ?? $event['id_event'] ?? 0);
            if ($eventId > 0) {
                $evParticipants = $weezevent->getParticipants($eventId);
                if (isset($evParticipants['id']) || isset($evParticipants['participant'])) {
                    $all[] = $evParticipants;
                } else {
                    $all = array_merge($all, $evParticipants);
                }
            }
        }
        $batch = array_slice($all, $offset, $limit);

        foreach ($batch as $participant) {
            try {
                // 1) Contact (acheteur/destinataire)
                $c = $weezevent->formatContact($participant);
                $localContact = null;
                if (!empty($c['email'])) {
                    $existing = $contactRepo->findByEmail($c['email']);
                    if ($existing === null) {
                        $contact = Contact::fromArray([
                            'nom'                    => $c['last_name'] ?? 'Inconnu',
                            'prenom'                 => $c['first_name'] ?? 'Inconnu',
                            'email'                  => $c['email'],
                            'phone'                  => $c['phone'] ?? null,
                            'source'                 => 'weezevent',
                            'date_creation'          => date('Y-m-d H:i:s'),
                            'consentement_marketing' => false
                        ]);
                        $contactRepo->save($contact);
                        $stats['contacts_created']++;
                        $localContact = $contact;
                    } else {
                        if (!empty($c['last_name']) && $c['last_name'] !== 'Inconnu') { $existing->setNom($c['last_name']); }
                        if (!empty($c['first_name']) && $c['first_name'] !== 'Inconnu') { $existing->setPrenom($c['first_name']); }
                        if (!empty($c['phone']))      { $existing->setPhone($c['phone']); }
                        $contactRepo->save($existing);
                        $stats['contacts_updated']++;
                        $localContact = $existing;
                    }
                }

                // 2) Billet + liaisons
                $ticketData = $weezevent->formatTicketFromParticipant($participant);
                $eventId = (int)($ticketData['event_id'] ?? 0);
                
                if (!empty($ticketData['id'])) {
                    $existsT = $billetRepo->exists((int)$ticketData['id']);
                    $billetRepo->save($ticketData);
                    $stats[$existsT ? 'tickets_updated' : 'tickets_created']++;
                    
                    // Liaison Billet <-> Evenement
                    if ($eventId > 0) {
                        if ($billetRepo->linkBilletEvenement((int)$ticketData['id'], $eventId)) {
                            $stats['links_created']++;
                        }
                    }
                    
                    // Liaison Contact <-> Billet
                    if ($localContact && $localContact->getIdContact()) {
                        if ($billetRepo->linkContactBillet($localContact->getIdContact(), (int)$ticketData['id'])) {
                            $stats['links_created']++;
                        }
                    }
                }
            } catch (\Throwable $e) {
                $stats['errors']++;
            }
        }

        return jsonResponse($response, [
            'success' => true,
            'message' => 'Lot Weezevent traité',
            'data'    => $stats
        ]);
    } catch (Throwable $e) {
        return jsonResponse($response, [
            'success' => false,
            'error'   => 'Erreur import Weezevent',
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

$app->get('/api/sync/brevo/count', function (Request $request, Response $response) {
    try {
        $brevo = new BrevoService();
        $result = $brevo->getContacts(1, 0); // Récupère le compte via le premier contact
        
        return jsonResponse($response, [
            'success' => true,
            'total' => $result['count'] ?? 0
        ]);
    } catch (Throwable $e) {
        return jsonResponse($response, [
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});

$app->post('/api/sync/brevo/import', function (Request $request, Response $response) {
    try {
        $brevo       = new BrevoService();
        $contactRepo = new ContactRepository();
        $segmentRepo = new SegmentRepository();

        $data = (array) $request->getParsedBody();
        $offset = isset($data['offset']) ? (int)$data['offset'] : null;
        $limit  = isset($data['limit']) ? (int)$data['limit'] : 100;

        $stats = [
            'lists_synced'     => 0,
            'contacts_created' => 0,
            'contacts_updated' => 0,
            'links_created'    => 0,
            'errors'           => 0
        ];

        // 1. Synchroniser les listes (toujours nécessaire pour le mapping, mais rapide)
        $brevoLists = [];
        $listsOffset = 0;
        do {
            $brevoListsData = $brevo->getLists(50, $listsOffset);
            $batch = $brevoListsData['lists'] ?? [];
            $brevoLists = array_merge($brevoLists, $batch);
            $listsOffset += 50;
        } while (count($batch) === 50);
        
        $segmentMap = []; // Map Brevo List ID => Local Segment ID

        foreach ($brevoLists as $list) {
            $existingSegment = $segmentRepo->findByName($list['name']);
            if (!$existingSegment) {
                $segment = new Segment(null, $list['name'], new DateTimeImmutable());
                $segmentRepo->save($segment);
                $existingSegment = $segmentRepo->findByName($list['name']);
            }
            if ($existingSegment) {
                $segmentMap[$list['id']] = $existingSegment->getIdSegment();
                $stats['lists_synced']++;
            }
        }

        // 2. Synchroniser les contacts (par lot si offset précisé, sinon boucle complète)
        if ($offset !== null) {
            $result   = $brevo->getContacts($limit, $offset);
            $contacts = $result['contacts'] ?? [];
            
            foreach ($contacts as $brevoContact) {
                processBrevoContact($brevoContact, $contactRepo, $segmentRepo, $segmentMap, $stats);
            }
        } else {
            // Mode fallback : boucle complète si pas d'offset (ancien comportement)
            $currentOffset = 0;
            do {
                $result   = $brevo->getContacts($limit, $currentOffset);
                $contacts = $result['contacts'] ?? [];
                
                foreach ($contacts as $brevoContact) {
                    processBrevoContact($brevoContact, $contactRepo, $segmentRepo, $segmentMap, $stats);
                }
                $currentOffset += $limit;
            } while (count($contacts) === $limit);
        }

        return jsonResponse($response, [
            'success' => true,
            'message' => 'Lot synchronisé',
            'data'    => $stats
        ]);

    } catch (Throwable $e) {
        return jsonResponse($response, [
            'success' => false,
            'error'   => 'Erreur synchronisation Brevo',
            'details' => $e->getMessage()
        ], 500);
    }
});

/**
 * Fonction helper pour traiter un contact Brevo
 */
function processBrevoContact($brevoContact, $contactRepo, $segmentRepo, $segmentMap, &$stats) {
    try {
        $email = $brevoContact['email'] ?? '';
        if (empty($email)) return;

        $attrs = $brevoContact['attributes'] ?? [];
        $existing = $contactRepo->findByEmail($email);

        if ($existing === null) {
            // Création
            $stats['contacts_created']++;
            $contact = Contact::fromArray([
                'nom'                    => $attrs['LASTNAME']  ?? $attrs['NOM']    ?? 'Inconnu',
                'prenom'                 => $attrs['FIRSTNAME'] ?? $attrs['PRENOM'] ?? 'Inconnu',
                'email'                  => $email,
                'phone'                  => $attrs['SMS']       ?? $attrs['TELEPHONE'] ?? null,
                'source'                 => 'brevo',
                'date_creation'          => date('Y-m-d H:i:s'),
                'consentement_marketing' => true
            ]);
            $contactRepo->save($contact);
        } else {
            // Mise à jour consolidée (patch)
            $stats['contacts_updated']++;
            
            // On ne met à jour que si les données Brevo sont présentes et non vides
            $newNom    = $attrs['LASTNAME']  ?? $attrs['NOM']    ?? null;
            $newPrenom = $attrs['FIRSTNAME'] ?? $attrs['PRENOM'] ?? null;
            $newPhone  = $attrs['SMS']       ?? $attrs['TELEPHONE'] ?? null;

            if (!empty($newNom) && $newNom !== 'Inconnu') {
                $existing->setNom($newNom);
            }
            if (!empty($newPrenom) && $newPrenom !== 'Inconnu') {
                $existing->setPrenom($newPrenom);
            }
            if (!empty($newPhone)) {
                $existing->setPhone($newPhone);
            }
            
            // On garde la source d'origine si elle existe déjà, ou on peut marquer la synchro
            $contactRepo->save($existing);
        }
        
        // Récupérer le contact (re-fetch pour être sûr d'avoir l'ID en cas de création)
        $localContact = $existing ?? $contactRepo->findByEmail($email);
        if ($localContact) {
            // Nettoyer les anciens segments pour ce contact avant de remettre les nouveaux
            $segmentRepo->removeAllSegmentsFromContact($localContact->getIdContact());

            // Lier aux segments
            $listIds = $brevoContact['listIds'] ?? [];
            foreach ($listIds as $bListId) {
                if (isset($segmentMap[$bListId])) {
                    if ($segmentRepo->addContactToSegment($localContact->getIdContact(), $segmentMap[$bListId])) {
                        $stats['links_created']++;
                    }
                }
            }
        }
    } catch (\Exception $e) {
        $stats['errors']++;
    }
}