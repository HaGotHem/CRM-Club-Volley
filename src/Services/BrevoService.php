<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

final class BrevoService
{
    private Client $client;

public function __construct()
{
    $apiKey = $_ENV['BREVO_API_KEY'] ?? '';

    $this->client = new Client([
        'base_uri' => 'https://api.brevo.com',
        'timeout'  => 30,
        'verify'   => false,
        'headers'  => [
            'accept'       => 'application/json',
            'content-type' => 'application/json',
            'api-key'      => $apiKey
        ]
    ]);
}
public function createOrUpdateContact(array $contact): array
{
    try {
        $payload = [
            'email'         => $contact['email'],
            'attributes'    => [
                'FIRSTNAME' => $contact['first_name'] ?? '',
                'LASTNAME'  => $contact['last_name'] ?? '',
                'SMS'       => $contact['phone'] ?? ''
            ],
            'updateEnabled' => true
        ];

        $response = $this->client->post('/v3/contacts', [
    'json' => $payload
]);
        $body = (string) $response->getBody();

        return [
            'status_code' => $response->getStatusCode(),
            'body'        => $body !== '' ? json_decode($body, true) : null
        ];

    } catch (GuzzleException $e) {
        throw new RuntimeException('Erreur Brevo : ' . $e->getMessage());
    }
}
    public function syncSegment(array $contacts): array
    {
        $results = ['success' => 0, 'errors' => 0, 'details' => []];

        foreach ($contacts as $contact) {
            try {
                $this->createOrUpdateContact($contact);
                $results['success']++;
            } catch (\Exception $e) {
                $results['errors']++;
                $results['details'][] = [
                    'email' => $contact['email'],
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }
public function getContacts(int $limit = 500, int $offset = 0): array
{
    try {
        $response = $this->client->get('/v3/contacts', [
            'query' => [
                'limit'  => $limit,
                'offset' => $offset
            ]
        ]);

        $body = json_decode((string) $response->getBody(), true);
        return $body ?? [];

    } catch (GuzzleException $e) {
        throw new RuntimeException('Erreur récupération Brevo : ' . $e->getMessage());
    }
}
}