<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

final class WeezeventService
{
    private Client $client;
    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = $_ENV['WEEZEVENT_API_KEY'] ?? '';
        $baseUrl = $_ENV['WEEZEVENT_BASE_URL'] ?? 'https://api.weezevent.com';

        $this->client = new Client([
            'base_uri' => $baseUrl,
            'timeout'  => 15,
            'headers'  => [
                'Accept' => 'application/json'
            ]
        ]);
    }

    public function getParticipants(): array
    {
        try {
            $response = $this->client->get('/participant/list', [
                'query' => [
                    'api_key'        => $this->apiKey,
                    'include_addon'  => 'false',
                    'full'           => 'true'
                ]
            ]);

            $body = json_decode((string) $response->getBody(), true);
            return $body['participants'] ?? [];

        } catch (GuzzleException $e) {
            throw new RuntimeException('Erreur Weezevent : ' . $e->getMessage());
        }
    }

    public function formatContact(array $participant): array
    {
        return [
            'first_name' => $participant['first_name'] ?? $participant['prenom'] ?? 'Inconnu',
            'last_name'  => $participant['last_name'] ?? $participant['nom'] ?? 'Inconnu',
            'email'      => $participant['email'] ?? '',
            'phone'      => $participant['phone'] ?? $participant['telephone'] ?? null,
            'source'     => 'weezevent'
        ];
    }
}