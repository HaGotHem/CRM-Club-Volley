<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Slim\Psr7\Response as SlimResponse;

class AuthMiddleware
{
    /**
     * Middleware d'authentification : redirige vers / si l'utilisateur n'est pas connecté
     */
    public function __invoke(Request $request, Handler $handler): Response
    {
        $path = $request->getUri()->getPath();

        // On ne protège pas la page de login, la soumission du formulaire, ni la route de santé
        if (in_array($path, ['/', '/login', '/api/health'])) {
            return $handler->handle($request);
        }

        // Si la session n'a pas d'ID administrateur, on redirige vers la page de login (/)
        if (!isset($_SESSION['admin_id'])) {
            $response = new SlimResponse();
            return $response->withHeader('Location', '/')->withStatus(302);
        }

        return $handler->handle($request);
    }
}
