<?php

declare(strict_types=1);

namespace App\Repository;

use App\Db\Query;

/**
 * "Fake account" reports (one report per pair).
 */
final class ReportRepository
{
    public function __construct(private Query $db)
    {
    }

    public function add(int $reporterId, int $reportedId, string $reason): void
    {
        $this->db->run(
            'INSERT IGNORE INTO reports (reporter_id, reported_id, reason) VALUES (?, ?, ?)',
            [$reporterId, $reportedId, $reason]
        );
    }
}
