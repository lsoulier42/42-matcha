<?php

declare(strict_types=1);

namespace App\Repository;

use App\Db\Query;

/**
 * User blocks (both directions are considered).
 */
final class BlockRepository
{
    public function __construct(private Query $db)
    {
    }

    /** Does a block exist in either direction? */
    public function isBlocked(int $userId, int $otherId): bool
    {
        return $this->db->value(
            'SELECT id FROM blocks
             WHERE (blocker_id = ? AND blocked_id = ?) OR (blocker_id = ? AND blocked_id = ?)',
            [$userId, $otherId, $otherId, $userId]
        ) !== null;
    }

    public function add(int $blockerId, int $blockedId): void
    {
        $this->db->run('INSERT IGNORE INTO blocks (blocker_id, blocked_id) VALUES (?, ?)', [$blockerId, $blockedId]);
    }

    public function remove(int $blockerId, int $blockedId): void
    {
        $this->db->delete('blocks', 'blocker_id = ? AND blocked_id = ?', [$blockerId, $blockedId]);
    }

    /** IDs of users blocked with $userId (both directions). */
    public function idsInvolving(int $userId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT blocked_id AS id FROM blocks WHERE blocker_id = ?
             UNION SELECT blocker_id AS id FROM blocks WHERE blocked_id = ?',
            [$userId, $userId]
        );
        return array_map('intval', array_column($rows, 'id'));
    }
}
