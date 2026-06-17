<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;

function jsonResponse(Response $response, array $data, int $status = 200): Response
{
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