<?php

declare(strict_types=1);

namespace App\Validation;

/**
 * Règles de localisation : consentement GPS explicite avec coordonnées
 * valides, OU saisie manuelle obligatoire de la ville/quartier (exigence
 * du sujet pour le matching en cas de refus du GPS).
 *
 * @return array<string, string> erreurs (champ => message), vide si valide
 */
final class LocationValidator
{
    public function validate(array $data): array
    {
        $gpsConsent = (int) ($data['gps_consent'] ?? 0) === 1;
        $ville = trim((string) ($data['ville'] ?? ''));
        $lat = isset($data['lat']) && $data['lat'] !== '' ? (float) $data['lat'] : null;
        $lng = isset($data['lng']) && $data['lng'] !== '' ? (float) $data['lng'] : null;

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
