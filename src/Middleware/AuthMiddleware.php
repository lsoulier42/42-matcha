<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Support\Http;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

/**
 * Private access: requires a server-side authenticated session.
 * Pages → redirect to login; /api/* → 401 JSON.
 */
final class AuthMiddleware implements MiddlewareInterface
{
    public function process(Request $request, RequestHandler $handler): Response
    {
        $userId = $_SESSION['user_id'] ?? null;

        if ($userId === null) {
            $path = $request->getUri()->getPath();
            if (str_starts_with($path, '/api')) {
                return Http::json(new SlimResponse(401), ['error' => 'non_authentifie'], 401);
            }
            return (new SlimResponse(302))->withHeader('Location', '/auth/login');
        }

        $request = $request->withAttribute('user_id', (int) $userId);
        return $handler->handle($request);
    }
}
