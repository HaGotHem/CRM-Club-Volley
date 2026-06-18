<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class BilletRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = \Database::getConnection();
    }

    public function exists(int $id): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM billet WHERE idBilletWeezevent = :id");
        $stmt->execute(['id' => $id]);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * Upsert d'un billet Weezevent par idBilletWeezevent
     */
    public function save(array $ticket): bool
    {
        $sql = "INSERT INTO billet (idBilletWeezevent, date_achat, quantite, montant_total, type_tarif, code_promotionnel, origine)
                VALUES (:id, :date_achat, :quantite, :montant_total, :type_tarif, :code_promo, :origine)
                ON CONFLICT (idBilletWeezevent) DO UPDATE SET
                    date_achat = EXCLUDED.date_achat,
                    quantite = EXCLUDED.quantite,
                    montant_total = EXCLUDED.montant_total,
                    type_tarif = EXCLUDED.type_tarif,
                    code_promotionnel = EXCLUDED.code_promotionnel,
                    origine = EXCLUDED.origine";

        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            'id'            => (int)$ticket['id'],
            'date_achat'    => $ticket['date_achat'] ?? date('Y-m-d H:i:s'),
            'quantite'      => (int)($ticket['quantite'] ?? 1),
            'montant_total' => (float)($ticket['montant_total'] ?? 0),
            'type_tarif'    => $ticket['type_tarif'] ?? '—',
            'code_promo'    => $ticket['code_promotionnel'] ?? null,
            'origine'       => $ticket['origine'] ?? 'weezevent',
        ]);

        return $result;
    }

    public function linkBilletEvenement(int $idBillet, int $idEvenement): bool
    {
        $sql = "INSERT INTO billet_evenement (idBilletWeezevent, idEvenementWeezevent)
                VALUES (:idBillet, :idEvent)
                ON CONFLICT (idBilletWeezevent, idEvenementWeezevent) DO NOTHING";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['idBillet' => $idBillet, 'idEvent' => $idEvenement]);
    }

    public function linkContactBillet(int $idContact, int $idBillet): bool
    {
        $sql = "INSERT INTO contact_billet (idContact, idBilletWeezevent)
                VALUES (:idContact, :idBillet)
                ON CONFLICT (idContact, idBilletWeezevent) DO NOTHING";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['idContact' => $idContact, 'idBillet' => $idBillet]);
    }
}
