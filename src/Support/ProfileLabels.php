<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Inclusive gender and sexual-orientation labels — single source of truth
 * for templates (selects, public profile). DB stores the machine keys;
 * these maps hold the human-readable French labels.
 */
final class ProfileLabels
{
    /**
     * Identité de genre (genre vécu — une personne trans choisit son genre
     * vécu ; un indicateur « personne trans » optionnel est un chantier à part).
     */
    public const GENRES = [
        'homme'       => 'Homme',
        'femme'       => 'Femme',
        'non-binaire' => 'Non binaire',
        'agenre'      => 'Agenre',
        'xénogenre'   => 'Xénogenre',
        'genre-fluide' => 'Genre fluide',
        'autre'       => 'Autre / préfère ne pas préciser',
    ];

    /**
     * Orientation sexuelle. 'homo' (Homosexuel·le) reste en base pour les
     * anciennes données du seed ; il est filtré du select d'édition.
     */
    public const ORIENTATIONS = [
        'hetero'        => 'Hétérosexuel·le',
        'homo'          => 'Homosexuel·le',
        'gay'           => 'Gay',
        'lesbienne'     => 'Lesbienne',
        'bi'            => 'Bisexuel·le',
        'pan'           => 'Pansexuel·le',
        'asexuel'       => 'Asexuel·le',
        'demisexuel'    => 'Demisexuel·le',
        'questionnement' => 'En questionnement',
        'autre'         => 'Autre / préfère ne pas préciser',
    ];

    public static function genderLabel(?string $genre): string
    {
        return self::GENRES[$genre ?? ''] ?? 'Non renseigné';
    }

    public static function orientationLabel(?string $orientation): string
    {
        if ($orientation === null || $orientation === '') {
            return 'Bisexuel·le par défaut';
        }
        return self::ORIENTATIONS[$orientation] ?? 'Bisexuel·le par défaut';
    }
}
