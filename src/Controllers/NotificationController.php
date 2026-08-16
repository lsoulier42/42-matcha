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
    private const LABELS = [
        'like' => 'vous a liké',
        'visit' => 'a consulté votre profil',
        'message' => 'vous a envoyé un message',
        'match' => 'vous a liké en retour — c\'est un match !',
        'unlike' => 'a retiré son like',
    ];

    public function __construct(
        private Twig $twig,
        private NotificationService $notifications
    ) {
    }

    public function index(Request $request, Response $response): Response
    {
        $userId = (int) $_SESSION['user_id'];
        $items = $this->notifications->list($userId);

        foreach ($items as &$item) {
            $item['label'] = self::LABELS[$item['type']] ?? 'nouvelle activité';
            $item['created_display'] = date('d/m/Y à H:i', strtotime((string) $item['created_at']));
            $item['avatar'] = $item['avatar'] ?? null;
        }
        unset($item);

        // Consultation = marquées comme lues.
        $this->notifications->markAllRead($userId);

        return $this->twig->render($response, 'notifications/index.html.twig', [
            'items' => $items,
        ]);
    }
}
