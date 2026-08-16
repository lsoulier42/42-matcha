<?php

declare(strict_types=1);

use App\Controllers\HomeController;
use Slim\App;

/*
 * Définition de toutes les routes de l'application.
 * Les contrôleurs sont résolus par le conteneur php-di (autowiring).
 */
return static function (App $app): void {
    $app->get('/', [HomeController::class, 'index']);
};
