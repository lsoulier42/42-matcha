<?php

declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class HomeController
{
    public function __construct(private Twig $twig)
    {
    }

    public function index(Request $request, Response $response): Response
    {
        // Déjà connecté → accueil privé (suggestions).
        if (!empty($_SESSION['user_id'])) {
            return $response->withHeader('Location', '/suggestions')->withStatus(302);
        }
        return $this->twig->render($response, 'home/index.html.twig');
    }
}
