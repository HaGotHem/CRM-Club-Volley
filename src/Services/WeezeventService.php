<?php

declare(strict_types=1);

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

final class WeezeventService
{
    private Client $client;
    private string $apiKey;
    private string $accessToken;

    public function __construct()
    {
        $this->apiKey = $_ENV['WEEZEVENT_API_KEY'] ?? '';
        $this->accessToken = $_ENV['WEEZEVENT_TOKEN'] ?? '';
        $baseUrl = $_ENV['WEEZEVENT_BASE_URL'] ?? 'https://api.weezevent.com';

        $this->client = new Client([
            'base_uri' => $baseUrl,
            'timeout'  => 30,
            'headers'  => [
                'Accept' => 'application/json'
            ]
        ]);
    }

    /**
     * Récupère les participants pour un événement spécifique.
     */
    public function getParticipants(int $eventId): array
    {
        try {
            $response = $this->client->get('/participant/list', [
                'query' => [
                    'api_key'        => $this->apiKey,
                    'access_token'   => $this->accessToken,
                    'id_event[]'     => $eventId,
                    'include_addon'  => 'false',
                    'full'           => 'true'
                ]
            ]);

            $body = json_decode((string) $response->getBody(), true);
            return $body['participants'] ?? $body ?? [];

        } catch (GuzzleException $e) {
            throw new \RuntimeException('Erreur Weezevent participants (event ' . $eventId . ') : ' . $e->getMessage());
        }
    }

    /**
     * Récupère les événements Weezevent.
     */
    public function getEvents(): array
    {
        try {
            $response = $this->client->get('/events', [
                'query' => [
                    'api_key'          => $this->apiKey,
                    'access_token'     => $this->accessToken,
                    'include_archived' => 'true', // Tentative d'inclusion des archives
                ]
            ]);
            $body = json_decode((string) $response->getBody(), true);
            return $body['events'] ?? $body['evenements'] ?? $body ?? [];
        } catch (GuzzleException $e) {
            throw new \RuntimeException('Erreur Weezevent (events) : ' . $e->getMessage());
        }
    }

    /**
     * Récupère les détails d'un événement spécifique.
     */
    public function getEventDetails(int $eventId): array
    {
        try {
            $response = $this->client->get('/event/' . $eventId, [
                'query' => [
                    'api_key'      => $this->apiKey,
                    'access_token' => $this->accessToken,
                ]
            ]);
            $body = json_decode((string) $response->getBody(), true);
            return $body['event'] ?? $body['evenement'] ?? $body ?? [];
        } catch (GuzzleException $e) {
            // On log ou on ignore selon la criticité
            return [];
        }
    }

    /**
     * Formatte un participant en structure contact
     */
    public function formatContact(array $participant): array
    {
        // En mode "full", les infos de l'acheteur sont souvent dans 'owner'
        // Si 'owner' n'est pas présent, on prend à la racine.
        $data = $participant['owner'] ?? $participant['participant'] ?? $participant;

        return [
            'first_name' => $data['first_name'] ?? $data['prenom'] ?? $data['first_name'] ?? 'Inconnu',
            'last_name'  => $data['last_name'] ?? $data['nom'] ?? $data['last_name'] ?? 'Inconnu',
            'email'      => $data['email'] ?? '',
            'phone'      => $data['phone'] ?? $data['telephone'] ?? null,
            'source'     => 'weezevent'
        ];
    }

    /**
     * Formatte un événement brut Weezevent vers notre schéma.
     */
    public function formatEvent(array $event): array
    {
        $date = $event['date_start'] ?? $event['date'] ?? date('Y-m-d H:i:s');

        // L'API Weezevent /events renvoie la date sous la forme { "start": "...", "end": "..." }.
        // On prend 'start' en priorité, puis quelques variantes par sécurité.
        if (is_array($date)) {
            $date = $date['start'] ?? $date['date'] ?? $date[0] ?? date('Y-m-d H:i:s');
        }

        return [
            'id'     => (int)($event['id'] ?? $event['id_event'] ?? 0),
            'nom'    => $event['name'] ?? $event['nom'] ?? 'Événement',
            'date'   => (string)$date,
            'lieu'   => $event['location'] ?? $event['lieu'] ?? ($event['city'] ?? '—'),
            'type'   => $event['type'] ?? '—',
            'saison' => $event['season'] ?? null,
        ];
    }

