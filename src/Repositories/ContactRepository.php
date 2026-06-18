<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Contact;
use PDO;

/**
 * Repository pour la gestion des contacts.
 */
final class ContactRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = \Database::getConnection();
    }

    /**
     * Récupère tous les contacts avec pagination et recherche optionnelle.
     * 
     * @return Contact[]
     */
    public function findAll(int $limit = 100, int $offset = 0, ?string $search = null): array
    {
        $sql = "SELECT * FROM contact";
        $params = [];

        if ($search) {
            $sql .= " WHERE nom ILIKE :search 
                      OR prenom ILIKE :search 
                      OR email ILIKE :search 
                      OR phone ILIKE :search";
            $params['search'] = '%' . $search . '%';
        }

        $sql .= " ORDER BY date_creation DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        
        // Liaison explicite des paramètres pour LIMIT et OFFSET
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        if ($search) {
            $stmt->bindValue(':search', $params['search'], PDO::PARAM_STR);
        }

        $stmt->execute();
        
        $results = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = Contact::fromArray($data);
        }
        
        return $results;
    }

    /**
     * Compte le nombre total de contacts avec recherche optionnelle.
     */
    public function countAll(?string $search = null): int
    {
        $sql = "SELECT COUNT(*) FROM contact";
        $params = [];

        if ($search) {
            $sql .= " WHERE nom ILIKE :search 
                      OR prenom ILIKE :search 
                      OR email ILIKE :search 
                      OR phone ILIKE :search";
            $params['search'] = '%' . $search . '%';
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Récupère les contacts appartenant à un segment avec recherche optionnelle.
     * 
     * @param int $idSegment
     * @return Contact[]
     */
    public function findBySegmentId(int $idSegment, ?string $search = null): array
    {
        $sql = "SELECT c.* 
                FROM contact c
                JOIN contact_segment cs ON c.idContact = cs.idContact
                WHERE cs.idSegment = :idSegment";
        
        $params = ['idSegment' => $idSegment];

        if ($search) {
            $sql .= " AND (c.nom ILIKE :search 
                      OR c.prenom ILIKE :search 
                      OR c.email ILIKE :search 
                      OR c.phone ILIKE :search)";
            $params['search'] = '%' . $search . '%';
        }

        $sql .= " ORDER BY c.date_creation DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        $results = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = Contact::fromArray($data);
        }
        
        return $results;
    }

    /**
     * Trouve un contact par son ID.
     */
    public function findById(int $id): ?Contact
    {
        $stmt = $this->db->prepare("SELECT * FROM contact WHERE idContact = :id");
        $stmt->execute(['id' => $id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ? Contact::fromArray($data) : null;
    }

    /**
     * Trouve un contact par son email.
     */
    public function findByEmail(string $email): ?Contact
    {
        $stmt = $this->db->prepare("SELECT * FROM contact WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ? Contact::fromArray($data) : null;
    }

    /**
     * Crée un nouveau contact.
     */
    public function save(Contact $contact): bool
    {
        $sql = "INSERT INTO contact (nom, prenom, email, phone, source, consentement_marketing) 
                VALUES (:nom, :prenom, :email, :phone, :source, :consentement)
                ON CONFLICT (email) DO UPDATE 
                SET nom = EXCLUDED.nom, 
                    prenom = EXCLUDED.prenom, 
                    phone = EXCLUDED.phone,
                    consentement_marketing = EXCLUDED.consentement_marketing,
                    date_derniere_maj = NOW()
                RETURNING idContact";

        $stmt = $this->db->prepare($sql);
        $res = $stmt->execute([
            'nom'          => $contact->getNom(),
            'prenom'       => $contact->getPrenom(),
            'email'        => $contact->getEmail(),
            'phone'        => $contact->getPhone(),
            'source'       => $contact->getSource(),
            'consentement' => $contact->isConsentementMarketing() ? 1 : 0
        ]);

        if ($res) {
            $id = $stmt->fetchColumn();
            if ($id) {
                // On met à jour l'objet contact avec l'ID récupéré
                $reflector = new \ReflectionClass($contact);
                $prop = $reflector->getProperty('idContact');
                $prop->setAccessible(true);
                $prop->setValue($contact, (int)$id);
            }
        }

        return $res;
    }
}