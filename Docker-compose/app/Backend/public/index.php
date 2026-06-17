<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use Slim\Factory\AppFactory;

// Autoloader Composer (composer.json se trouve dans src/, donc vendor dans src/vendor)
require __DIR__ . '/../src/vendor/autoload.php';

require __DIR__ . '/../src/Helpers.php';
require __DIR__ . '/../src/Database.php';
require __DIR__ . '/../src/Repositories/ContactRepository.php';
require __DIR__ . '/../src/Services/WeezeventService.php';
require __DIR__ . '/../src/Services/BrevoService.php';

// Chargement du fichier .env (optionnel : en Docker les variables viennent de l'environnement)
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// Création de l'application Slim
$app = AppFactory::create();

// Middleware JSON
$app->addBodyParsingMiddleware();

// Middleware erreurs
$app->addErrorMiddleware(true, true, true);

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

// Chargement des routes (les fichiers de routes sont dans src/routes)
require __DIR__ . '/../src/routes/contacts.php';
require __DIR__ . '/../src/routes/stats.php';
require __DIR__ . '/../src/routes/segments.php';
require __DIR__ . '/../src/routes/sync.php';

$app->run();
