<?php

declare(strict_types=1);

final class Database
{
    private static ?PDO $connection = null;

    public static function getConnection(): PDO
    {
        if (self::$connection === null) {
            $host     = $_ENV['DB_HOST'] ?? 'db';
            $port     = $_ENV['DB_PORT'] ?? '5432';
            $dbname   = $_ENV['DB_NAME'] ?? 'appdb';
            $user     = $_ENV['DB_USER'] ?? 'appuser';
            $password = $_ENV['DB_PASSWORD'] ?? 'secretpassword';

            $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";

            self::$connection = new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT            => 5,
                PDO::ATTR_PERSISTENT         => true,
            ]);
        }

        return self::$connection;
    }
}