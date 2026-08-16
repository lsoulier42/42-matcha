<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

/**
 * Protection CSRF maison : jeton généré en session et exigé
 * sur toute requête qui modifie l'état (POST/PUT/PATCH/DELETE).
 * Accepté via champ de formulaire « csrf_token » ou en-tête X-CSRF-Token (AJAX).
 */
final class CsrfMiddleware implements MiddlewareInterface
{
    public function process(Request $request, RequestHandler $handler): Response
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        $expected = $_SESSION['csrf_token'];

        $method = strtoupper($request->getMethod());
        $safe = in_array($method, ['GET', 'HEAD', 'OPTIONS'], true);

        if (!$safe) {
            $body = $request->getParsedBody();
            $sent = is_array($body) ? ($body['csrf_token'] ?? null) : null;
            if (!is_string($sent)) {
                $sent = $request->getHeaderLine('X-CSRF-Token');
            }
            if (!is_string($sent) || !hash_equals($expected, $sent)) {
                return $this->forbidden($request);
            }
        }

        return $handler->handle($request);
    }

    private function forbidden(Request $request): Response
    {
        $response = new SlimResponse(403, ['Content-Type' => 'application/json; charset=utf-8']);
        $response->getBody()->write(json_encode(['error' => 'csrf_invalide'], JSON_UNESCAPED_UNICODE));
        return $response;
    }
}
