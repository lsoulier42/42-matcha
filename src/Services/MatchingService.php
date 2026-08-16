<?php

declare(strict_types=1);

namespace App\Services;

use App\Repository\BlockRepository;
use App\Repository\TagRepository;
use App\Repository\UserRepository;

/**
 * Algorithme de suggestions « intelligentes » (section 3.3 du sujet).
 *
 * Compatibilité d'orientation (croisée) :
 *   - orientation NULL = bisexuel par défaut (règle du sujet) ;
 *   - hetero → genre opposé, homo → même genre, bi → tous ;
 *   - genre « autre » : seul un profil bi/NULL accepte ce genre.
 * Un profil est suggéré si MOI je peux être intéressé par lui ET
 * que LUI peut être intéressé par moi.
 *
 * Score (plus élevé = plus pertinent) :
 *   1. Même zone géographique (distance < 10 km ou même ville) : +100 ;
 *   2. Tags partagés : +2 par tag ;
 *   3. Note de popularité : + note (0–10).
 *
 * Exclusions : soi-même, comptes inactifs/non vérifiés, bloqués des deux côtés.
 *
 * Le SQL est centralisé dans UserRepository::suggestCandidates ; ce service
 * construit les critères, applique la compatibilité d'orientation, le score
 * et le tri.
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
            $candidates[] = $this->decorate($row);
        }

        return $this->sort($candidates, $sort);
    }

    /** Critère obligatoire du sujet : orientation non renseignée = bisexuel. */
    private function orientationCompatible(array $me, array $other): bool
    {
        if (!$this->covers($me['orientation'] ?? null, $me['genre'] ?? null, $other['genre'] ?? null)) {
            return false;
        }
        if (!$this->covers($other['orientation'] ?? null, $other['genre'] ?? null, $me['genre'] ?? null)) {
            return false;
        }
        return true;
    }

    /** $orientation couvre-t-elle le genre $target ? (NULL = bi) */
    private function covers(?string $orientation, ?string $ownGenre, ?string $targetGenre): bool
    {
        if ($targetGenre === null || $targetGenre === 'autre' || $orientation === null || $orientation === 'bi') {
            return $targetGenre !== null; // genre inconnu → pas de suggestion
        }
        if ($ownGenre === null || $ownGenre === 'autre') {
            return false; // sans genre, hetero/homo ne peuvent pas matcher
        }
        return $orientation === 'homo' ? $ownGenre === $targetGenre : $ownGenre !== $targetGenre;
    }

    /** Construction WHERE + paramètres (filtres : âge, popularité, ville, tags). */
    private function buildWhere(array $me, array $filters, array $blockedIds): array
    {
        $where = ['u.id <> ?', 'u.actif = 1', 'u.email_verifie = 1'];
        $params = [$me['id']];

        if ($blockedIds !== []) {
            $where[] = 'u.id NOT IN (' . implode(',', array_fill(0, count($blockedIds), '?')) . ')';
            $params = [...$params, ...$blockedIds];
        }

        // Tranche d'âge (conversion âge → date de naissance).
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

        // Popularité minimale.
        $popMin = isset($filters['popularity_min']) && $filters['popularity_min'] !== ''
            ? max(0, min(10, (float) $filters['popularity_min'])) : null;
        if ($popMin !== null) {
            $where[] = 'u.note_popularite >= ?';
            $params[] = $popMin;
        }

        // Localisation (ville / quartier).
        $ville = isset($filters['ville']) ? trim((string) $filters['ville']) : '';
        if ($ville !== '') {
            $where[] = 'u.ville LIKE ?';
            $params[] = '%' . mb_substr($ville, 0, 120) . '%';
        }

        // Tags : au moins un tag commun parmi ceux demandés.
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

        return [$where, $params]; // fusionné par le repository (AND)
    }

    /** Distance Haversine en km (null si coordonnées manquantes). */
    private function distanceKm(array $a, array $b): ?float
    {
        if ($a['lat'] === null || $a['lng'] === null || $b['lat'] === null || $b['lng'] === null) {
            return null;
        }
        $lat1 = deg2rad((float) $a['lat']);
        $lat2 = deg2rad((float) $b['lat']);
        $dlat = $lat2 - $lat1;
        $dlng = deg2rad((float) $b['lng'] - (float) $a['lng']);
        $h = sin($dlat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dlng / 2) ** 2;
        return round(6371.0 * 2 * atan2(sqrt($h), sqrt(1 - $h)), 1);
    }

    /** Même zone : < 10 km ou même ville (insensible à la casse). */
    private function sameZone(array $a, array $b): bool
    {
        $dist = $this->distanceKm($a, $b);
        if ($dist !== null) {
            return $dist < (float) $this->settings['matching']['zone_radius_km'];
        }
        return $a['ville'] !== null && $b['ville'] !== null
            && mb_strtolower((string) $a['ville']) === mb_strtolower((string) $b['ville']);
    }

    private function decorate(array $row): array
    {
        $row['age'] = $this->ageOf((string) ($row['birthdate'] ?? ''));
        $row['popularity_display'] = number_format((float) $row['note_popularite'], 1, ',', ' ');
        unset($row['lat'], $row['lng']);
        return $row;
    }

    private function ageOf(string $birthdate): ?int
    {
        if ($birthdate === '') {
            return null;
        }
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $birthdate);
        return $dt === false ? null : (int) $dt->diff(new \DateTimeImmutable('now'))->y;
    }

    /** Tri : score (défaut), âge, localisation, popularité, tags communs. */
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
