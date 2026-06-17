<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;

/**
 * Classe représentant un segment de contacts.
 */
class Segment
{
    private ?int $idSegment;
    private string $nomSegment;
    private DateTimeImmutable $dateCreation;

    public function __construct(
        ?int $idSegment,
        string $nomSegment,
        DateTimeImmutable $dateCreation
    ) {
        $this->idSegment = $idSegment;
        $this->nomSegment = $nomSegment;
        $this->dateCreation = $dateCreation;
    }

    public function getIdSegment(): ?int
    {
        return $this->idSegment;
    }

    public function getNomSegment(): string
    {
        return $this->nomSegment;
    }

    public function setNomSegment(string $nomSegment): void
    {
        $this->nomSegment = $nomSegment;
    }

    public function getDateCreation(): DateTimeImmutable
    {
        return $this->dateCreation;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['idsegment'] ?? null,
            $data['nom_segment'],
            new DateTimeImmutable($data['date_creation'])
        );
    }
    /**
     * Convertit l'objet en tableau pour JSON.
     */
    public function toArray(): array
    {
        return [
            'id'            => $this->idSegment,
            'nom_segment'   => $this->nomSegment,
            'date_creation' => $this->dateCreation->format('Y-m-d H:i:s')
        ];
    }
}
