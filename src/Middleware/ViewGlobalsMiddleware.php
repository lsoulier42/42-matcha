<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\MessageService;
use App\Services\NotificationService;
use App\Support\Flash;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Views\Twig;

/**
 * Injects Twig global view variables:
 * app config, current user, CSRF token, unread counters (badges).
 */
final class ViewGlobalsMiddleware implements MiddlewareInterface
{
    public function __construct(
        private Twig $twig,
        private array $settings,
        private MessageService $messages,
        private NotificationService $notifications
    ) {
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $env = $this->twig->getEnvironment();

        $env->addGlobal('app_name', $this->settings['app']['name']);
        $env->addGlobal('app_url', $this->settings['app']['url']);

        $userId = $_SESSION['user_id'] ?? null;
        $env->addGlobal('current_user', $userId !== null ? ($_SESSION['user'] ?? null) : null);
        $env->addGlobal('csrf_token', $_SESSION['csrf_token'] ?? '');

        // Flash messages (consumed on display).
        $env->addGlobal('flash', Flash::pull());

        // Real-time badges: recalculated on every request (polling / pages).
        $unreadMessages = 0;
        $unreadNotifs = 0;
        if ($userId !== null) {
            $unreadMessages = $this->messages->unreadCount($userId);
            $unreadNotifs = $this->notifications->unreadCount($userId);
        }
        $env->addGlobal('unread_messages', $unreadMessages);
        $env->addGlobal('unread_notifs', $unreadNotifs);

        return $handler->handle($request);
    }
}
