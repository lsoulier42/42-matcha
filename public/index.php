<?php

declare(strict_types=1);

use DI\ContainerBuilder;
use Slim\Factory\AppFactory;

$rootDir = dirname(__DIR__);

require $rootDir . '/vendor/autoload.php';

// .env local (hors git) — optionnel en production
Dotenv\Dotenv::createImmutable($rootDir)->safeLoad();

$isDev = ($_ENV['APP_ENV'] ?? 'dev') === 'dev';

// Zéro erreur/warning/notice : tout est tracé en dev, masqué en prod.
error_reporting(E_ALL);
ini_set('display_errors', $isDev ? '1' : '0');
ini_set('display_startup_errors', $isDev ? '1' : '0');
ini_set('log_errors', '1');
if (!$isDev) {
    ini_set('error_log', $rootDir . '/var/logs/php-error.log');
}

$containerBuilder = new ContainerBuilder();
$containerBuilder->addDefinitions($rootDir . '/config/definitions.php');
if (!$isDev) {
    $containerBuilder->enableCompilation($rootDir . '/var/cache/di');
}
$container = $containerBuilder->build();

AppFactory::setContainer($container);
$app = AppFactory::create();

$settings = $container->get('settings');

/*
 * Ordre d'exécution des middlewares (onion : le dernier ajouté s'exécute en premier) :
 *   1. ErrorMiddleware        (attrape tout Throwable)
 *   2. SessionMiddleware      (session PHP démarrée avant tout)
 *   3. CsrfMiddleware         (génère le jeton, valide les POST)
 *   4. ViewGlobalsMiddleware  (variables Twig : utilisateur courant, CSRF, badges)
 *   5. RoutingMiddleware
 *   6. BodyParsingMiddleware  (parsing JSON pour l'AJAX)
 */
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->add(new App\Middleware\ViewGlobalsMiddleware($container->get(Slim\Views\Twig::class), $settings));
$app->add(new App\Middleware\CsrfMiddleware());
$app->add(new App\Middleware\SessionMiddleware($settings['session']));
$app->addErrorMiddleware($settings['app']['debug'], true, true);

// Routes
(require $rootDir . '/routes.php')($app);

$app->run();
