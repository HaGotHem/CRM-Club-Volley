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
