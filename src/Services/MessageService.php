<?php

declare(strict_types=1);

namespace App\Services;

use App\Db\Query;

/**
 * Chat réservé aux utilisateurs « connectés » (like mutuel, section 3.6).
 * Un unlike ou un blocage coupe réellement le chat côté serveur :
 * l'envoi est refusé sans like mutuel et sans blocage.
 */
final class MessageService
{
    public function __construct(
        private Query $db,
        private NotificationService $notifications
    ) {
    }

    /** Le chat est-il autorisé entre a et b ? (like mutuel + aucun blocage) */
    public function canChat(int $a, int $b): bool
    {
        if ($a === $b) {
            return false;
        }
        $blocked = $this->db->value(
            'SELECT id FROM blocks WHERE (blocker_id = ? AND blocked_id = ?) OR (blocker_id = ? AND blocked_id = ?)',
            [$a, $b, $b, $a]
        );
        if ($blocked !== null) {
            return false;
        }
        $mutual = $this->db->value(
            'SELECT l1.id FROM likes l1
             JOIN likes l2 ON l2.from_user_id = l1.to_user_id AND l2.to_user_id = l1.from_user_id
             WHERE l1.from_user_id = ? AND l1.to_user_id = ?',
            [$a, $b]
        );
        return $mutual !== null;
    }

    /** Liste des conversations (matchs) avec dernier message et non-lus. */
    public function conversations(int $userId): array
    {
        return $this->db->fetchAll(
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
    }

    /** Historique du fil (optionnellement après un id, pour le polling). */
    public function history(int $userId, int $otherId, ?int $afterId = null): array
    {
        if ($afterId !== null) {
            return $this->db->fetchAll(
                'SELECT m.id, m.from_user_id, m.content, m.sent_at
                 FROM messages m
                 WHERE ((m.from_user_id = ? AND m.to_user_id = ?) OR (m.from_user_id = ? AND m.to_user_id = ?))
                   AND m.id > ?
                 ORDER BY m.id ASC',
                [$userId, $otherId, $otherId, $userId, $afterId]
            );
        }
        return $this->db->fetchAll(
            'SELECT m.id, m.from_user_id, m.content, m.sent_at
             FROM messages m
             WHERE (m.from_user_id = ? AND m.to_user_id = ?) OR (m.from_user_id = ? AND m.to_user_id = ?)
             ORDER BY m.id ASC',
            [$userId, $otherId, $otherId, $userId]
        );
    }

    /** Infos publiques d'un utilisateur (pour l'en-tête de discussion). */
    public function userInfo(int $userId): ?array
    {
        return $this->db->fetch(
            'SELECT u.id, u.username, u.prenom, u.ville, u.derniere_connexion, p.path AS avatar
             FROM users u
             LEFT JOIN photos p ON p.user_id = u.id AND p.is_profile = 1
             WHERE u.id = ?',
            [$userId]
        );
    }

    /** Envoie un message. Retourne l'id créé, ou null si refusé
     * (pas de like mutuel, blocage, contenu invalide).
     */
    public function send(int $from, int $to, string $content): ?int
    {
        $content = trim($content);
        if ($content === '' || mb_strlen($content) > 2000) {
            return null;
        }
        if (!$this->canChat($from, $to)) {
            return null;
        }

        $id = $this->db->insert('messages', [
            'from_user_id' => $from,
            'to_user_id' => $to,
            'content' => $content,
        ]);
        $this->notifications->notify($to, 'message', $from);
        return $id;
    }

    /** Marque comme lus les messages reçus de $otherId. */
    public function markRead(int $userId, int $otherId): void
    {
        $this->db->run(
            'UPDATE messages SET read_at = NOW()
             WHERE from_user_id = ? AND to_user_id = ? AND read_at IS NULL',
            [$otherId, $userId]
        );
    }

    /** Nombre total de messages non lus. */
    public function unreadCount(int $userId): int
    {
        return (int) $this->db->value(
            'SELECT COUNT(*) FROM messages WHERE to_user_id = ? AND read_at IS NULL',
            [$userId]
        );
    }
}
