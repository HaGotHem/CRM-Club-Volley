<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;

/**
 * Classe représentant un événement Weezevent.
 */
class Evenement
{
    private int $idEvenementWeezevent;
    private string $nomEvenement;
    private DateTimeImmutable $date;
    private string $lieu;
    private string $type;
    private ?string $saison;

    public function __construct(
        int $idEvenementWeezevent,
        string $nomEvenement,
        DateTimeImmutable $date,
        string $lieu,
        string $type,
        ?string $saison
    ) {
        $this->idEvenementWeezevent = $idEvenementWeezevent;
        $this->nomEvenement = $nomEvenement;
        $this->date = $date;
        $this->lieu = $lieu;
        $this->type = $type;
        $this->saison = $saison;
    }

    public function getIdEvenementWeezevent(): int
    {
        return $this->idEvenementWeezevent;
    }

    public function getNomEvenement(): string
    {
        return $this->nomEvenement;
    }

    public function setNomEvenement(string $nomEvenement): void
    {
        $this->nomEvenement = $nomEvenement;
    }

    public function getDate(): DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(DateTimeImmutable $date): void
    {
        $this->date = $date;
    }

    public function getLieu(): string
    {
        return $this->lieu;
    }

    public function setLieu(string $lieu): void
    {
        $this->lieu = $lieu;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getSaison(): ?string
    {
        return $this->saison;
    }

    public function setSaison(?string $saison): void
    {
        $this->saison = $saison;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (int)$data['idevenementweezevent'],
            $data['nom_evenement'],
            new DateTimeImmutable($data['date']),
            $data['lieu'],
            $data['type'],
            $data['saison'] ?? null
        );
    }
    /**
     * Convertit l'objet en tableau pour JSON.
     */
    public function toArray(): array
    {
        return [
            'id_weezevent'  => $this->idEvenementWeezevent,
            'nom_evenement' => $this->nomEvenement,
            'date'          => $this->date->format('Y-m-d H:i:s'),
            'lieu'          => $this->lieu,
            'type'          => $this->type,
            'saison'        => $this->saison
        ];
    }
}
