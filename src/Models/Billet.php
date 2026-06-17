<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;

/**
 * Classe représentant un billet Weezevent.
 */
class Billet
{
    private int $idBilletWeezevent;
    private DateTimeImmutable $dateAchat;
    private int $quantite;
    private float $montantTotal;
    private string $typeTarif;
    private ?string $codePromotionnel;
    private string $origine;

    public function __construct(
        int $idBilletWeezevent,
        DateTimeImmutable $dateAchat,
        int $quantite,
        float $montantTotal,
        string $typeTarif,
        ?string $codePromotionnel,
        string $origine
    ) {
        $this->idBilletWeezevent = $idBilletWeezevent;
        $this->dateAchat = $dateAchat;
        $this->quantite = $quantite;
        $this->montantTotal = $montantTotal;
        $this->typeTarif = $typeTarif;
        $this->codePromotionnel = $codePromotionnel;
        $this->origine = $origine;
    }

    public function getIdBilletWeezevent(): int
    {
        return $this->idBilletWeezevent;
    }

    public function getDateAchat(): DateTimeImmutable
    {
        return $this->dateAchat;
    }

    public function getQuantite(): int
    {
        return $this->quantite;
    }

    public function setQuantite(int $quantite): void
    {
        $this->quantite = $quantite;
    }

    public function getMontantTotal(): float
    {
        return $this->montantTotal;
    }

    public function setMontantTotal(float $montantTotal): void
    {
        $this->montantTotal = $montantTotal;
    }

    public function getTypeTarif(): string
    {
        return $this->typeTarif;
    }

    public function setTypeTarif(string $typeTarif): void
    {
        $this->typeTarif = $typeTarif;
    }

    public function getCodePromotionnel(): ?string
    {
        return $this->codePromotionnel;
    }

    public function setCodePromotionnel(?string $codePromotionnel): void
    {
        $this->codePromotionnel = $codePromotionnel;
    }

    public function getOrigine(): string
    {
        return $this->origine;
    }

    public function setOrigine(string $origine): void
    {
        $this->origine = $origine;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (int)$data['idbilletweezevent'],
            new DateTimeImmutable($data['date_achat']),
            (int)$data['quantite'],
            (float)$data['montant_total'],
            $data['type_tarif'],
            $data['code_promotionnel'] ?? null,
            $data['origine']
        );
    }
    /**
     * Convertit l'objet en tableau pour JSON.
     */
    public function toArray(): array
    {
        return [
            'id_weezevent'      => $this->idBilletWeezevent,
            'date_achat'        => $this->dateAchat->format('Y-m-d H:i:s'),
            'quantite'          => $this->quantite,
            'montant_total'     => $this->montantTotal,
            'type_tarif'        => $this->typeTarif,
            'code_promotionnel' => $this->codePromotionnel,
            'origine'           => $this->origine
        ];
    }
}
