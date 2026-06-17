<?php
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();

// Définir le chemin de base si nécessaire
$app->setBasePath('/test-slim.php');

$app->get('/test', function ($request, $response, $args) {
    $response->getBody()->write("Le serveur Slim fonctionne correctement !");
    return $response;
});

$app->run();
