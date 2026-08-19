<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\Http;
use App\Support\Session;
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
        // Already logged in -> redirect to private home (suggestions).
        if (Session::userId() !== null) {
            return Http::redirect($response, '/suggestions');
        }
        return $this->twig->render($response, 'home/index.html.twig');
    }
}
