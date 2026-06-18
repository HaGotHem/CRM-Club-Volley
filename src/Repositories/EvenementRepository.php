<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class EvenementRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = \Database::getConnection();
    }

    /**
     * Récupère tous les événements présents en base avec le nombre de tickets vendus
     */
    public function findAllWithStats(): array
    {
        $sql = "SELECT e.*, 
                       COUNT(cb.idContact) as total_tickets
                FROM evenement e
                LEFT JOIN billet_evenement be ON e.idEvenementWeezevent = be.idEvenementWeezevent
                LEFT JOIN contact_billet cb ON be.idBilletWeezevent = cb.idBilletWeezevent
                GROUP BY e.idEvenementWeezevent
                ORDER BY e.date DESC";

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function exists(int $id): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM evenement WHERE idEvenementWeezevent = :id");
        $stmt->execute(['id' => $id]);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * Upsert d'un événement Weezevent par idEvenementWeezevent
     */
    public function save(array $event): bool
    {
        $sql = "INSERT INTO evenement (idEvenementWeezevent, nom_evenement, date, lieu, type, saison)
                VALUES (:id, :nom, :date, :lieu, :type, :saison)
                ON CONFLICT (idEvenementWeezevent) DO UPDATE SET
                    nom_evenement = EXCLUDED.nom_evenement,
                    date = EXCLUDED.date,
                    lieu = EXCLUDED.lieu,
                    type = EXCLUDED.type,
                    saison = EXCLUDED.saison";

        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            'id'     => (int)$event['id'],
            'nom'    => $event['nom'] ?? 'Événement',
            'date'   => $event['date'] ?? date('Y-m-d H:i:s'),
            'lieu'   => $event['lieu'] ?? '—',
            'type'   => $event['type'] ?? '—',
            'saison' => $event['saison'] ?? null,
        ]);

        return $result;
    }
}
