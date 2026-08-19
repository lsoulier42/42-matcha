<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\MessageService;
use App\Services\NotificationService;
use App\Support\Http;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Global polling endpoint (every 5 s): unread message and notification
 * badges, accessible from any page.
 * Guarantees the ≤ 10 s delivery latency required by the spec.
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
        $userId = $request->getAttribute('user_id');

        $payload = [
            'unread_messages' => $this->messages->unreadCount($userId),
            'unread_notifs' => $this->notifications->unreadCount($userId),
            'server_time' => time(),
        ];

        return Http::json($response, $payload)->withHeader('Cache-Control', 'no-store');
    }
}
