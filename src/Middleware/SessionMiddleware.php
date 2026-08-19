<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

/**
 * Starts the PHP session with secure cookie settings
 * (HttpOnly, SameSite=Lax, optional Secure based on environment).
 * The session is opened in middleware, never in a view.
 */
final class SessionMiddleware implements MiddlewareInterface
{
    public function __construct(private array $config)
    {
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $params = [
            'lifetime' => (int) ($this->config['lifetime'] ?? 0),
            'path' => '/',
            'domain' => '',
            'secure' => (bool) ($this->config['secure'] ?? false),
            'httponly' => true,
            'samesite' => 'Lax',
        ];
        session_set_cookie_params($params);
        session_name((string) ($this->config['name'] ?? 'matcha_session'));

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        try {
            return $handler->handle($request);
        } finally {
            session_write_close();
        }
    }
}
