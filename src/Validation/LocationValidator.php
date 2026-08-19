<?php

declare(strict_types=1);

namespace App\Validation;

use App\Dto\LocationUpdateData;

/**
 * Règles de localisation : consentement GPS explicite avec coordonnées
 * valides, OU saisie manuelle obligatoire de la ville/quartier (exigence
 * du sujet pour le matching en cas de refus du GPS).
 *
 * @return array<string, string> erreurs (champ => message), vide si valide
 */
final class LocationValidator
{
    public function validate(LocationUpdateData $data): array
    {
        $gpsConsent = $data->gpsConsent;
        $ville = $data->ville ?? '';
        $lat = $data->lat;
        $lng = $data->lng;

        $v = new Validator();
        $v->length('ville', $ville, 2, 120, 'Ville / quartier');

        if ($gpsConsent) {
            if ($lat === null || $lng === null || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                $v->add('location', 'Coordonnées GPS invalides.');
            }
        } elseif ($ville === '') {
            // Exigence du sujet : refus du GPS → saisie manuelle obligatoire.
            $v->add('ville', 'La localisation manuelle (ville ou quartier) est obligatoire pour le matching.');
        }

        return $v->errors();
    }
}

