<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Segment;
use PDO;

/**
 * Repository pour la gestion des segments de contacts.
 */
class SegmentRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = \Database::getConnection();
    }

    /**
     * Récupère un segment par son nom.
     */
    public function findByName(string $name): ?Segment
    {
        $stmt = $this->db->prepare("SELECT * FROM segment WHERE nom_segment = :name");
        $stmt->execute(['name' => $name]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ? Segment::fromArray($data) : null;
    }

    /**
     * Supprime toutes les liaisons pour un contact (utilisé avant resynchro)
     */
    public function removeAllSegmentsFromContact(int $idContact): bool
    {
        $stmt = $this->db->prepare("DELETE FROM contact_segment WHERE idContact = :id");
        return $stmt->execute(['id' => $idContact]);
    }
    public function findById(int $id): ?Segment
    {
        $stmt = $this->db->prepare("SELECT * FROM segment WHERE idSegment = :id");
        $stmt->execute(['id' => $id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ? Segment::fromArray($data) : null;
    }

    /**
     * Récupère tous les segments.
     * 
     * @return Segment[]
     */
    public function findAll(): array
    {
        $stmt = $this->db->query("SELECT * FROM segment ORDER BY nom_segment ASC");
        
        $results = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = Segment::fromArray($data);
        }
        
        return $results;
    }

    /**
     * Récupère les segments auxquels appartient un contact.
     * 
     * @param int $idContact
     * @return Segment[]
     */
    public function findByContactId(int $idContact): array
    {
        $sql = "SELECT s.* 
                FROM segment s
                JOIN contact_segment cs ON s.idSegment = cs.idSegment
                WHERE cs.idContact = :idContact";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['idContact' => $idContact]);
        
        $results = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = Segment::fromArray($data);
        }
        
        return $results;
    }

    /**
     * Ajoute un nouveau segment.
     */
    public function save(Segment $segment): bool
    {
        $id = $segment->getIdSegment();
        $params = [
            'nom'      => $segment->getNomSegment(),
            'date'     => $segment->getDateCreation()->format('Y-m-d H:i:s'),
            'brevo_id' => $segment->getBrevoId()
        ];

        if ($id === null) {
            $sql = "INSERT INTO segment (nom_segment, date_creation, brevo_id) 
                    VALUES (:nom, :date, :brevo_id)
                    RETURNING idSegment";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $newId = $stmt->fetchColumn();
            if ($newId) {
                // On peut utiliser la réflexion ou un setter si on veut mettre à jour l'objet
                // Mais pour le retour de save(), bool suffit.
                return true;
            }
            return false;
        } else {
            $sql = "INSERT INTO segment (idSegment, nom_segment, date_creation, brevo_id) 
                    VALUES (:id, :nom, :date, :brevo_id)
                    ON CONFLICT (idSegment) DO UPDATE 
                    SET nom_segment = EXCLUDED.nom_segment, 
                        brevo_id = EXCLUDED.brevo_id";
            $params['id'] = $id;
        }
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Ajoute un contact à un segment.
     */
    public function addContactToSegment(int $idContact, int $idSegment): bool
    {
        $sql = "INSERT INTO contact_segment (idContact, idSegment) 
                VALUES (:idC, :idS)
                ON CONFLICT DO NOTHING";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'idC' => $idContact,
            'idS' => $idSegment
        ]);
    }
    /**
     * Supprime un segment par son ID.
     */
    public function delete(int $id): bool
    {
        $sql = "DELETE FROM segment WHERE idSegment = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }
}
