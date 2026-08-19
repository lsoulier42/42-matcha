<?php

declare(strict_types=1);

namespace App\Services;

use App\Repository\LikeRepository;
use App\Repository\UserRepository;

/**
 * Popularity score — documented formula (see README.md):
 *
 *   Popularity = (likes received) + 2 × (active matches) − (unlikes received)
 *
 * Capped between 0 and 10, rounded to 2 decimal places, stored in
 * users.note_popularite and recomputed on every like / unlike.
 * The same formula is used everywhere the score is displayed or sorted.
 */
final class PopularityService
{
    public function __construct(
        private LikeRepository $likes,
        private UserRepository $users
    ) {
    }

    public function recompute(int $userId): void
    {
        $likes = $this->likes->countReceived($userId);
        $unlikes = $this->likes->countUnlikesReceived($userId);
        $matches = $this->likes->countMatches($userId);

        $score = $likes + 2 * $matches - $unlikes;
        $score = max(0.0, min(10.0, (float) $score));

        $this->users->update($userId, ['note_popularite' => round($score, 2)]);
    }

    /** Score for a user (0.00–10.00). */
    public function score(int $userId): float
    {
        $user = $this->users->findById($userId);
        return $user === null ? 0.0 : $user->notePopularite;
    }
}
