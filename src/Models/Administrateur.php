<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Classe représentant un administrateur du système.
 */
class Administrateur
{
    private ?int $idAdministrateur;
    private string $nom;
    private string $prenom;
    private string $email;
    private string $motDePasse;
    private string $statut;

    public function __construct(
        ?int $idAdministrateur,
        string $nom,
        string $prenom,
        string $email,
        string $motDePasse,
        string $statut = 'actif'
    ) {
        $this->idAdministrateur = $idAdministrateur;
        $this->nom = $nom;
        $this->prenom = $prenom;
        $this->email = $email;
        $this->motDePasse = $motDePasse;
        $this->statut = $statut;
    }

    // Getters et Setters

    public function getIdAdministrateur(): ?int
    {
        return $this->idAdministrateur;
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

    public function getMotDePasse(): string
    {
        return $this->motDePasse;
    }

    public function setMotDePasse(string $motDePasse): void
    {
        $this->motDePasse = $motDePasse;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): void
    {
        $this->statut = $statut;
    }

    /**
     * Convertit un tableau associatif (issu de la DB) en objet Administrateur.
     */
    public static function fromArray(array $data): self
    {
        $data = array_change_key_case($data, CASE_LOWER);
        return new self(
            $data['idadministrateur'] ?? null,
            $data['nom'] ?? '',
            $data['prenom'] ?? '',
            $data['email'] ?? '',
            $data['mot_de_passe'] ?? '',
            $data['statut'] ?? 'actif'
        );
    }
    /**
     * Convertit l'objet en tableau pour JSON.
     */
    public function toArray(): array
    {
        return [
            'id'     => $this->idAdministrateur,
            'nom'    => $this->nom,
            'prenom' => $this->prenom,
            'email'  => $this->email,
            'statut' => $this->statut
        ];
    }
}
