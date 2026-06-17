<?php

declare(strict_types=1);

final class Database
{
    private static ?PDO $connection = null;

    private static function env(string $key, string $default): string
    {
        // En Docker les variables sont fournies par l'environnement (getenv),
        // en local elles peuvent venir du fichier .env chargé dans $_ENV.
        $value = getenv($key);
        if ($value === false || $value === '') {
            $value = $_ENV[$key] ?? $default;
        }
        return (string) $value;
    }

    public static function getConnection(): PDO
    {
        if (self::$connection === null) {
            $host     = self::env('DB_HOST', 'db');
            $port     = self::env('DB_PORT', '5432');
            $dbname   = self::env('DB_NAME', 'appdb');
            $user     = self::env('DB_USER', 'appuser');
            $password = self::env('DB_PASSWORD', 'secretpassword');

            $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";

            self::$connection = new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
        }

        return self::$connection;
    }
}
