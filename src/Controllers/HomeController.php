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

    /** Landing page: onboarding slides for guests, compact welcome for members. */
    public function index(Request $request, Response $response): Response
    {
        return $this->twig->render($response, 'home/index.html.twig');
    }
}
