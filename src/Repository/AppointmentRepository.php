<?php

declare(strict_types=1);

namespace App\Repository;

use App\Db\Query;

/**
 * Rendez-vous entre utilisateurs connectés (bonus).
 */
final class AppointmentRepository
{
    public function __construct(private Query $db)
    {
    }

    /** Rendez-vous impliquant $userId, avec l'autre participant. */
    public function listFor(int $userId): array
    {
        return $this->db->fetchAll(
            'SELECT a.id, a.title, a.description, a.location, a.start_at, a.user1_id, a.user2_id,
                    CASE WHEN a.user1_id = ? THEN u2.id ELSE u1.id END AS other_id,
                    CASE WHEN a.user1_id = ? THEN u2.prenom ELSE u1.prenom END AS other_prenom,
                    CASE WHEN a.user1_id = ? THEN u2.username ELSE u1.username END AS other_username
             FROM appointments a
             JOIN users u1 ON u1.id = a.user1_id
             JOIN users u2 ON u2.id = a.user2_id
             WHERE a.user1_id = ? OR a.user2_id = ?
             ORDER BY a.start_at ASC',
            [$userId, $userId, $userId, $userId, $userId]
        );
    }

    public function create(array $data): int
    {
        return $this->db->insert('appointments', $data);
    }

    /** Supprime un rendez-vous dont $userId est participant. */
    public function delete(int $id, int $userId): void
    {
        $this->db->delete(
            'appointments',
            'id = ? AND (user1_id = ? OR user2_id = ?)',
            [$id, $userId, $userId]
        );
    }
}
