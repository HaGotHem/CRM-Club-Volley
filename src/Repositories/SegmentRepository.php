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
        $data = $stmt->fetch();

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
        $data = $stmt->fetch();

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
        while ($data = $stmt->fetch()) {
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
        while ($data = $stmt->fetch()) {
            $results[] = Segment::fromArray($data);
        }
        
        return $results;
    }

    /**
     * Ajoute un nouveau segment.
     */
    public function save(Segment $segment): bool
    {
        $sql = "INSERT INTO segment (nom_segment, date_creation) 
                VALUES (:nom, :date)";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'nom'  => $segment->getNomSegment(),
            'date' => $segment->getDateCreation()->format('Y-m-d H:i:s')
        ]);
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
}
