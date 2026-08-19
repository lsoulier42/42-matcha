<?php

declare(strict_types=1);

namespace App\Validation;

use App\Dto\LocationUpdateData;

/**
 * Location rules: explicit GPS consent with valid coordinates,
 * OR mandatory manual city/district input (spec requirement for
 * matching when GPS is declined).
 *
 * @return array<string, string> errors (field => message), empty if valid
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
            // Spec requirement: GPS declined → manual input mandatory.
            $v->add('ville', 'La localisation manuelle (ville ou quartier) est obligatoire pour le matching.');
        }

        return $v->errors();
    }
}

