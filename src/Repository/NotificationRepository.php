<?php

declare(strict_types=1);

namespace App\Repository;

use App\Db\Query;
use App\ViewModel\NotificationItem;

/**
 * Real-time notifications (like, visit, message, match, unlike).
 */
final class NotificationRepository
{
    public function __construct(private Query $db)
    {
    }

    public function create(int $userId, string $type, ?int $actorId): void
    {
        $this->db->insert('notifications', [
            'user_id' => $userId,
            'type' => $type,
            'actor_id' => $actorId,
        ]);
    }

    public function unreadCount(int $userId): int
    {
        return (int) $this->db->value(
            'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL',
            [$userId]
        );
    }

    public function markAllRead(int $userId): void
    {
        $this->db->run(
            'UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL',
            [$userId]
        );
    }

    /**
     * Recent notifications with the actor;
     * notifications from blocked users are excluded.
     *
     * @return NotificationItem[]
     */
    public function list(int $userId, int $limit = 50): array
    {
        $rows = $this->db->fetchAll(
            'SELECT n.id, n.type, n.actor_id, n.created_at, n.read_at,
                    a.username, a.prenom, a.ville, p.path AS avatar
             FROM notifications n
             LEFT JOIN users a ON a.id = n.actor_id
             LEFT JOIN photos p ON p.user_id = a.id AND p.is_profile = 1
             WHERE n.user_id = ?
               AND (n.actor_id IS NULL OR NOT EXISTS (
                   SELECT 1 FROM blocks b WHERE b.blocker_id = ? AND b.blocked_id = n.actor_id
               ))
             ORDER BY n.created_at DESC, n.id DESC
             LIMIT ' . (int) $limit,
            [$userId, $userId]
        );
        return array_map(static fn (array $row): NotificationItem => NotificationItem::fromRow($row), $rows);
    }

    /** Deletes unread notifications from a given actor (unlike/block). */
    public function clearUnreadFrom(int $userId, int $actorId): void
    {
        $this->db->run(
            'DELETE FROM notifications WHERE user_id = ? AND actor_id = ? AND read_at IS NULL',
            [$userId, $actorId]
        );
    }
}
