<?php

declare(strict_types=1);

use App\Core\Env;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Controllers\Api\PublicController;
use App\Controllers\Admin\ArchivesController;
use App\Controllers\Admin\AuthController;
use App\Controllers\Admin\AutomationsController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\FormationsController;
use App\Controllers\Admin\InscriptionsController;
use App\Controllers\Admin\JobApplicationsController;
use App\Controllers\Admin\JobOffersController;
use App\Controllers\Admin\MessagesController;
use App\Controllers\Admin\UsersController;
use App\Controllers\Admin\SettingsController;
use App\Controllers\Admin\SurveillanceController;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\AdminRole;
use App\Middleware\SuperAdminRole;

$root = dirname(__DIR__);

// Autoload : Composer si présent, sinon fallback PSR-4 minimal (App\ -> app/)
if (is_file($root . '/vendor/autoload.php')) {
    require $root . '/vendor/autoload.php';
} else {
    spl_autoload_register(function (string $class) use ($root): void {
        if (str_starts_with($class, 'App\\')) {
            $file = $root . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
            if (is_file($file)) {
                require $file;
            }
        }
    });
}
require $root . '/app/helpers.php';

Env::load($root . '/.env');

error_reporting(E_ALL);
ini_set('display_errors', Env::bool('APP_DEBUG') ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', $root . '/storage/logs/php-error.log');
date_default_timezone_set('Africa/Lome');

// --- En-têtes de sécurité globaux ---
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

$request = new Request();
$isApi = str_starts_with($request->path, '/api/');

if ($isApi) {
    // CORS : whitelist des origines front
    $allowed = array_filter(array_map('trim', explode(',', Env::get('CORS_ORIGINS', ''))));
    $origin = $request->origin();
    if ($origin && in_array($origin, $allowed, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        header('Access-Control-Max-Age: 86400');
    }
    if ($request->method === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
} else {
    // Session sécurisée pour l'admin uniquement
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1'], true)
                      && !str_starts_with($_SERVER['HTTP_HOST'] ?? '', 'localhost:')
                      && !str_starts_with($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1:'),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_name('procope_admin');
    session_start();
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'");
}

// --- Routes ---
$router = new Router();

// API publique (JSON, CORS)
$router->get('/api/formations/active', [PublicController::class, 'activeFormation']);
$router->post('/api/inscriptions', [PublicController::class, 'createInscription']);
$router->post('/api/contacts', [PublicController::class, 'createContact']);
$router->get('/api/offres', [PublicController::class, 'listOffers']);
$router->get('/api/offres/{slug}', [PublicController::class, 'showOffer']);
$router->post('/api/offres/{slug}/postuler', [PublicController::class, 'applyToOffer']);

// Auth admin
$router->get('/admin/login', [AuthController::class, 'showLogin']);
$router->post('/admin/login', [AuthController::class, 'login']);
$router->post('/admin/logout', [AuthController::class, 'logout'], [AuthMiddleware::class, CsrfMiddleware::class]);

// Back-office (session requise)
$auth = [AuthMiddleware::class];
$authPost = [AuthMiddleware::class, CsrfMiddleware::class];
$adminPost = [AuthMiddleware::class, AdminRole::class, CsrfMiddleware::class];

$router->get('/admin', [DashboardController::class, 'index'], $auth);

$router->get('/admin/formations', [FormationsController::class, 'index'], $auth);
$router->get('/admin/formations/create', [FormationsController::class, 'create'], [AuthMiddleware::class, AdminRole::class]);
$router->post('/admin/formations', [FormationsController::class, 'store'], $adminPost);
$router->get('/admin/formations/{id}/edit', [FormationsController::class, 'edit'], [AuthMiddleware::class, AdminRole::class]);
$router->post('/admin/formations/{id}', [FormationsController::class, 'update'], $adminPost);
$router->post('/admin/formations/{id}/toggle', [FormationsController::class, 'toggle'], $adminPost);
$router->post('/admin/formations/{id}/delete', [FormationsController::class, 'destroy'], $adminPost);
$router->post('/admin/formations/{id}/archive', [FormationsController::class, 'archive'], $adminPost);
$router->post('/admin/formations/{id}/announce', [FormationsController::class, 'announce'], $adminPost);

// Offres d'emploi + candidatures (réservé admin et super admin)
$adminGet = [AuthMiddleware::class, AdminRole::class];
$router->get('/admin/emplois', [JobOffersController::class, 'index'], $adminGet);
$router->get('/admin/emplois/create', [JobOffersController::class, 'create'], $adminGet);
$router->post('/admin/emplois', [JobOffersController::class, 'store'], $adminPost);
$router->post('/admin/emplois/reminders/run', [JobOffersController::class, 'runReminders'], $adminPost);
// Vue globale des candidatures + exports (routes fixes AVANT candidatures/{id})
$router->get('/admin/emplois/candidatures', [JobApplicationsController::class, 'indexAll'], $adminGet);
$router->get('/admin/emplois/candidatures/export', [JobApplicationsController::class, 'exportAll'], $adminGet);
$router->get('/admin/emplois/candidatures/pdf', [JobApplicationsController::class, 'exportAllPdf'], $adminGet);
$router->get('/admin/emplois/candidatures/{id}', [JobApplicationsController::class, 'show'], $adminGet);
$router->post('/admin/emplois/candidatures/{id}/status', [JobApplicationsController::class, 'updateStatus'], $adminPost);
$router->get('/admin/emplois/candidatures/{id}/cv', [JobApplicationsController::class, 'cvPage'], $adminGet);
$router->get('/admin/emplois/candidatures/{id}/cv/fichier', [JobApplicationsController::class, 'cv'], $adminGet);
$router->get('/admin/emplois/{id}/edit', [JobOffersController::class, 'edit'], $adminGet);
$router->post('/admin/emplois/{id}/images', [JobOffersController::class, 'uploadImages'], $adminPost);
$router->post('/admin/emplois/{id}/images/{img}/main', [JobOffersController::class, 'setMainImage'], $adminPost);
$router->post('/admin/emplois/{id}/images/{img}/delete', [JobOffersController::class, 'deleteImage'], $adminPost);
$router->post('/admin/emplois/{id}', [JobOffersController::class, 'update'], $adminPost);
$router->post('/admin/emplois/{id}/publish', [JobOffersController::class, 'publish'], $adminPost);
$router->post('/admin/emplois/{id}/archive', [JobOffersController::class, 'archive'], $adminPost);
$router->post('/admin/emplois/{id}/delete', [JobOffersController::class, 'destroy'], $adminPost);
$router->post('/admin/emplois/{id}/announce', [JobOffersController::class, 'announce'], $adminPost);
$router->get('/admin/emplois/{id}/candidatures', [JobApplicationsController::class, 'index'], $adminGet);
$router->get('/admin/emplois/{id}/candidatures/export', [JobApplicationsController::class, 'export'], $adminGet);
$router->get('/admin/emplois/{id}/candidatures/pdf', [JobApplicationsController::class, 'exportPdf'], $adminGet);

$router->get('/admin/archives', [ArchivesController::class, 'index'], $auth);
// Archives des offres d'emploi (routes fixes AVANT archives/{id})
$router->get('/admin/archives/emplois/{id}', [ArchivesController::class, 'showOffer'], $adminGet);
$router->get('/admin/archives/emplois/{id}/export', [ArchivesController::class, 'exportOffer'], $adminGet);
$router->get('/admin/archives/emplois/{id}/pdf', [ArchivesController::class, 'exportOfferPdf'], $adminGet);
$router->post('/admin/archives/emplois/{id}/restore', [ArchivesController::class, 'restoreOffer'], $adminPost);
$router->get('/admin/archives/{id}', [ArchivesController::class, 'show'], $auth);
$router->post('/admin/archives/{id}/restore', [ArchivesController::class, 'restore'], $adminPost);

$router->get('/admin/inscriptions', [InscriptionsController::class, 'index'], $auth);
$router->get('/admin/inscriptions/export', [InscriptionsController::class, 'export'], [AuthMiddleware::class, AdminRole::class]);
$router->get('/admin/inscriptions/{id}', [InscriptionsController::class, 'show'], $auth);
$router->post('/admin/inscriptions/{id}/status', [InscriptionsController::class, 'updateStatus'], $authPost);
$router->post('/admin/inscriptions/{id}/validate-payment', [InscriptionsController::class, 'validatePayment'], $authPost);
$router->post('/admin/inscriptions/{id}/send-mail', [InscriptionsController::class, 'sendMail'], $authPost);
$router->get('/admin/inscriptions/{id}/proof', [InscriptionsController::class, 'proof'], $auth);

$router->get('/admin/messages', [MessagesController::class, 'index'], $auth);
$router->get('/admin/messages/{id}', [MessagesController::class, 'show'], $auth);
$router->post('/admin/messages/{id}/status', [MessagesController::class, 'updateStatus'], $authPost);
$router->post('/admin/messages/{id}/delete', [MessagesController::class, 'destroy'], $authPost);

$router->get('/admin/automations', [AutomationsController::class, 'index'], [AuthMiddleware::class, AdminRole::class]);
$router->post('/admin/automations', [AutomationsController::class, 'update'], $adminPost);
$router->post('/admin/automations/test-mail', [AutomationsController::class, 'testMail'], $adminPost);
$router->post('/admin/automations/logs/purge-failed', [AutomationsController::class, 'purgeFailedLogs'], $adminPost);
$router->get('/admin/automations/preview/{template}', [AutomationsController::class, 'preview'], [AuthMiddleware::class, AdminRole::class]);
$router->get('/admin/automations/templates/{name}/edit', [AutomationsController::class, 'editTemplate'], [AuthMiddleware::class, AdminRole::class]);
$router->post('/admin/automations/templates/{name}', [AutomationsController::class, 'saveTemplate'], $adminPost);
$router->post('/admin/automations/templates/{name}/reset', [AutomationsController::class, 'resetTemplate'], $adminPost);

$router->get('/admin/users', [UsersController::class, 'index'], [AuthMiddleware::class, SuperAdminRole::class]);
$router->get('/admin/users/create', [UsersController::class, 'create'], [AuthMiddleware::class, SuperAdminRole::class]);
$router->post('/admin/users', [UsersController::class, 'store'], [AuthMiddleware::class, SuperAdminRole::class, CsrfMiddleware::class]);
$router->get('/admin/users/{id}/edit', [UsersController::class, 'edit'], [AuthMiddleware::class, SuperAdminRole::class]);
$router->post('/admin/users/{id}', [UsersController::class, 'update'], [AuthMiddleware::class, SuperAdminRole::class, CsrfMiddleware::class]);
$router->post('/admin/users/{id}/delete', [UsersController::class, 'destroy'], [AuthMiddleware::class, SuperAdminRole::class, CsrfMiddleware::class]);

$router->get('/admin/surveillance', [SurveillanceController::class, 'index'], [AuthMiddleware::class, SuperAdminRole::class]);

$router->get('/admin/settings', [SettingsController::class, 'index'], [AuthMiddleware::class, AdminRole::class]);
$router->post('/admin/settings', [SettingsController::class, 'update'], $adminPost);

// Racine -> admin
$router->get('/', [AuthController::class, 'root']);

try {
    $router->dispatch($request);
} catch (Throwable $e) {
    error_log($e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (Env::bool('APP_DEBUG')) {
        throw $e;
    }
    if ($isApi) {
        Response::json(['error' => 'Erreur interne du serveur'], 500);
    }
    Response::abort(500, 'Erreur interne du serveur');
}
