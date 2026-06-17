<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;

/**
 * Classe représentant un contact synchronisé avec Brevo.
 */
class Contact
{
    private ?int $idContact;
    private string $nom;
    private string $prenom;
    private string $email;
    private ?string $phone;
    private string $source;
    private DateTimeImmutable $dateCreation;
    private ?DateTimeImmutable $dateDerniereMaj;
    private bool $consentementMarketing;

    public function __construct(
        ?int $idContact,
        string $nom,
        string $prenom,
        string $email,
        ?string $phone,
        string $source,
        DateTimeImmutable $dateCreation,
        ?DateTimeImmutable $dateDerniereMaj,
        bool $consentementMarketing
    ) {
        $this->idContact = $idContact;
        $this->nom = $nom;
        $this->prenom = $prenom;
        $this->email = $email;
        $this->phone = $phone;
        $this->source = $source;
        $this->dateCreation = $dateCreation;
        $this->dateDerniereMaj = $dateDerniereMaj;
        $this->consentementMarketing = $consentementMarketing;
    }

    // Getters et Setters

    public function getIdContact(): ?int
    {
        return $this->idContact;
    }

    public function getNom(): string
    {
        return $this->nom;
    }

    public function setNom(string $nom): void
    {
        $this->nom = $nom;
    }

    public function getPrenom(): string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): void
    {
        $this->prenom = $prenom;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): void
    {
        $this->phone = $phone;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function setSource(string $source): void
    {
        $this->source = $source;
    }

    public function getDateCreation(): DateTimeImmutable
    {
        return $this->dateCreation;
    }

    public function getDateDerniereMaj(): ?DateTimeImmutable
    {
        return $this->dateDerniereMaj;
    }

    public function isConsentementMarketing(): bool
    {
        return $this->consentementMarketing;
    }

    public function setConsentementMarketing(bool $consentementMarketing): void
    {
        $this->consentementMarketing = $consentementMarketing;
    }

    /**
     * Factory method à partir d'un tableau DB.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['idcontact'] ?? null,
            $data['nom'],
            $data['prenom'],
            $data['email'],
            $data['phone'] ?? null,
            $data['source'] ?? 'manual',
            new DateTimeImmutable($data['date_creation']),
            isset($data['date_derniere_maj']) ? new DateTimeImmutable($data['date_derniere_maj']) : null,
            (bool)($data['consentement_marketing'] ?? false)
        );
    }
    /**
     * Convertit l'objet en tableau pour JSON.
     */
    public function toArray(): array
    {
        return [
            'id'                     => $this->idContact,
            'nom'                    => $this->nom,
            'prenom'                 => $this->prenom,
            'email'                  => $this->email,
            'phone'                  => $this->phone,
            'source'                 => $this->source,
            'date_creation'          => $this->dateCreation->format('Y-m-d H:i:s'),
            'date_derniere_maj'      => $this->dateDerniereMaj?->format('Y-m-d H:i:s'),
            'consentement_marketing' => $this->consentementMarketing
        ];
    }
}
