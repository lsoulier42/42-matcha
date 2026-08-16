<?php

declare(strict_types=1);

namespace App\Repository;

use App\Db\Query;

/**
 * Historique des visites de profils (dernière visite par paire).
 */
final class VisitRepository
{
    public function __construct(private Query $db)
    {
    }

    /** Enregistre (ou met à jour) la visite d'un profil. */
    public function record(int $visitorId, int $visitedId): void
    {
        $this->db->run(
            'INSERT INTO visits (visitor_id, visited_id, viewed_at)
             VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE viewed_at = NOW()',
            [$visitorId, $visitedId]
        );
    }

    /** « Qui a consulté mon profil » avec le visiteur et sa photo. */
    public function listVisitors(int $visitedId): array
    {
        return $this->db->fetchAll(
            'SELECT u.id, u.username, u.prenom, u.ville, u.note_popularite, u.birthdate,
                    v.viewed_at, p.path AS photo
             FROM visits v
             JOIN users u ON u.id = v.visitor_id
             LEFT JOIN photos p ON p.user_id = u.id AND p.is_profile = 1
             WHERE v.visited_id = ?
             ORDER BY v.viewed_at DESC',
            [$visitedId]
        );
    }
}
