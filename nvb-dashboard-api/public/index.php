<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

require __DIR__ . '/../src/Helpers.php';
require __DIR__ . '/../src/Database.php';

// Chargement du fichier .env
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// Création de l'application Slim
$app = AppFactory::create();

// Middleware JSON
$app->addBodyParsingMiddleware();

// Middleware erreurs
$app->addErrorMiddleware(
    ($_ENV['APP_DEBUG'] ?? 'false') === 'true',
    true,
    true
);

// Middleware CORS
$app->add(function ($request, $handler) {
    $response = $handler->handle($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
});

$app->options('/{routes:.+}', function ($request, $response) {
    return $response;
});

// Route de test
$app->get('/api/health', function ($request, $response) {
    return jsonResponse($response, [
        'status'    => 'ok',
        'message'   => 'API Nice Volley Ball opérationnelle',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
});

// Chargement des routes
require __DIR__ . '/../routes/contacts.php';
require __DIR__ . '/../routes/stats.php';
require __DIR__ . '/../routes/segments.php';
require __DIR__ . '/../routes/sync.php';

$app->run();