<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use Slim\Factory\AppFactory;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

use App\Models\Contact;
use App\Models\Administrateur;
use App\Repositories\AdminRepository;
use App\Repositories\ContactRepository;
use App\Repositories\SegmentRepository;
use App\Repositories\EvenementRepository;
use App\Repositories\BilletRepository;
use App\Repositories\ConsentementRepository;
use App\Middleware\AuthMiddleware;

require __DIR__ . '/../vendor/autoload.php';

require __DIR__ . '/../src/Helpers.php';
require __DIR__ . '/../src/Database.php';

// Les modèles sont chargés via l'autoloader PSR-4 configuré dans composer.json
// On peut garder les inclusions manuelles pour les services/repos si l'autoloader n'est pas utilisé partout,
// mais avec PSR-4 "App\\" => "src/", l'autoloader vendor/autoload.php devrait tout gérer.

// Chargement du fichier .env
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// Création de l'application Slim
$app = AppFactory::create();

// Détection de l'environnement (production par défaut)
$isDev = ($_ENV['APP_ENV'] ?? 'production') !== 'production';

// Configuration de Twig — cache de compilation activé.
// Les templates ne sont compilés en PHP qu'une seule fois (puis réutilisés),
// au lieu d'être recompilés à chaque requête. Le cache est écrit dans le
// système de fichiers local du conteneur (rapide) plutôt que sur le bind-mount.
// auto_reload=true => les modifications de templates restent prises en compte.
$twig = Twig::create(__DIR__ . '/../templates', [
    'cache'       => sys_get_temp_dir() . '/nvb_twig_cache',
    'auto_reload' => true,
]);
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

// Middleware erreurs — détails affichés uniquement hors production
$app->addErrorMiddleware($isDev, true, true);

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

// Sécurisation des routes API
$app->add($authMiddleware);

// Redirection forcée de /index.php (Exécuté tout au début de la pile LIFO)
$app->add(function ($request, $handler) {
    $path = $request->getUri()->getPath();
    if ($path === '/index.php') {
        $response = new \Slim\Psr7\Response();
        return $response->withHeader('Location', '/')->withStatus(301);
    }
    return $handler->handle($request);
});

$app->run();