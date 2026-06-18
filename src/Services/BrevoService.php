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
     * @param Contact     $contact
     * @param int[]|null  $listIds Listes Brevo auxquelles rattacher le contact
     * @return array
     */
    public function createOrUpdateContact(Contact $contact, ?array $listIds = null): array
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

            if (!empty($listIds)) {
                $payload['listIds'] = array_values(array_map('intval', $listIds));
            }

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
     * Crée une liste dans Brevo et renvoie son identifiant.
     */
    public function createList(string $name, ?int $folderId = null): int
    {
        if ($folderId === null) {
            // Dossier défini en .env, sinon dossier « CRM Volley » créé automatiquement
            $folderId = isset($_ENV['BREVO_FOLDER_ID'])
                ? (int) $_ENV['BREVO_FOLDER_ID']
                : $this->getOrCreateFolderId();
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

    /**
     * Import en masse de contacts dans Brevo (traitement asynchrone côté Brevo).
     * Bien plus rapide qu'un appel createOrUpdateContact par contact : un seul appel par lot,
     * indispensable pour les gros segments (sinon timeout HTTP).
     *
     * @param Contact[] $contacts
     * @param int[]     $listIds  Listes auxquelles rattacher les contacts importés
     * @return int Nombre de contacts envoyés à l'import
     */
    public function importContacts(array $contacts, array $listIds = []): int
    {
        $rows = [];
        foreach ($contacts as $contact) {
            $rows[] = [
                'email'      => $contact->getEmail(),
                'attributes' => [
                    'FIRSTNAME' => $contact->getPrenom(),
                    'LASTNAME'  => $contact->getNom(),
                    'SMS'       => $contact->getPhone() ?? ''
                ]
            ];
        }

        if (empty($rows)) {
            return 0;
        }

        $listIds = array_values(array_map('intval', $listIds));
        $sent = 0;

        // Brevo accepte de gros lots ; on découpe par 1000 par prudence.
        foreach (array_chunk($rows, 1000) as $chunk) {
            $payload = [
                'jsonBody'                => $chunk,
                'updateExistingContacts'  => true,
                'emptyContactsAttributes' => false
            ];
            if (!empty($listIds)) {
                $payload['listIds'] = $listIds;
            }

            try {
                $this->client->post('/v3/contacts/import', ['json' => $payload]);
                $sent += count($chunk);
            } catch (GuzzleException $e) {
                throw new \RuntimeException('Erreur import Brevo : ' . $e->getMessage());
            }
        }

        return $sent;
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

    /**
     * Récupère les dossiers (folders) de contacts Brevo.
     */
    public function getFolders(int $limit = 50, int $offset = 0): array
    {
        try {
            $response = $this->client->get('/v3/contacts/folders', [
                'query' => [
                    'limit'  => $limit,
                    'offset' => $offset
                ]
            ]);

            $body = json_decode((string) $response->getBody(), true);
            return $body ?? [];
        } catch (GuzzleException $e) {
            throw new \RuntimeException('Erreur récupération dossiers Brevo : ' . $e->getMessage());
        }
    }

    /**
     * Récupère l'identifiant d'un dossier Brevo par son nom, en le créant s'il n'existe pas.
     * Toutes les listes créées par le CRM sont rangées dans ce dossier.
     */
    public function getOrCreateFolderId(string $name = 'CRM Volley'): int
    {
        try {
            $folders = $this->getFolders(50, 0);
            foreach (($folders['folders'] ?? []) as $folder) {
                if (($folder['name'] ?? '') === $name) {
                    return (int) $folder['id'];
                }
            }

            $response = $this->client->post('/v3/contacts/folders', [
                'json' => ['name' => $name]
            ]);
            $body = json_decode((string) $response->getBody(), true);

            return (int) ($body['id'] ?? 1);
        } catch (GuzzleException $e) {
            throw new \RuntimeException('Erreur dossier Brevo : ' . $e->getMessage());
        }
    }

    /**
     * Recherche une liste Brevo par son nom (parcourt toutes les pages).
     */
    public function findListByName(string $name): ?array
    {
        $offset = 0;
        do {
            $data  = $this->getLists(50, $offset);
            $lists = $data['lists'] ?? [];
            foreach ($lists as $list) {
                if (($list['name'] ?? '') === $name) {
                    return $list;
                }
            }
            $offset += 50;
        } while (count($lists) === 50);

        return null;
    }
}