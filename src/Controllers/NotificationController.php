<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\NotificationService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/**
 * Real-time notifications (section 3.7): the 5 events (like,
 * visit, message, match, unlike), global unread counter,
 * marked read after viewing.
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
        $userId = $request->getAttribute('user_id');

        // Viewing = mark all as read.
        $items = $this->notifications->list($userId);
        $this->notifications->markAllRead($userId);

        return $this->twig->render($response, 'notifications/index.html.twig', [
            'items' => $items,
        ]);
    }
}
