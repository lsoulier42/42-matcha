<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\NotificationService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/**
 * Notifications temps réel (section 3.7) : les 5 événements (like,
 * visite, message, match, unlike), compteur global de non-lues,
 * marquage lu après consultation.
 */
final class NotificationController
{
    public function __construct(
        private Twig $twig,
        private NotificationService $notifications
    ) {
    }

    public function index(Request $request, Response $response): Response
    {
        $userId = (int) $_SESSION['user_id'];

        // Consultation = marquées comme lues.
        $items = $this->notifications->list($userId);
        $this->notifications->markAllRead($userId);

        return $this->twig->render($response, 'notifications/index.html.twig', [
            'items' => $items,
        ]);
    }
}
