<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\MessageService;
use App\Services\NotificationService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Endpoint de polling global (toutes les 5 s) : badges des messages
 * et des notifications non lus, visibles depuis n'importe quelle page.
 * Garantit le délai de réception ≤ 10 s exigé par le sujet.
 */
final class ApiController
{
    public function __construct(
        private MessageService $messages,
        private NotificationService $notifications
    ) {
    }

    public function poll(Request $request, Response $response): Response
    {
        $userId = (int) $_SESSION['user_id'];

        $payload = [
            'unread_messages' => $this->messages->unreadCount($userId),
            'unread_notifs' => $this->notifications->unreadCount($userId),
            'server_time' => time(),
        ];

        $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withHeader('Cache-Control', 'no-store');
    }
}
