<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;

function jsonResponse(Response $response, mixed $data, int $status = 200): Response
{
    // Si $data contient des objets avec toArray(), on les convertit récursivement
    $data = convertToArray($data);
    
    $payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    if ($payload === false) {
        $payload = json_encode(['success' => false, 'error' => 'Erreur encodage JSON']);
        $status = 500;
    }

    $response->getBody()->write($payload);

    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus($status);
}

/**
 * Helper récursif pour convertir des objets modèles en tableaux.
 */
function convertToArray(mixed $data): mixed
{
    if (is_array($data)) {
        return array_map('convertToArray', $data);
    }

    if (is_object($data) && method_exists($data, 'toArray')) {
        return convertToArray($data->toArray());
    }

    return $data;
}

/**
 * Cache applicatif minimaliste basé sur des fichiers (aucune extension requise).
 *
 * Exécute $producer et met son résultat en cache pendant $ttl secondes. Les
 * appels suivants, dans la fenêtre de validité, renvoient la valeur en cache
 * SANS ré-exécuter $producer — donc sans rejouer les requêtes SQL associées.
 *
 * En cas d'erreur d'écriture/lecture, le cache est simplement ignoré (la donnée
 * est recalculée), ce qui garantit qu'aucune panne n'est introduite.
 *
 * @param string        $key      Clé logique du cache.
 * @param int           $ttl      Durée de vie en secondes.
 * @param callable():array $producer Fonction produisant la donnée à mettre en cache.
 * @return array
 */
function cacheRemember(string $key, int $ttl, callable $producer): array
{
    $dir  = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nvb_cache';
    $file = $dir . DIRECTORY_SEPARATOR . md5($key) . '.json';

    // Lecture du cache s'il existe et n'est pas expiré
    if (is_file($file) && (time() - filemtime($file)) < $ttl) {
        $raw = file_get_contents($file);
        if ($raw !== false) {
            $cached = json_decode($raw, true);
            if (is_array($cached)) {
                return $cached;
            }
        }
    }

    // Cache absent ou expiré : on (re)calcule
    $data = $producer();

    // Puis on tente de le stocker (échec silencieux, non bloquant)
    $payload = json_encode($data, JSON_UNESCAPED_UNICODE);
    if ($payload !== false) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents($file, $payload, LOCK_EX);
    }

    return $data;
}
