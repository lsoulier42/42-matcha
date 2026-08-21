<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\User;
use App\Repository\BlockRepository;
use App\Repository\TagRepository;
use App\Repository\UserRepository;
use App\ViewModel\ProfileCard;

/**
 * "Smart" suggestion algorithm (section 3.3 of the spec).
 *
 * Orientation compatibility (cross-checked):
 *   - NULL orientation = bisexual by default (spec rule);
 *   - hetero → opposite gender, homo → same gender, bi → all;
 *   - "other" gender: only bi/NULL profiles accept that gender.
 * A profile is suggested if I could be interested in them AND
 * they could be interested in me.
 *
 * Score (higher = more relevant):
 *   1. Same geographic zone (< 10 km or same city): +100;
 *   2. Shared tags: +2 per tag;
 *   3. Popularity score: + score (0–10).
 *
 * Exclusions: self, inactive/unverified accounts, blocked in both
 * directions.
 *
 * SQL is centralised in UserRepository::suggestCandidates; this service
 * builds the criteria, applies orientation compatibility, scoring
 * and sorting.
 */
final class MatchingService
{
    public function __construct(
        private UserRepository $users,
        private TagRepository $tags,
        private BlockRepository $blocks,
        private array $settings
    ) {
    }

    /**
     * @param array $filters age_min, age_max, popularity_min, ville, tags[]
     * @return ProfileCard[]
     */
    public function suggest(int $userId, array $filters = [], string $sort = 'score'): array
    {
        $me = $this->users->findById($userId);
        if ($me === null) {
            return [];
        }

        $blockedIds = $this->blocks->idsInvolving($userId);
        $myTagIds = $this->tags->idsForUser($userId);

        [$where, $params] = $this->buildWhere($me, $filters, $blockedIds);
        $rows = $this->users->suggestCandidates($where, $params, $myTagIds);
        $candidates = [];
        foreach ($rows as $row) {
            if (!$this->orientationCompatible($me, $row)) {
                continue;
            }
            $row['shared_tags'] = (int) $row['shared_tags'];
            $row['distance_km'] = $this->distanceKm($me, $row);
            $row['same_zone'] = $this->sameZone($me, $row);
            $row['score'] = ($row['same_zone'] ? 100 : 0) + 2 * $row['shared_tags'] + (float) $row['note_popularite'];
            $row['age'] = $this->ageOf((string) ($row['birthdate'] ?? ''));
            $candidates[] = $row;
        }

        $sorted = $this->sort($candidates, $sort);

        return array_map(static fn (array $row): ProfileCard => ProfileCard::fromRow($row), $sorted);
    }

    /** Mandatory spec rule: unset orientation = bisexual. */
    private function orientationCompatible(User $me, array $other): bool
    {
        if (!$this->covers($me->orientation, $me->genre, $other['genre'] ?? null)) {
            return false;
        }
        if (!$this->covers($other['orientation'] ?? null, $other['genre'] ?? null, $me->genre)) {
            return false;
        }
        return true;
    }

    /** Does $orientation cover the $target genre? (NULL or open orientations = all) */
    private function covers(?string $orientation, ?string $ownGenre, ?string $targetGenre): bool
    {
        $target = self::genreBucket($targetGenre);
        if ($target === null) {
            return false; // unknown gender → no suggestion
        }
        $kind = self::orientationKind($orientation);
        if ($kind === 'all') {
            return true; // bi, pan, asexuel, demisexuel, questionnement, autre, NULL
        }
        $own = self::genreBucket($ownGenre);
        if ($own === null) {
            return false; // without a gender, hetero/gay/lesbienne cannot match
        }
        if ($kind === 'same') {
            return $own === $target; // gay / lesbienne (et legacy 'homo')
        }
        return ($own === 'masculin' && $target === 'féminin')
            || ($own === 'féminin' && $target === 'masculin'); // hétéro : binaire opposé
    }

    /**
     * Bucket de matching pour un genre : 'masculin', 'féminin' ou 'non-binaire'.
     * Tous les genres non binaires (dont 'autre') partagent le même bucket
     * pour la compatibilité d'orientation.
     */
    private static function genreBucket(?string $genre): ?string
    {
        return match ($genre) {
            'homme' => 'masculin',
            'femme' => 'féminin',
            'non-binaire', 'agenre', 'xénogenre', 'genre-fluide', 'autre' => 'non-binaire',
            default => null,
        };
    }

    /** Catégorie de matching d'une orientation : 'opposite' | 'same' | 'all'. */
    private static function orientationKind(?string $orientation): string
    {
        return match ($orientation) {
            'hetero' => 'opposite',
            'homo', 'gay', 'lesbienne' => 'same',
            default => 'all',
        };
    }

