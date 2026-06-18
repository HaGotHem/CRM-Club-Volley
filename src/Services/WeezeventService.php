<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\ConnectException;

class WeezeventService
{
    private Client $http;
    private string $baseUrl;
    private string $apiKey;
    private string $accessToken;

    public function __construct()
    {
        $this->http        = new Client(['timeout' => 15]);
        $this->baseUrl     = $_ENV['WEEZEVENT_BASE_URL']     ?: 'https://api.weezevent.com/events';
        $this->apiKey      = $_ENV['WEEZEVENT_API_KEY']      ?: '';
        $this->accessToken = $_ENV['WEEZEVENT_ACCESS_TOKEN'] ?: '';

        if (empty($this->apiKey)) {
            throw new \RuntimeException("WEEZEVENT_API_KEY manquant dans les variables d'environnement");
        }
        if (empty($this->accessToken)) {
            throw new \RuntimeException("WEEZEVENT_ACCESS_TOKEN manquant dans les variables d'environnement");
        }
    }

    private function call(string $method, string $endpoint, array $options = []): array
    {
        $options['query'] = array_merge($options['query'] ?? [], [
            'access_token' => $this->accessToken,
            'api_key'      => $this->apiKey,
        ]);

        try {
            $response = $this->http->request($method, "{$this->baseUrl}{$endpoint}", $options);

        } catch (ClientException $e) {
            $status = $e->getResponse()->getStatusCode();
            $body   = json_decode($e->getResponse()->getBody(), true);
            throw new \RuntimeException(
                $status === 401
                    ? "Token WeezEvent invalide ou expiré — mettre à jour WEEZEVENT_ACCESS_TOKEN dans .env"
                    : "Erreur WeezEvent {$status} : " . ($body['error']['message'] ?? $e->getMessage())
            );

        } catch (ServerException $e) {
            $body = (string) $e->getResponse()->getBody();
            throw new \RuntimeException(
                "WeezEvent indisponible ({$e->getResponse()->getStatusCode()}) : {$body}"
            );

        } catch (ConnectException $e) {
            throw new \RuntimeException("Timeout/réseau WeezEvent : " . $e->getMessage());
        }

        $data = json_decode($response->getBody(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("Réponse WeezEvent invalide (JSON malformé)");
        }

        return $data;
    }

    public function getEvents(): array
    {
        $data = $this->call('GET', '/events');
        return $data['events'] ?? [];
    }

    public function getEventTickets(int $eventId): array
    {
        $data      = $this->call('GET', '/tickets', ['query' => ['id_event' => $eventId]]);
        $eventData = $data['events'][0] ?? $data;
        return $this->extractTickets($eventData);
    }

    private function extractTickets(array $data): array
    {
        $tickets = $data['tickets'] ?? [];
        if (!empty($data['categories'])) {
            foreach ($data['categories'] as $category) {
                $tickets = array_merge($tickets, $this->extractTickets($category));
            }
        }
        return $tickets;
    }

    public function calcRevenue(array $tickets): float
    {
        return array_reduce($tickets, function (float $carry, array $t): float {
            return $carry + ((float)($t['price'] ?? 0) * (int)($t['participants'] ?? 0));
        }, 0.0);
    }

    public function getParticipants(?int $eventId = null): array
    {
        $query = [];
        if ($eventId !== null) {
            $query['id_event[]'] = $eventId;
        }

        $data = $this->call('GET', '/participant/list', ['query' => $query]);

        if (!empty($data['error'])) {
            throw new \RuntimeException($data['error']['message'] ?? "Erreur API participants");
        }

        return $data['participants'] ?? (is_array($data) ? $data : []);
    }

    public function formatContact(array $participant): array
    {
        $owner  = $participant['owner']  ?? $participant;
        $ticket = $participant['ticket'] ?? [];

        return [
            'last_name'  => $owner['last_name']  ?? $owner['nom']    ?? '',
            'first_name' => $owner['first_name'] ?? $owner['prenom'] ?? '',
            'email'      => strtolower(trim($owner['email'] ?? '')),
            'phone'      => $owner['phone']       ?? $owner['telephone'] ?? null,
            'ticket'     => [
                'name'       => $ticket['name']    ?? $ticket['libelle'] ?? null,
                'event_id'   => $participant['id_event']    ?? null,
                'created_at' => $participant['create_date'] ?? null,
                'cancelled'  => (bool)($participant['deleted'] ?? false),
            ],
        ];
    }
}