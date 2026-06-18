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
    private ?int $brevoId;

    public function __construct(
        ?int $idSegment,
        string $nomSegment,
        DateTimeImmutable $dateCreation,
        ?int $brevoId = null
    ) {
        $this->idSegment = $idSegment;
        $this->nomSegment = $nomSegment;
        $this->dateCreation = $dateCreation;
        $this->brevoId = $brevoId;
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

    public function getBrevoId(): ?int
    {
        return $this->brevoId;
    }

    public function setBrevoId(?int $brevoId): void
    {
        $this->brevoId = $brevoId;
    }

    public static function fromArray(array $data): self
    {
        // PostgreSQL peut retourner les clés en minuscules
        $data = array_change_key_case($data, CASE_LOWER);

        return new self(
            $data['idsegment'] ?? null,
            $data['nom_segment'] ?? '',
            new DateTimeImmutable($data['date_creation'] ?? 'now'),
            isset($data['brevo_id']) ? (int)$data['brevo_id'] : null
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
            'date_creation' => $this->dateCreation->format('Y-m-d H:i:s'),
            'brevo_id'      => $this->brevoId
        ];
    }
}
