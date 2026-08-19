<?php

declare(strict_types=1);

namespace App\Services;

use App\Repository\NotificationRepository;

/**
 * Real-time notifications (section 3.7): like received, profile visit,
 * message received, like back (match), unlike. The delivery latency
 * (≤ 10 s) is guaranteed by AJAX polling — see /api/poll.
 */
final class NotificationService
{
    public function __construct(private NotificationRepository $notifications)
    {
    }

    /** Creates a notification for $userId (actorId = the event actor). */
    public function notify(int $userId, string $type, ?int $actorId = null): void
    {
        if ($userId === $actorId) {
            return;
        }
        $this->notifications->create($userId, $type, $actorId);
    }

    /** Unread notification count. */
    public function unreadCount(int $userId): int
    {
        return $this->notifications->unreadCount($userId);
    }

    /** Marks all notifications as read. */
    public function markAllRead(int $userId): void
    {
        $this->notifications->markAllRead($userId);
    }

    /**
     * Recent notifications list, with the actor.
     * Notifications from blocked users are excluded.
     */
    public function list(int $userId, int $limit = 50): array
    {
        return $this->notifications->list($userId, $limit);
    }

    /** Deletes unread notifications from a given actor (unlike/block). */
    public function clearUnreadFrom(int $userId, int $actorId): void
    {
        $this->notifications->clearUnreadFrom($userId, $actorId);
    }
}