    /** Builds WHERE clause + parameters (filters: age, popularity, city, tags). */
    private function buildWhere(User $me, array $filters, array $blockedIds): array
    {
        $where = ['u.id <> ?', 'u.actif = 1', 'u.email_verifie = 1'];
        $params = [$me->id];

        if ($blockedIds !== []) {
            $where[] = 'u.id NOT IN (' . implode(',', array_fill(0, count($blockedIds), '?')) . ')';
            $params = [...$params, ...$blockedIds];
        }

        // Age range (convert age → birthdate).
        $ageMin = isset($filters['age_min']) && $filters['age_min'] !== '' ? max(16, min(100, (int) $filters['age_min'])) : null;
        $ageMax = isset($filters['age_max']) && $filters['age_max'] !== '' ? max(16, min(100, (int) $filters['age_max'])) : null;
        if ($ageMin !== null) {
            $where[] = 'u.birthdate <= ?';
            $params[] = date('Y-m-d', (int) (time() - $ageMin * 365.25 * 86400));
        }
        if ($ageMax !== null) {
            $where[] = 'u.birthdate >= ?';
            $params[] = date('Y-m-d', (int) (time() - ($ageMax + 1) * 365.25 * 86400));
        }

        // Minimum popularity.
        $popMin = isset($filters['popularity_min']) && $filters['popularity_min'] !== ''
            ? max(0, min(10, (float) $filters['popularity_min'])) : null;
        if ($popMin !== null) {
            $where[] = 'u.note_popularite >= ?';
            $params[] = $popMin;
        }

        // Location (city / district).
        $ville = isset($filters['ville']) ? trim((string) $filters['ville']) : '';
        if ($ville !== '') {
            $where[] = 'u.ville LIKE ?';
            $params[] = '%' . mb_substr($ville, 0, 120) . '%';
        }

        // Tags: at least one common tag among those requested.
        $tagNames = [];
        foreach ((array) ($filters['tags'] ?? []) as $tag) {
            $tag = mb_strtolower(trim((string) $tag));
            if ($tag !== '') {
                $tagNames[] = mb_substr($tag, 0, 30);
            }
        }
        if ($tagNames !== []) {
            $where[] = 'EXISTS (
                SELECT 1 FROM user_tags ut3
                JOIN tags t3 ON t3.id = ut3.tag_id
                WHERE ut3.user_id = u.id AND t3.name IN ('
                . implode(',', array_fill(0, count($tagNames), '?')) . '))';
            $params = [...$params, ...$tagNames];
        }

        return [$where, $params]; // merged by the repository (AND)
    }

    /** Haversine distance in km (null if coordinates missing). */
    private function distanceKm(User $a, array $b): ?float
    {
        if ($a->lat === null || $a->lng === null || $b['lat'] === null || $b['lng'] === null) {
            return null;
        }
        $lat1 = deg2rad($a->lat);
        $lat2 = deg2rad((float) $b['lat']);
        $dlat = $lat2 - $lat1;
        $dlng = deg2rad((float) $b['lng'] - $a->lng);
        $h = sin($dlat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dlng / 2) ** 2;
        return round(6371.0 * 2 * atan2(sqrt($h), sqrt(1 - $h)), 1);
    }

    /** Same zone: < 10 km or same city (case-insensitive). */
    private function sameZone(User $a, array $b): bool
    {
        $dist = $this->distanceKm($a, $b);
        if ($dist !== null) {
            return $dist < (float) $this->settings['matching']['zone_radius_km'];
        }
        return $a->ville !== null && $b['ville'] !== null
            && mb_strtolower($a->ville) === mb_strtolower((string) $b['ville']);
    }

    private function ageOf(string $birthdate): ?int
    {
        if ($birthdate === '') {
            return null;
        }
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $birthdate);
        return $dt === false ? null : (int) $dt->diff(new \DateTimeImmutable('now'))->y;
    }

    /** Sorting: score (default), age, location, popularity, shared tags. */
    private function sort(array $rows, string $sort): array
    {
        switch ($sort) {
            case 'age':
                usort($rows, fn (array $a, array $b): int => ($a['age'] ?? 999) <=> ($b['age'] ?? 999));
                break;
            case 'location':
                usort($rows, function (array $a, array $b): int {
                    if ($a['distance_km'] === null) {
                        return 1;
                    }
                    if ($b['distance_km'] === null) {
                        return -1;
                    }
                    return $a['distance_km'] <=> $b['distance_km'];
                });
                break;
            case 'popularity':
                usort($rows, fn (array $a, array $b): int => (float) $b['note_popularite'] <=> (float) $a['note_popularite']);
                break;
            case 'tags':
                usort($rows, fn (array $a, array $b): int => $b['shared_tags'] <=> $a['shared_tags']);
                break;
            default:
                usort($rows, fn (array $a, array $b): int => $b['score'] <=> $a['score']);
        }
        return $rows;
    }
}
