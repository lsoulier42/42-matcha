<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\HomeController;
use Slim\App;

/*
 * Définition de toutes les routes de l'application.
 * Les contrôleurs sont résolus par le conteneur php-di (autowiring).
 */
return static function (App $app): void {
    // Public
    $app->get('/', [HomeController::class, 'index']);

    $app->group('/auth', function ($app): void {
        $app->get('/register', [AuthController::class, 'showRegister']);
        $app->post('/register', [AuthController::class, 'register']);
        $app->get('/verify/{token}', [AuthController::class, 'verify']);
        $app->get('/login', [AuthController::class, 'showLogin']);
        $app->post('/login', [AuthController::class, 'login']);
        $app->get('/forgot', [AuthController::class, 'showForgot']);
        $app->post('/forgot', [AuthController::class, 'forgot']);
        $app->get('/reset/{token}', [AuthController::class, 'showReset']);
        $app->post('/reset/{token}', [AuthController::class, 'reset']);
    });

    // Déconnexion : POST protégé CSRF, « en un clic » depuis le layout.
    $app->post('/logout', [AuthController::class, 'logout']);
};
