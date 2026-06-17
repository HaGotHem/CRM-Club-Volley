<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Billet;
use PDO;

/**
 * Repository pour la gestion des billets Weezevent.
 */
class BilletRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = \Database::getConnection();
    }

    /**
     * Récupère un billet par son ID Weezevent.
     */
    public function findById(int $idWeezevent): ?Billet
    {
        $stmt = $this->db->prepare("SELECT * FROM billet WHERE idBilletWeezevent = :id");
        $stmt->execute(['id' => $idWeezevent]);
        $data = $stmt->fetch();

        return $data ? Billet::fromArray($data) : null;
    }

    /**
     * Récupère tous les billets achetés par un contact.
     * 
     * @param int $idContact
     * @return Billet[]
     */
    public function findByContactId(int $idContact): array
    {
        $sql = "SELECT b.* 
                FROM billet b
                JOIN contact_billet cb ON b.idBilletWeezevent = cb.idBilletWeezevent
                WHERE cb.idContact = :idContact";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['idContact' => $idContact]);
        
        $results = [];
        while ($data = $stmt->fetch()) {
            $results[] = Billet::fromArray($data);
        }
        
        return $results;
    }

    /**
     * Sauvegarde ou met à jour un billet.
     */
    public function upsert(Billet $billet): bool
    {
        $sql = "INSERT INTO billet (idBilletWeezevent, date_achat, quantite, montant_total, type_tarif, code_promotionnel, origine) 
                VALUES (:id, :date, :qte, :montant, :tarif, :promo, :origine)
                ON CONFLICT (idBilletWeezevent) DO UPDATE 
                SET date_achat        = EXCLUDED.date_achat,
                    quantite          = EXCLUDED.quantite,
                    montant_total     = EXCLUDED.montant_total,
                    type_tarif        = EXCLUDED.type_tarif,
                    code_promotionnel = EXCLUDED.code_promotionnel,
                    origine           = EXCLUDED.origine";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'id'      => $billet->getIdBilletWeezevent(),
            'date'    => $billet->getDateAchat()->format('Y-m-d H:i:s'),
            'qte'     => $billet->getQuantite(),
            'montant' => $billet->getMontantTotal(),
            'tarif'   => $billet->getTypeTarif(),
            'promo'   => $billet->getCodePromotionnel(),
            'origine' => $billet->getOrigine()
        ]);
    }

    /**
     * Lie un billet à un événement.
     */
    public function linkToEvenement(int $idBillet, int $idEvenement): bool
    {
        $sql = "INSERT INTO billet_evenement (idBilletWeezevent, idEvenementWeezevent) 
                VALUES (:idB, :idE)
                ON CONFLICT DO NOTHING";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'idB' => $idBillet,
            'idE' => $idEvenement
        ]);
    }

    /**
     * Lie un billet à un contact (acheteur).
     */
    public function linkToContact(int $idBillet, int $idContact): bool
    {
        $sql = "INSERT INTO contact_billet (idBilletWeezevent, idContact) 
                VALUES (:idB, :idC)
                ON CONFLICT DO NOTHING";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'idB' => $idBillet,
            'idC' => $idContact
        ]);
    }
}
