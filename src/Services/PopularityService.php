<?php

declare(strict_types=1);

namespace App\Services;

use App\Repository\LikeRepository;
use App\Repository\UserRepository;

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

    /** Note d'un utilisateur (0.00–10.00). */
    public function score(int $userId): float
    {
        $user = $this->users->findById($userId);
        return $user === null ? 0.0 : $user->notePopularite;
    }
}
