<?php

declare(strict_types=1);

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

use App\Models\Contact;

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

    /**
     * Crée ou met à jour un contact dans Brevo.
     * 
     * @param Contact $contact
     * @return array
     */
    public function createOrUpdateContact(Contact $contact): array
    {
        try {
            $payload = [
                'email'         => $contact->getEmail(),
                'attributes'    => [
                    'FIRSTNAME' => $contact->getPrenom(),
                    'LASTNAME'  => $contact->getNom(),
                    'SMS'       => $contact->getPhone() ?? ''
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
            throw new \RuntimeException('Erreur Brevo : ' . $e->getMessage());
        }
    }

    /**
     * Crée une liste dans Brevo.
     */
    public function createList(string $name, ?int $folderId = null): int
    {
        if ($folderId === null) {
            $folderId = (int) ($_ENV['BREVO_FOLDER_ID'] ?? 4);
        }

        try {
            $response = $this->client->post('/v3/contacts/lists', [
                'json' => [
                    'name'     => $name,
                    'folderId' => $folderId
                ]
            ]);
            $body = json_decode((string) $response->getBody(), true);
            return (int) $body['id'];
        } catch (GuzzleException $e) {
            throw new \RuntimeException('Erreur création liste Brevo : ' . $e->getMessage());
        }
    }

    /**
     * Ajoute des contacts à une liste Brevo.
     */
    public function addContactsToList(int $listId, array $emails): array
    {
        try {
            $response = $this->client->post("/v3/contacts/lists/{$listId}/contacts/add", [
                'json' => [
                    'emails' => $emails
                ]
            ]);
            return json_decode((string) $response->getBody(), true);
        } catch (GuzzleException $e) {
            $errorBody = '';
            if (method_exists($e, 'getResponse') && $e->getResponse()) {
                $errorBody = (string) $e->getResponse()->getBody();
                $decoded = json_decode($errorBody, true);
                
                // Si l'erreur indique que des contacts sont déjà dans la liste, on peut tenter de continuer 
                // ou renvoyer une info structurée. Pour l'instant on garde l'exception mais avec plus d'infos.
                if (isset($decoded['code']) && $decoded['code'] === 'invalid_parameter') {
                     // On pourrait aussi logger ici
                }
            }
            throw new \RuntimeException('Erreur ajout contacts liste Brevo : ' . $e->getMessage() . ' | Response: ' . $errorBody);
        }
    }

    /**
     * Retire des contacts d'une liste Brevo.
     */
    public function removeContactsFromList(int $listId, array $emails): array
    {
        try {
            $response = $this->client->post("/v3/contacts/lists/{$listId}/contacts/remove", [
                'json' => [
                    'emails' => $emails
                ]
            ]);
            return json_decode((string) $response->getBody(), true);
        } catch (GuzzleException $e) {
            throw new \RuntimeException('Erreur retrait contacts liste Brevo : ' . $e->getMessage());
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
    public function getContacts(int $limit = 50, int $offset = 0, ?int $listId = null): array
    {
        try {
            $query = [
                'limit'  => $limit,
                'offset' => $offset,
                'sort'   => 'desc'
            ];

            if ($listId !== null) {
                $query['listIds'] = (string)$listId;
            }

            $response = $this->client->get('/v3/contacts', [
                'query' => $query
            ]);

            $body = json_decode((string) $response->getBody(), true);
            return $body ?? [];

        } catch (GuzzleException $e) {
            throw new \RuntimeException('Erreur récupération Brevo : ' . $e->getMessage());
        }
    }

    /**
     * Récupère les listes Brevo.
     */
    public function getLists(int $limit = 50, int $offset = 0): array
    {
        if ($limit > 50) $limit = 50;
        try {
            $response = $this->client->get('/v3/contacts/lists', [
                'query' => [
                    'limit'  => $limit,
                    'offset' => $offset,
                    'sort'   => 'desc'
                ]
            ]);

            $body = json_decode((string) $response->getBody(), true);
            return $body ?? [];
        } catch (GuzzleException $e) {
            throw new \RuntimeException('Erreur récupération listes Brevo : ' . $e->getMessage());
        }
    }

    /**
     * Récupère TOUS les contacts d'une liste spécifique en gérant la pagination.
     */
    public function getAllContactsFromList(int $listId): array
    {
        $allEmails = [];
        $offset = 0;
        $limit = 50;

        do {
            $data = $this->getContactsFromList($listId, $limit, $offset);
            if (isset($data['contacts']) && is_array($data['contacts'])) {
                foreach ($data['contacts'] as $c) {
                    $allEmails[] = $c['email'];
                }
                $offset += count($data['contacts']);
                $hasMore = count($data['contacts']) === $limit;
            } else {
                $hasMore = false;
            }
        } while ($hasMore);

        return $allEmails;
    }

    /**
     * Récupère les contacts d'une liste spécifique.
     */
    public function getContactsFromList(int $listId, int $limit = 50, int $offset = 0): array
    {
        try {
            $response = $this->client->get("/v3/contacts/lists/{$listId}/contacts", [
                'query' => [
                    'limit'  => $limit,
                    'offset' => $offset
                ]
            ]);

            $body = json_decode((string) $response->getBody(), true);
            return $body ?? [];
        } catch (GuzzleException $e) {
            throw new \RuntimeException('Erreur récupération contacts liste Brevo : ' . $e->getMessage());
        }
    }
}