    /**
     * Récupère le catalogue de tarifs d'un événement (nom + prix par type de billet).
     * Le prix n'est pas présent dans /participant/list : il faut le lire ici.
     *
     * @return array<string, array{name: string, price: float}> indexé par id_ticket (type de tarif)
     */
    public function getTicketCatalog(int $eventId): array
    {
        try {
            $response = $this->client->get('/tickets', [
                'query' => [
                    'api_key'      => $this->apiKey,
                    'access_token' => $this->accessToken,
                    'id_event[]'   => $eventId,
                ]
            ]);
            $body = json_decode((string) $response->getBody(), true);

            $catalog = [];
            foreach (($body['events'] ?? []) as $event) {
                foreach (($event['tickets'] ?? []) as $ticket) {
                    $catalog[(string)($ticket['id'] ?? '')] = [
                        'name'  => (string)($ticket['name'] ?? '—'),
                        'price' => (float)($ticket['price'] ?? 0),
                    ];
                }
            }
            return $catalog;
        } catch (GuzzleException $e) {
            return [];
        }
    }

    /**
     * Formatte un billet à partir d'un participant.
     *
     * @param array $participant   Participant brut Weezevent
     * @param array $ticketCatalog Catalogue de tarifs de l'événement (voir getTicketCatalog)
     */
    public function formatTicketFromParticipant(array $participant, array $ticketCatalog = []): array
    {
        // En mode "full", Weezevent retourne souvent les données dans un sous-objet participant ou directement
        $data = $participant['participant'] ?? $participant;

        // Identifiant UNIQUE du billet = id_participant (un participant = un billet).
        // ATTENTION : id_ticket est le TYPE de tarif (partagé par tous les billets du même tarif) ;
        // l'utiliser comme identifiant écrasait tous les billets entre eux.
        $id = $data['id_participant'] ?? $data['id'] ?? null;

        // Type de tarif : sert de clé pour retrouver le prix et le libellé dans le catalogue.
        $idTicketType = (string)($data['id_ticket'] ?? $data['ticket_id'] ?? '');

        // Récupération de l'ID de l'événement
        $eventId = $participant['id_event'] ?? $data['id_event'] ?? $participant['event_id'] ?? 0;

        // Date d'achat réelle (create_date / transaction_date)
        $dateAchat = $data['create_date'] ?? $data['transaction_date'] ?? $data['date_achat'] ?? date('Y-m-d H:i:s');
        if (is_array($dateAchat)) {
            $dateAchat = $dateAchat['date'] ?? $dateAchat[0] ?? date('Y-m-d H:i:s');
        }

        // Origine Weezevent : 'web' (vente en ligne), 'invitation' (place offerte), 'guichet'...
        $origin = (string)($data['origin'] ?? 'weezevent');
        $isInvitation = stripos($origin, 'invitation') !== false;

        // Prix + libellé issus du catalogue de l'événement
        $catalog   = $ticketCatalog[$idTicketType] ?? null;
        $tarifName = $catalog['name'] ?? '—';
        $price     = (float)($catalog['price'] ?? 0);

        // Une invitation ne génère pas de recette : montant 0 et libellé préfixé "Invitation"
        // (ce préfixe permet aux statistiques de distinguer ventes payantes et invitations).
        $montant   = $isInvitation ? 0.0 : $price;
        $typeTarif = $isInvitation ? ('Invitation - ' . $tarifName) : $tarifName;

        return [
            'id'            => (int)($id ?? 0),
            'date_achat'    => (string)$dateAchat,
            'quantite'      => 1,
            'montant_total' => $montant,
            'type_tarif'    => $typeTarif,
            'code_promotionnel' => $data['promo_code'] ?? $data['code_promo'] ?? null,
            'origine'       => $origin,
            'event_id'      => (int)$eventId,
            // Champs pour l'événement si on doit le créer à la volée
            'event_name'     => $participant['event_name'] ?? $participant['nom_evenement'] ?? 'Événement',
            'event_date'     => $participant['event_date'] ?? $participant['date_start'] ?? null,
            'event_location' => $participant['event_location'] ?? $participant['lieu'] ?? null,
        ];
    }
}