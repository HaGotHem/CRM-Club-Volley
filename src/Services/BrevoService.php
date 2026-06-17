<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

final class BrevoService
{
    private Client $client;

    public function __construct()
    {
        $baseUrl = $_ENV['BREVO_BASE_URL'] ?? 'https://api.brevo.com/v3';
        $apiKey  = $_ENV['BREVO_API_KEY'] ?? '';

        $this->client = new Client([
            'base_uri' => $baseUrl,
            'timeout'  => 15,
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
                    'PRENOM'    => $contact['first_name'] ?? '',
                    'NOM'       => $contact['last_name'] ?? '',
                    'TELEPHONE' => $contact['phone'] ?? ''
                ],
                'updateEnabled' => true
            ];

            $response = $this->client->post('/contacts', [
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
}