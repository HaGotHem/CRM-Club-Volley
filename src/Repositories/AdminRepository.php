<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Administrateur;
use Database;
use PDO;

require_once __DIR__ . '/../Database.php';

/**
 * Repository pour la gestion des administrateurs.
 */
final class AdminRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Trouve un administrateur par son email et son statut 'actif'.
     * 
     * @param string $email
     * @return Administrateur|null
     */
    public function findByEmail(string $email): ?Administrateur
    {
        // Utilisation d'une requête préparée pour prévenir les injections SQL
        $stmt = $this->db->prepare("SELECT * FROM administrateur WHERE email = :email AND statut = 'actif'");
        $stmt->execute(['email' => $email]);
        $data = $stmt->fetch();

        return $data ? Administrateur::fromArray($data) : null;
    }

    /**
     * Trouve un administrateur par son ID.
     */
    public function findById(int $id): ?Administrateur
    {
        $stmt = $this->db->prepare("SELECT * FROM administrateur WHERE idAdministrateur = :id");
        $stmt->execute(['id' => $id]);
        $data = $stmt->fetch();

        return $data ? Administrateur::fromArray($data) : null;
    }
}
