<?php

declare(strict_types=1);

use App\Controllers\ApiController;
use App\Controllers\AppointmentController;
use App\Controllers\AuthController;
use App\Controllers\ChatController;
use App\Controllers\HomeController;
use App\Controllers\MapController;
use App\Controllers\NotificationController;
use App\Controllers\ProfileController;
use App\Controllers\SearchController;
use App\Controllers\SuggestController;
use App\Controllers\UserController;
use App\Middleware\AuthMiddleware;
use Slim\App;

/*
 * Application route definitions.
 * Controllers are resolved by the php-di container (autowiring).
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

    // Logout: CSRF-protected POST, "one-click" from the layout.
    $app->post('/logout', [AuthController::class, 'logout']);

    // ---------- Private (session required) ----------
    $app->group('', function ($app): void {
        $app->get('/profile', [ProfileController::class, 'show']);
        $app->post('/profile', [ProfileController::class, 'update']);
        $app->post('/profile/location', [ProfileController::class, 'updateLocation']);
        $app->post('/profile/photo', [ProfileController::class, 'uploadPhoto']);
        $app->post('/profile/photo/{id}/profile', [ProfileController::class, 'setProfilePhoto']);
        $app->post('/profile/photo/{id}/delete', [ProfileController::class, 'deletePhoto']);
        $app->post('/profile/photo/{id}/rotate', [ProfileController::class, 'rotatePhoto']);
        $app->post('/profile/photo/{id}/filter', [ProfileController::class, 'filterPhoto']);
        $app->post('/profile/photo/{id}/crop', [ProfileController::class, 'cropPhoto']);
        $app->post('/profile/tags', [ProfileController::class, 'addTag']);
        $app->post('/profile/tags/{id}/delete', [ProfileController::class, 'removeTag']);
        $app->get('/profile/visits', [ProfileController::class, 'visits']);
        $app->get('/profile/likes', [ProfileController::class, 'likes']);

        // Smart suggestions + advanced search
        $app->get('/suggestions', [SuggestController::class, 'index']);
        $app->get('/search', [SearchController::class, 'index']);

        // Profile viewing: like/unlike, blocking, reporting
        $app->get('/user/{id}', [UserController::class, 'show']);
        $app->post('/user/{id}/like', [UserController::class, 'like']);
        $app->post('/user/{id}/unlike', [UserController::class, 'unlike']);
        $app->post('/user/{id}/block', [UserController::class, 'block']);
        $app->post('/user/{id}/unblock', [UserController::class, 'unblock']);
        $app->post('/user/{id}/report', [UserController::class, 'report']);

        // Real-time chat (restricted to matches)
        $app->get('/messages', [ChatController::class, 'index']);
        $app->get('/messages/{id}', [ChatController::class, 'show']);
        $app->post('/messages/{id}', [ChatController::class, 'send']);

        // Real-time notifications (the 5 events)
        $app->get('/notifications', [NotificationController::class, 'index']);

        // Bonus: interactive map + appointments between matches
        $app->get('/map', [MapController::class, 'index']);
        $app->get('/appointments', [AppointmentController::class, 'index']);
        $app->post('/appointments', [AppointmentController::class, 'create']);
        $app->post('/appointments/{id}/delete', [AppointmentController::class, 'delete']);

        // AJAX polling: global badges + open chat thread
        $app->get('/api/poll', [ApiController::class, 'poll']);
        $app->get('/api/messages/{id}', [ChatController::class, 'apiHistory']);

        // Existing tag autocomplete (AJAX)
        $app->get('/api/tags', [ProfileController::class, 'apiTags']);
    })->add(AuthMiddleware::class);
};
