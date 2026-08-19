<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * Normalised location update data (POST /profile/location).
 * `ville` is null when empty; `lat`/`lng` are null when absent or empty
 * (a `'0'` value remains a valid float 0.0, not null).
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

    /** Columns to update in the users table. */
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
