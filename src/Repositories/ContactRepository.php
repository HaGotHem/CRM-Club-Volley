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
     * Crée ou met à jour un contact et renvoie l'enregistrement résultant.
     *
     * Le `RETURNING *` évite un second aller-retour (SELECT) pour relire la
     * ligne fraîchement insérée/mise à jour.
     */
    public function save(Contact $contact): ?Contact
    {
        $sql = "INSERT INTO contact (nom, prenom, email, phone, source, consentement_marketing)
                VALUES (:nom, :prenom, :email, :phone, :source, :consentement)
                ON CONFLICT (email) DO UPDATE
                SET nom = EXCLUDED.nom,
                    prenom = EXCLUDED.prenom,
                    phone = EXCLUDED.phone,
                    consentement_marketing = EXCLUDED.consentement_marketing
                RETURNING *";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($this->toParams($contact));
        $data = $stmt->fetch();

        return $data ? Contact::fromArray($data) : null;
    }

    /**
     * Upsert optimisé pour les imports en masse (Weezevent / Brevo).
     *
     * Réalise l'insertion/mise à jour en UNE seule requête et indique si la
     * ligne a été créée (true) ou mise à jour (false), grâce au pseudo-attribut
     * `xmax` de PostgreSQL (= 0 lors d'un INSERT pur). Évite le SELECT
     * `findByEmail()` qui était exécuté pour chaque contact (suppression du N+1).
     */
    public function upsertWithStatus(Contact $contact): bool
    {
        $sql = "INSERT INTO contact (nom, prenom, email, phone, source, consentement_marketing)
                VALUES (:nom, :prenom, :email, :phone, :source, :consentement)
                ON CONFLICT (email) DO UPDATE
                SET nom = EXCLUDED.nom,
                    prenom = EXCLUDED.prenom,
                    phone = EXCLUDED.phone,
                    consentement_marketing = EXCLUDED.consentement_marketing
                RETURNING (xmax = 0)::int AS inserted";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($this->toParams($contact));

        return (int) $stmt->fetchColumn() === 1;
    }

    /**
     * Paramètres communs aux requêtes d'écriture d'un contact.
     */
    private function toParams(Contact $contact): array
    {
        return [
            'nom'          => $contact->getNom(),
            'prenom'       => $contact->getPrenom(),
            'email'        => $contact->getEmail(),
            'phone'        => $contact->getPhone(),
            'source'       => $contact->getSource(),
            'consentement' => $contact->isConsentementMarketing() ? 'true' : 'false'
        ];
    }
}