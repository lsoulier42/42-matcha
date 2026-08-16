<?php

declare(strict_types=1);

namespace App\Services;

use App\Repository\NotificationRepository;

/**
 * Notifications temps réel (section 3.7) : like reçu, visite de profil,
 * message reçu, like en retour (match), unlike. Le délai de réception
 * (≤ 10 s) est assuré par le polling AJAX — voir /api/poll.
 */
final class NotificationService
{
    public function __construct(private NotificationRepository $notifications)
    {
    }

    /** Crée une notification pour $userId (actorId = l'auteur de l'événement). */
    public function notify(int $userId, string $type, ?int $actorId = null): void
    {
        if ($userId === $actorId) {
            return;
        }
        $this->notifications->create($userId, $type, $actorId);
    }

    /** Nombre de notifications non lues. */
    public function unreadCount(int $userId): int
    {
        return $this->notifications->unreadCount($userId);
    }

    /** Marque toutes les notifications comme lues. */
    public function markAllRead(int $userId): void
    {
        $this->notifications->markAllRead($userId);
    }

    /**
     * Liste des notifications récentes, avec l'auteur.
     * Les notifications d'utilisateurs bloqués sont exclues.
     */
    public function list(int $userId, int $limit = 50): array
    {
        return $this->notifications->list($userId, $limit);
    }

    /** Supprime les notifications non lues d'un acteur donné (unlike/blocage). */
    public function clearUnreadFrom(int $userId, int $actorId): void
    {
        $this->notifications->clearUnreadFrom($userId, $actorId);
    }
}
