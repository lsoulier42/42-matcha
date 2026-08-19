<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * Mise à jour de localisation normalisée (POST /profile/location).
 * `ville` est null si vide ; `lat`/`lng` sont null si absents ou vides
 * (une valeur `'0'` reste un float valide 0.0, pas null).
 */
final readonly class LocationUpdateData
{
    public function __construct(
        public bool $gpsConsent,
        public ?string $ville,
        public ?float $lat,
        public ?float $lng,
    ) {
    }

    public static function fromRequest(array $body): self
    {
        $ville = trim((string) ($body['ville'] ?? ''));
        $lat = isset($body['lat']) && $body['lat'] !== '' ? (float) $body['lat'] : null;
        $lng = isset($body['lng']) && $body['lng'] !== '' ? (float) $body['lng'] : null;

        return new self(
            gpsConsent: (int) ($body['gps_consent'] ?? 0) === 1,
            ville: $ville === '' ? null : $ville,
            lat: $lat,
            lng: $lng,
        );
    }

    /** Colonnes à mettre à jour dans la table users. */
    public function toRecord(): array
    {
        return [
            'ville' => $this->ville,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'gps_consent' => $this->gpsConsent ? 1 : 0,
        ];
    }
}
