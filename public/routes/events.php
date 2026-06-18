<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

use App\Repositories\EvenementRepository;
use App\Repositories\ContactRepository;
use App\Repositories\BilletRepository;
use App\Services\WeezeventService;
use App\Models\Contact;

/**
 * Liste des événements fusionnés (DB + Weezevent)
 */
$app->get('/api/events', function (Request $request, Response $response) {
    try {
        $eventRepo = new EvenementRepository();
        $weezevent = new WeezeventService();

        // 1. Événements en DB
        $dbEvents = $eventRepo->findAllWithStats();
        $dbEventsMap = [];
        foreach ($dbEvents as $e) {
            $dbEventsMap[$e['idevenementweezevent']] = $e;
        }

        // 2. Événements Weezevent
        $weezeventEvents = $weezevent->getEvents();
        
        $merged = [];
        $weezeventIds = [];

        foreach ($weezeventEvents as $wEvent) {
            $wEventFormatted = $weezevent->formatEvent($wEvent);
            $id = $wEventFormatted['id'];
            $weezeventIds[] = $id;

            if (isset($dbEventsMap[$id])) {
                // Présent en DB
                $merged[] = array_merge($dbEventsMap[$id], [
                    'in_db' => true,
                    'nom' => $wEventFormatted['nom'], // On privilégie le nom Weezevent si mis à jour
                    'date' => $wEventFormatted['date'],
                    'sales_status' => $wEventFormatted['sales_status'] ?? null
                ]);
            } else {
                // Absent en DB
                $merged[] = array_merge($wEventFormatted, [
                    'in_db' => false,
                    'total_tickets' => 0
                ]);
            }
        }

        // 3. Ajouter les événements qui seraient en DB mais plus chez Weezevent (rare)
        foreach ($dbEvents as $e) {
            if (!in_array($e['idevenementweezevent'], $weezeventIds)) {
                $merged[] = array_merge($e, ['in_db' => true, 'archived_weezevent' => true]);
            }
        }

        // Détermination de la saison actuelle
        $now = new \DateTime();
        $currentMonth = (int)$now->format('n');
        $currentYear = (int)$now->format('Y');
        $currentSeasonStartYear = ($currentMonth >= 7) ? $currentYear : $currentYear - 1;

        // Groupement par saison
        $seasons = [];
        foreach ($merged as $evt) {
            $evtDate = new \DateTime($evt['date']);
            $evtMonth = (int)$evtDate->format('n');
            $evtYear = (int)$evtDate->format('Y');
            $seasonStartYear = ($evtMonth >= 7) ? $evtYear : $evtYear - 1;
            $seasonLabel = $seasonStartYear . '/' . ($seasonStartYear + 1);
            
            // Un événement est "en cours" si publié OU (saison actuelle ET en DB)
            $isPublished = (isset($evt['sales_status']['id_status']) && (int)$evt['sales_status']['id_status'] === 1);
            $isCurrentSeason = ($seasonStartYear === $currentSeasonStartYear);
            
            $evt['is_current'] = ($isPublished || ($isCurrentSeason && ($evt['in_db'] ?? false)));
            
            if (!isset($seasons[$seasonLabel])) {
                $seasons[$seasonLabel] = [];
            }
            $seasons[$seasonLabel][] = $evt;
        }

        // Tri des saisons (plus récente en premier)
        krsort($seasons);

        // Tri des événements dans chaque saison
        foreach ($seasons as &$sEvents) {
            usort($sEvents, function ($a, $b) {
                return strcmp($b['date'], $a['date']);
            });
        }

        // Si une saison spécifique est demandée
        $requestedSeason = $request->getQueryParams()['season'] ?? null;
        if ($requestedSeason) {
            return jsonResponse($response, [
                'success' => true,
                'data' => $seasons[$requestedSeason] ?? []
            ]);
        }

        // Sinon, on renvoie la saison en cours et la liste des saisons passées disponibles
        $currentSeasonLabel = $currentSeasonStartYear . '/' . ($currentSeasonStartYear + 1);
        
        $sections = [
            'current' => [],
            'past_seasons' => array_keys($seasons)
        ];

        // Pour la "Saison en cours", on prend tous les événements marqués is_current dans TOUTES les saisons
        // (En général ils sont dans la saison actuelle, mais un événement "Vente en cours" peut techniquement être vieux)
        foreach ($seasons as $label => $sEvents) {
            foreach ($sEvents as $evt) {
                if ($evt['is_current']) {
                    $sections['current'][] = $evt;
                }
            }
        }

        // Tri de la section current par date décroissante
        usort($sections['current'], function ($a, $b) {
            return strcmp($b['date'], $a['date']);
        });

        return jsonResponse($response, [
            'success' => true,
            'data' => $sections,
            'current_season' => $currentSeasonLabel
        ]);

    } catch (\Throwable $e) {
        return jsonResponse($response, [
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});

/**
 * Import d'un événement spécifique et de ses participants
 */
$app->post('/api/events/import/{id}', function (Request $request, Response $response, array $args) {
    try {
        $eventId = (int)$args['id'];
        if ($eventId <= 0) {
            throw new \Exception("ID d'événement invalide.");
        }

        $weezevent = new WeezeventService();
        $eventRepo = new EvenementRepository();
        $contactRepo = new ContactRepository();
        $billetRepo = new BilletRepository();

        // 1. Détails de l'événement
        $details = $weezevent->getEventDetails($eventId);
        if (empty($details)) {
            // Fallback sur la liste si le détail échoue
            $allEvents = $weezevent->getEvents();
            foreach ($allEvents as $e) {
                if ((int)($e['id'] ?? $e['id_event'] ?? 0) === $eventId) {
                    $details = $e;
                    break;
                }
            }
        }

        if (empty($details)) {
            throw new \Exception("Événement introuvable sur Weezevent.");
        }

        $eventFormatted = $weezevent->formatEvent($details);
        $eventRepo->save($eventFormatted);

        // 2. Participants
        $participants = $weezevent->getParticipants($eventId);
        if (isset($participants['id']) || isset($participants['participant'])) {
            $participants = [$participants];
        }

        $stats = [
            'contacts' => 0,
            'tickets' => 0,
            'errors' => 0
        ];

        foreach ($participants as $participant) {
            try {
                // Contact
                $c = $weezevent->formatContact($participant);
                if (empty($c['email'])) continue;

                $localContact = $contactRepo->findByEmail($c['email']);
                if ($localContact === null) {
                    $contact = Contact::fromArray([
                        'nom' => $c['last_name'] ?? 'Inconnu',
                        'prenom' => $c['first_name'] ?? 'Inconnu',
                        'email' => $c['email'],
                        'phone' => $c['phone'] ?? null,
                        'source' => 'weezevent',
                        'date_creation' => date('Y-m-d H:i:s'),
                        'consentement_marketing' => false
                    ]);
                    $contactRepo->save($contact);
                    $localContact = $contact;
                    $stats['contacts']++;
                }

                // Billet
                $ticketData = $weezevent->formatTicketFromParticipant($participant);
                if (!empty($ticketData['id'])) {
                    $billetRepo->save($ticketData);
                    $stats['tickets']++;

                    // Liaisons
                    $billetRepo->linkBilletEvenement((int)$ticketData['id'], $eventId);
                    if ($localContact && $localContact->getIdContact()) {
                        $billetRepo->linkContactBillet($localContact->getIdContact(), (int)$ticketData['id']);
                    }
                }
            } catch (\Throwable $e) {
                $stats['errors']++;
            }
        }

        return jsonResponse($response, [
            'success' => true,
            'message' => "Événement importé avec succès.",
            'stats' => $stats
        ]);

    } catch (\Throwable $e) {
        return jsonResponse($response, [
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});
