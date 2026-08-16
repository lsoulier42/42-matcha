<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\HomeController;
use App\Controllers\ProfileController;
use App\Controllers\SearchController;
use App\Controllers\SuggestController;
use App\Middleware\AuthMiddleware;
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

    // ---------- Privé (session requise) ----------
    $app->group('', function ($app): void {
        $app->get('/profile', [ProfileController::class, 'show']);
        $app->post('/profile', [ProfileController::class, 'update']);
        $app->post('/profile/location', [ProfileController::class, 'updateLocation']);
        $app->post('/profile/photo', [ProfileController::class, 'uploadPhoto']);
        $app->post('/profile/photo/{id}/profile', [ProfileController::class, 'setProfilePhoto']);
        $app->post('/profile/photo/{id}/delete', [ProfileController::class, 'deletePhoto']);
        $app->post('/profile/tags', [ProfileController::class, 'addTag']);
        $app->post('/profile/tags/{id}/delete', [ProfileController::class, 'removeTag']);
        $app->get('/profile/visits', [ProfileController::class, 'visits']);
        $app->get('/profile/likes', [ProfileController::class, 'likes']);

        // Suggestions intelligentes + recherche avancée
        $app->get('/suggestions', [SuggestController::class, 'index']);
        $app->get('/search', [SearchController::class, 'index']);

        // Autocomplétion des tags existants (AJAX)
        $app->get('/api/tags', [ProfileController::class, 'apiTags']);
    })->add(AuthMiddleware::class);
};
