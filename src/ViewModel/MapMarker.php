<?php

declare(strict_types=1);

namespace App\ViewModel;

/**
 * Interactive map marker. toArray() provides the JSON expected by
 * the Leaflet client (snake_case keys unchanged).
 */
final readonly class MapMarker
{
    public function __construct(
        public int $id,
        public string $prenom,
        public float $lat,
        public float $lng,
        public ?string $popularityDisplay,
        public ?string $ville,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            prenom: (string) $row['prenom'],
            lat: (float) $row['lat'],
            lng: (float) $row['lng'],
            popularityDisplay: isset($row['note_popularite'])
                ? number_format((float) $row['note_popularite'], 1, ',', ' ') : null,
            ville: $row['ville'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'prenom' => $this->prenom,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'popularity_display' => $this->popularityDisplay,
            'ville' => $this->ville,
        ];
    }
}
