<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Evenement;
use Database;
use PDO;

require_once __DIR__ . '/../Database.php';

/**
 * Repository pour la gestion des événements Weezevent.
 */
class EvenementRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Récupère un événement par son ID Weezevent.
     */
    public function findById(int $idWeezevent): ?Evenement
    {
        $stmt = $this->db->prepare("SELECT * FROM evenement WHERE idEvenementWeezevent = :id");
        $stmt->execute(['id' => $idWeezevent]);
        $data = $stmt->fetch();

        return $data ? Evenement::fromArray($data) : null;
    }

    /**
     * Récupère tous les événements d'une saison donnée.
     * 
     * @param string $saison ex: "2024-2025"
     * @return Evenement[]
     */
    public function findBySaison(string $saison): array
    {
        $stmt = $this->db->prepare("SELECT * FROM evenement WHERE saison = :saison ORDER BY date DESC");
        $stmt->execute(['saison' => $saison]);
        
        $results = [];
        while ($data = $stmt->fetch()) {
            $results[] = Evenement::fromArray($data);
        }
        
        return $results;
    }

    /**
     * Sauvegarde ou met à jour un événement.
     */
    public function upsert(Evenement $evenement): bool
    {
        $sql = "INSERT INTO evenement (idEvenementWeezevent, nom_evenement, date, lieu, type, saison) 
                VALUES (:id, :nom, :date, :lieu, :type, :saison)
                ON CONFLICT (idEvenementWeezevent) DO UPDATE 
                SET nom_evenement = EXCLUDED.nom_evenement,
                    date          = EXCLUDED.date,
                    lieu          = EXCLUDED.lieu,
                    type          = EXCLUDED.type,
                    saison        = EXCLUDED.saison";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'id'     => $evenement->getIdEvenementWeezevent(),
            'nom'    => $evenement->getNomEvenement(),
            'date'   => $evenement->getDate()->format('Y-m-d H:i:s'),
            'lieu'   => $evenement->getLieu(),
            'type'   => $evenement->getType(),
            'saison' => $evenement->getSaison()
        ]);
    }

    /**
     * Récupère l'événement lié à un billet.
     */
    public function findByBilletId(int $idBilletWeezevent): ?Evenement
    {
        $sql = "SELECT e.* 
                FROM evenement e
                JOIN billet_evenement be ON e.idEvenementWeezevent = be.idEvenementWeezevent
                WHERE be.idBilletWeezevent = :idBillet";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['idBillet' => $idBilletWeezevent]);
        $data = $stmt->fetch();
        
        return $data ? Evenement::fromArray($data) : null;
    }
}
