<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Consentement;
use PDO;

/**
 * Repository pour la gestion des consentements.
 */
class ConsentementRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = \Database::getConnection();
    }

    /**
     * Récupère un consentement par son ID.
     * 
     * @param int $id L'ID du consentement
     * @return Consentement|null L'objet Consentement ou null si non trouvé
     */
    public function findById(int $id): ?Consentement
    {
        // Utilisation d'une requête préparée pour la sécurité
        $stmt = $this->db->prepare("SELECT * FROM consentement WHERE idConsentement = :id");
        $stmt->execute(['id' => $id]);
        $data = $stmt->fetch();

        return $data ? Consentement::fromArray($data) : null;
    }

    /**
     * Récupère tous les consentements d'un contact donné.
     * 
     * @param int $idContact ID du contact
     * @return Consentement[] Liste des consentements
     */
    public function findByContactId(int $idContact): array
    {
        // Jointure pour récupérer les consentements liés à un contact via la table de liaison
        $sql = "SELECT c.* 
                FROM consentement c
                JOIN consentement_contact cc ON c.idConsentement = cc.idConsentement
                WHERE cc.idContact = :idContact";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['idContact' => $idContact]);
        
        $results = [];
        while ($data = $stmt->fetch()) {
            $results[] = Consentement::fromArray($data);
        }
        
        return $results;
    }

    /**
     * Enregistre un nouveau consentement en base de données.
     * 
     * @param Consentement $consentement
     * @return bool Succès de l'opération
     */
    public function save(Consentement $consentement): bool
    {
        $sql = "INSERT INTO consentement (type, date, source, statut) 
                VALUES (:type, :date, :source, :statut)";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'type'   => $consentement->getType(),
            'date'   => $consentement->getDate()->format('Y-m-d H:i:s'),
            'source' => $consentement->getSource(),
            'statut' => $consentement->getStatut()
        ]);
    }

    /**
     * Lie un consentement à un contact.
     * 
     * @param int $idConsentement
     * @param int $idContact
     * @return bool
     */
    public function linkToContact(int $idConsentement, int $idContact): bool
    {
        $sql = "INSERT INTO consentement_contact (idConsentement, idContact) 
                VALUES (:idC, :idContact)
                ON CONFLICT DO NOTHING"; // Évite les doublons
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'idC'       => $idConsentement,
            'idContact' => $idContact
        ]);
    }
}
