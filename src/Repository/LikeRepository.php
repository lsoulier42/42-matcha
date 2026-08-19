<?php

declare(strict_types=1);

namespace App\Repository;

use App\Db\Query;
use App\ViewModel\ProfileCard;

/**
 * Likes (one per pair), tracked unlikes, popularity counters.
 */
final class LikeRepository
{
    public function __construct(private Query $db)
    {
    }

    public function exists(int $fromUserId, int $toUserId): bool
    {
        return $this->db->value(
            'SELECT id FROM likes WHERE from_user_id = ? AND to_user_id = ?',
            [$fromUserId, $toUserId]
        ) !== null;
    }

    public function add(int $fromUserId, int $toUserId): void
    {
        $this->db->insert('likes', ['from_user_id' => $fromUserId, 'to_user_id' => $toUserId]);
    }

    public function remove(int $fromUserId, int $toUserId): void
    {
        $this->db->delete('likes', 'from_user_id = ? AND to_user_id = ?', [$fromUserId, $toUserId]);
    }

    /** Records an unlike (feeds into the popularity formula). */
    public function recordUnlike(int $fromUserId, int $toUserId): void
    {
        $this->db->run(
            'INSERT INTO unlikes (from_user_id, to_user_id) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE created_at = NOW()',
            [$fromUserId, $toUserId]
        );
    }

    public function countReceived(int $userId): int
    {
        return (int) $this->db->value('SELECT COUNT(*) FROM likes WHERE to_user_id = ?', [$userId]);
    }

    public function countUnlikesReceived(int $userId): int
    {
        return (int) $this->db->value('SELECT COUNT(*) FROM unlikes WHERE to_user_id = ?', [$userId]);
    }

    /** Number of active matches (mutual likes still in effect). */
    public function countMatches(int $userId): int
    {
        return (int) $this->db->value(
            'SELECT COUNT(*) FROM likes l1
             JOIN likes l2 ON l1.from_user_id = l2.to_user_id AND l1.to_user_id = l2.from_user_id
             WHERE l1.to_user_id = ?',
            [$userId]
        );
    }

    /** "Who liked me": author cards with their photo. */
    public function listLikers(int $userId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT u.id, u.username, u.prenom, u.ville, u.note_popularite, u.birthdate, u.bio,
                    l.created_at, p.path AS photo
             FROM likes l
             JOIN users u ON u.id = l.from_user_id
             LEFT JOIN photos p ON p.user_id = u.id AND p.is_profile = 1
             WHERE l.to_user_id = ?
             ORDER BY l.created_at DESC',
            [$userId]
        );
        return array_map(static fn (array $row): ProfileCard => ProfileCard::fromRow($row), $rows);
    }
}
