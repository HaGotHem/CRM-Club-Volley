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
        
        // Sécurité si l'API renvoie un tableau pour la date (déjà vu sur certaines API)
        if (is_array($date)) {
            $date = $date['date'] ?? $date[0] ?? date('Y-m-d H:i:s');
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
     * Formatte un billet à partir d'un participant.  
     */
    public function formatTicketFromParticipant(array $participant): array
    {
        // En mode "full", Weezevent retourne souvent les données dans un sous-objet participant ou directement
        $data = $participant['participant'] ?? $participant;
        
        // Tentative de récupération de l'ID du billet. Souvent id_ticket ou ticket_id.
        // Fallback sur l'ID du participant si c'est un billet unique par participant.
        $id = $data['id_ticket'] ?? $data['ticket_id'] ?? $data['id'] ?? null;
        
        // Récupération de l'ID de l'événement
        $eventId = $participant['id_event'] ?? $data['id_event'] ?? $participant['event_id'] ?? 0;

        $dateAchat = $data['date_achat'] ?? $data['order_date'] ?? $data['create_date'] ?? date('Y-m-d H:i:s');
        if (is_array($dateAchat)) {
            $dateAchat = $dateAchat['date'] ?? $dateAchat[0] ?? date('Y-m-d H:i:s');
        }

        return [
            'id'            => (int)($id ?? 0),
            'date_achat'    => (string)$dateAchat,
            'quantite'      => (int)($data['quantity'] ?? $data['quantite'] ?? 1),
            'montant_total' => (float)($data['amount'] ?? $data['montant'] ?? $data['price'] ?? $data['total'] ?? 0),
            'type_tarif'    => $data['ticket_category'] ?? $data['type_tarif'] ?? $data['ticket_name'] ?? '—',
            'code_promotionnel' => $data['promo_code'] ?? $data['code_promo'] ?? null,
            'origine'       => 'weezevent',
            'event_id'      => (int)$eventId,
            // Champs pour l'événement si on doit le créer à la volée
            'event_name'     => $participant['event_name'] ?? $participant['nom_evenement'] ?? 'Événement',
            'event_date'     => $participant['event_date'] ?? $participant['date_start'] ?? null,
            'event_location' => $participant['event_location'] ?? $participant['lieu'] ?? null,
        ];
    }
}