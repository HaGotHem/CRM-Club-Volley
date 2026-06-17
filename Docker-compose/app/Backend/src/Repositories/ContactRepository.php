<?php

declare(strict_types=1);

final class ContactRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findAll(): array
    {
        $sql = "
            SELECT id, first_name, last_name, email, phone, source, created_at
            FROM contacts
            ORDER BY created_at DESC
        ";

        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $sql = "
            SELECT id, first_name, last_name, email, phone, source, created_at, updated_at
            FROM contacts
            WHERE id = :id
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $contact = $stmt->fetch();
        return $contact ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $sql = "
            SELECT id, first_name, last_name, email, phone, source, created_at
            FROM contacts
            WHERE email = :email
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['email' => $email]);
        $contact = $stmt->fetch();
        return $contact ?: null;
    }

    public function create(array $data): array
    {
        $sql = "
            INSERT INTO contacts (first_name, last_name, email, phone, source)
            VALUES (:first_name, :last_name, :email, :phone, :source)
            RETURNING id, first_name, last_name, email, phone, source, created_at
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'first_name' => $data['first_name'],
            'last_name'  => $data['last_name'],
            'email'      => $data['email'],
            'phone'      => $data['phone'] ?? null,
            'source'     => $data['source'] ?? 'manual'
        ]);

        return $stmt->fetch();
    }
}