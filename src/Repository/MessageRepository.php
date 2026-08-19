<?php

declare(strict_types=1);

namespace App\Repository;

use App\Db\Query;
use App\Entity\Message;
use App\ViewModel\Conversation;
use App\ViewModel\UserProfile;

/**
 * Chat messages (restricted to matched users = mutual like).
 */
final class MessageRepository
{
    public function __construct(private Query $db)
    {
    }

    /** Conversations (matches) with last message and unread count. */
    public function conversations(int $userId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT u.id, u.username, u.prenom, u.ville, u.note_popularite, u.derniere_connexion,
                    p.path AS avatar,
                    (SELECT m.content FROM messages m
                     WHERE (m.from_user_id = u.id AND m.to_user_id = ?)
                        OR (m.from_user_id = ? AND m.to_user_id = u.id)
                     ORDER BY m.sent_at DESC, m.id DESC LIMIT 1) AS last_message,
                    (SELECT m.sent_at FROM messages m
                     WHERE (m.from_user_id = u.id AND m.to_user_id = ?)
                        OR (m.from_user_id = ? AND m.to_user_id = u.id)
                     ORDER BY m.sent_at DESC, m.id DESC LIMIT 1) AS last_message_at,
                    (SELECT COUNT(*) FROM messages m
                     WHERE m.from_user_id = u.id AND m.to_user_id = ? AND m.read_at IS NULL) AS unread
             FROM users u
             JOIN likes l1 ON l1.from_user_id = ? AND l1.to_user_id = u.id
             JOIN likes l2 ON l2.from_user_id = u.id AND l2.to_user_id = ?
             LEFT JOIN photos p ON p.user_id = u.id AND p.is_profile = 1
             WHERE u.actif = 1
               AND NOT EXISTS (SELECT 1 FROM blocks b
                   WHERE (b.blocker_id = ? AND b.blocked_id = u.id)
                      OR (b.blocker_id = u.id AND b.blocked_id = ?))
             ORDER BY last_message_at DESC',
            [
                $userId, $userId, $userId, $userId, $userId,
                $userId, $userId, $userId, $userId,
            ]
        );
        return array_map(static fn (array $row): Conversation => Conversation::fromRow($row), $rows);
    }

    /** Thread history (optionally after a given id, for polling). */
    public function history(int $userId, int $otherId, ?int $afterId = null): array
    {
        if ($afterId !== null) {
            $rows = $this->db->fetchAll(
                'SELECT m.id, m.from_user_id, m.content, m.sent_at
                 FROM messages m
                 WHERE ((m.from_user_id = ? AND m.to_user_id = ?) OR (m.from_user_id = ? AND m.to_user_id = ?))
                   AND m.id > ?
                 ORDER BY m.id ASC',
                [$userId, $otherId, $otherId, $userId, $afterId]
            );
        } else {
            $rows = $this->db->fetchAll(
                'SELECT m.id, m.from_user_id, m.content, m.sent_at
                 FROM messages m
                 WHERE (m.from_user_id = ? AND m.to_user_id = ?) OR (m.from_user_id = ? AND m.to_user_id = ?)
                 ORDER BY m.id ASC',
                [$userId, $otherId, $otherId, $userId]
            );
        }
        return array_map(static fn (array $row): Message => Message::fromRow($row), $rows);
    }

    public function send(int $fromUserId, int $toUserId, string $content): int
    {
        return $this->db->insert('messages', [
            'from_user_id' => $fromUserId,
            'to_user_id' => $toUserId,
            'content' => $content,
        ]);
    }

    /** Marks messages received from $otherId as read. */
    public function markRead(int $userId, int $otherId): void
    {
        $this->db->run(
            'UPDATE messages SET read_at = NOW()
             WHERE from_user_id = ? AND to_user_id = ? AND read_at IS NULL',
            [$otherId, $userId]
        );
    }

    public function unreadCount(int $userId): int
    {
        return (int) $this->db->value(
            'SELECT COUNT(*) FROM messages WHERE to_user_id = ? AND read_at IS NULL',
            [$userId]
        );
    }

    /** Public info for a user (conversation header). */
    public function userInfo(int $userId): ?UserProfile
    {
        $row = $this->db->fetch(
            'SELECT u.id, u.username, u.prenom, u.ville, u.derniere_connexion, u.genre, u.orientation,
                    u.bio, u.birthdate, u.note_popularite, p.path AS avatar
             FROM users u
             LEFT JOIN photos p ON p.user_id = u.id AND p.is_profile = 1
             WHERE u.id = ?',
            [$userId]
        );
        return $row === null ? null : UserProfile::fromRow($row);
    }
}
