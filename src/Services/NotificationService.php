<?php

declare(strict_types=1);

namespace App\Services;

use App\Db\Query;

/**
 * Notifications temps réel (section 3.7) : like reçu, visite de profil,
 * message reçu, like en retour (match), unlike. Le délai de réception
 * (≤ 10 s) est assuré par le polling AJAX — voir /api/poll.
 */
final class NotificationService
{
    public function __construct(private Query $db)
    {
    }

    /** Crée une notification pour $userId (actorId = l'auteur de l'événement). */
    public function notify(int $userId, string $type, ?int $actorId = null): void
    {
        if ($userId === $actorId) {
            return;
        }
        $this->db->insert('notifications', [
            'user_id' => $userId,
            'type' => $type,
            'actor_id' => $actorId,
        ]);
    }

    /** Nombre de notifications non lues. */
    public function unreadCount(int $userId): int
    {
        return (int) $this->db->value(
            'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL',
            [$userId]
        );
    }

    /** Marque toutes les notifications comme lues. */
    public function markAllRead(int $userId): void
    {
        $this->db->run(
            'UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL',
            [$userId]
        );
    }

    /**
     * Liste des notifications récentes, avec l'auteur.
     * Les notifications d'utilisateurs bloqués sont exclues.
     */
    public function list(int $userId, int $limit = 50): array
    {
        return $this->db->fetchAll(
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
    }

    /** Supprime les notifications non lues d'un acteur donné (unlike/blocage). */
    public function clearUnreadFrom(int $userId, int $actorId): void
    {
        $this->db->run(
            'DELETE FROM notifications WHERE user_id = ? AND actor_id = ? AND read_at IS NULL',
            [$userId, $actorId]
        );
    }
}
