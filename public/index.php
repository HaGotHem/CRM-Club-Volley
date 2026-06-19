<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use Slim\Factory\AppFactory;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

use App\Middleware\AuthMiddleware;
use App\Repositories\AdminRepository;

require __DIR__ . '/../vendor/autoload.php';

require __DIR__ . '/../src/Helpers.php';
require __DIR__ . '/../src/Database.php';

// Chargement du fichier .env
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// Création de l'application Slim
$app = AppFactory::create();

// Configuration de Twig
$twig = Twig::create(__DIR__ . '/../templates', ['cache' => false]);
$app->add(TwigMiddleware::create($app, $twig));

// Ajout des variables de session aux variables Twig
$app->add(function ($request, $handler) use ($twig) {
    $twig->getEnvironment()->addGlobal('current_route', $request->getUri()->getPath());
    $twig->getEnvironment()->addGlobal('session', $_SESSION);
    return $handler->handle($request);
});

// Démarrage de la session PHP avec une durée de 2 heures (7200 secondes)
ini_set('session.gc_maxlifetime', '7200');
ini_set('session.cookie_lifetime', '7200');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

// Routes MVC (Vues)
$app->get('/', function ($request, $response) {
    // Si déjà connecté, on va sur le dashboard
    if (isset($_SESSION['admin_id'])) {
        return $response->withHeader('Location', '/dashboard')->withStatus(302);
    }
    $view = Twig::fromRequest($request);
    $error = $_SESSION['login_error'] ?? null;
    unset($_SESSION['login_error']);
    return $view->render($response, 'pages/login.html.twig', ['error' => $error]);
});


$authMiddleware = new AuthMiddleware();

$app->get('/dashboard', function ($request, $response) {
    $view = Twig::fromRequest($request);
    return $view->render($response, 'pages/dashboard.html.twig');
});

$app->get('/contacts', function ($request, $response) {
    $view = Twig::fromRequest($request);
    return $view->render($response, 'pages/contacts.html.twig');
});

$app->get('/stats', function ($request, $response) {
    $view = Twig::fromRequest($request);
    return $view->render($response, 'pages/stats.html.twig');
});

$app->get('/events', function ($request, $response) {
    $view = Twig::fromRequest($request);
    return $view->render($response, 'pages/events.html.twig');
});

$app->post('/login', function ($request, $response) {
    $data = $request->getParsedBody();
    $email = $data['identifiant'] ?? '';
    $password = $data['password'] ?? '';

    $adminRepo = new AdminRepository();
    $admin = $adminRepo->findByEmail($email);

    if ($admin && password_verify($password, $admin->getMotDePasse())) {
        $_SESSION['admin_id'] = $admin->getIdAdministrateur();
        $_SESSION['admin_name'] = $admin->getPrenom() . ' ' . $admin->getNom();
        return $response->withHeader('Location', '/dashboard')->withStatus(302);
    }

    $_SESSION['login_error'] = "Identifiant ou mot de passe incorrect.";
    return $response->withHeader('Location', '/')->withStatus(302);
});

$app->get('/logout', function ($request, $response) {
    session_destroy();
    return $response->withHeader('Location', '/')->withStatus(302);
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
require __DIR__ . '/routes/contacts.php';
require __DIR__ . '/routes/stats.php';
require __DIR__ . '/routes/segments.php';
require __DIR__ . '/routes/sync.php';
require __DIR__ . '/routes/events.php';

// Sécurisation des routes API
$app->add($authMiddleware);

// Redirection forcée de /index.php
$app->add(function ($request, $handler) use ($app) {
    $path = $request->getUri()->getPath();
    if ($path === '/index.php' || $path === '/index.php/') {
        $response = $app->getResponseFactory()->createResponse();
        return $response->withHeader('Location', '/')->withStatus(301);
    }
    return $handler->handle($request);
});

$app->run();