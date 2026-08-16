<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Views\Twig;

/**
 * Injecte les variables globales des vues Twig :
 * app, utilisateur courant, jeton CSRF, compteurs de non-lus (badges).
 */
final class ViewGlobalsMiddleware implements MiddlewareInterface
{
    public function __construct(
        private Twig $twig,
        private array $settings
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

        // Badges temps réel : recalculés à chaque requête (polling / pages).
        $env->addGlobal('unread_messages', $_SESSION['unread_messages'] ?? 0);
        $env->addGlobal('unread_notifs', $_SESSION['unread_notifs'] ?? 0);

        return $handler->handle($request);
    }
}
