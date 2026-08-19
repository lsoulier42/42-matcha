<?php

declare(strict_types=1);

namespace App\Middleware;

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
                $response = new SlimResponse(401);
                $response->getBody()->write(json_encode(['error' => 'non_authentifie'], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
            }
            return (new SlimResponse(302))->withHeader('Location', '/auth/login');
        }

        return $handler->handle($request);
    }
}
