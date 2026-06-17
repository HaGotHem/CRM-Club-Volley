<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;

/**
 * Classe représentant un consentement.
 */
class Consentement
{
    private ?int $idConsentement;
    private string $type;
    private DateTimeImmutable $date;
    private string $source;
    private string $statut;

    public function __construct(
        ?int $idConsentement,
        string $type,
        DateTimeImmutable $date,
        string $source,
        string $statut = 'actif'
    ) {
        $this->idConsentement = $idConsentement;
        $this->type = $type;
        $this->date = $date;
        $this->source = $source;
        $this->statut = $statut;
    }

    public function getIdConsentement(): ?int
    {
        return $this->idConsentement;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getDate(): DateTimeImmutable
    {
        return $this->date;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function setSource(string $source): void
    {
        $this->source = $source;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): void
    {
        $this->statut = $statut;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['idconsentement'] ?? null,
            $data['type'],
            new DateTimeImmutable($data['date']),
            $data['source'],
            $data['statut'] ?? 'actif'
        );
    }
    /**
     * Convertit l'objet en tableau pour JSON.
     */
    public function toArray(): array
    {
        return [
            'id'     => $this->idConsentement,
            'type'   => $this->type,
            'date'   => $this->date->format('Y-m-d H:i:s'),
            'source' => $this->source,
            'statut' => $this->statut
        ];
    }
}
