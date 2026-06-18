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
     * Récupère tous les contacts avec pagination.
     * 
     * @return Contact[]
     */
    public function findAll(int $limit = 100, int $offset = 0): array
    {
        $sql = "SELECT * FROM contact ORDER BY date_creation DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $results = [];
        while ($data = $stmt->fetch()) {
            $results[] = Contact::fromArray($data);
        }
        
        return $results;
    }

    /**
     * Compte le nombre total de contacts.
     */
    public function countAll(): int
    {
        return (int) $this->db->query("SELECT COUNT(*) FROM contact")->fetchColumn();
    }

    /**
     * Récupère les contacts appartenant à un segment.
     * 
     * @param int $idSegment
     * @return Contact[]
     */
    public function findBySegmentId(int $idSegment): array
    {
        $sql = "SELECT c.* 
                FROM contact c
                JOIN contact_segment cs ON c.idContact = cs.idContact
                WHERE cs.idSegment = :idSegment
                ORDER BY c.date_creation DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['idSegment' => $idSegment]);
        
        $results = [];
        while ($data = $stmt->fetch()) {
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
        $data = $stmt->fetch();

        return $data ? Contact::fromArray($data) : null;
    }

    /**
     * Trouve un contact par son email.
     */
    public function findByEmail(string $email): ?Contact
    {
        $stmt = $this->db->prepare("SELECT * FROM contact WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $data = $stmt->fetch();

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
            'consentement' => $contact->isConsentementMarketing() ? 'true' : 'false'
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