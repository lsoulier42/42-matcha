<?php

declare(strict_types=1);

namespace App\Services;

use App\Db\Query;

/**
 * Note de popularité — formule documentée (voir README.md) :
 *
 *   Popularité = (likes reçus) + 2 × (matchs actifs) − (unlikes reçus)
 *
 * Plafonnée entre 0 et 10, arrondie à 2 décimales, stockée dans
 * users.note_popularite et recalculée à chaque like / unlike.
 * La même formule est utilisée partout où la note est affichée ou triée.
 */
final class PopularityService
{
    public function __construct(private Query $db)
    {
    }

    public function recompute(int $userId): void
    {
        $likes = (int) $this->db->value('SELECT COUNT(*) FROM likes WHERE to_user_id = ?', [$userId]);
        $unlikes = (int) $this->db->value('SELECT COUNT(*) FROM unlikes WHERE to_user_id = ?', [$userId]);

        // Un match = like mutuel (les deux sens existent encore).
        $matches = (int) $this->db->value(
            'SELECT COUNT(*) FROM likes l1
             JOIN likes l2 ON l1.from_user_id = l2.to_user_id AND l1.to_user_id = l2.from_user_id
             WHERE l1.to_user_id = ?',
            [$userId]
        );

        $score = $likes + 2 * $matches - $unlikes;
        $score = max(0.0, min(10.0, (float) $score));

        $this->db->update('users', ['note_popularite' => round($score, 2)], 'id = ?', [$userId]);
    }

    /** Note d'un utilisateur (0.00–10.00). */
    public function score(int $userId): float
    {
        return (float) ($this->db->value('SELECT note_popularite FROM users WHERE id = ?', [$userId]) ?? 0);
    }
}
