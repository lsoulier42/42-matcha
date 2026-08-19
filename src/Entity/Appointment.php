<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Appointment between matched users (bonus).
 */
final readonly class Appointment
{
    public function __construct(
        public int $id,
        public string $title,
        public ?string $description,
        public ?string $location,
        public string $startLabel,
        public bool $isPast,
        public int $otherId,
        public string $otherPrenom,
    ) {
    }

    public static function fromRow(array $row): self
    {
        $startAt = (string) $row['start_at'];
        return new self(
            id: (int) $row['id'],
            title: (string) $row['title'],
            description: $row['description'] ?? null,
            location: $row['location'] ?? null,
            startLabel: date('d/m/Y à H:i', strtotime($startAt) ?: time()),
            isPast: strtotime($startAt) < time(),
            otherId: (int) $row['other_id'],
            otherPrenom: (string) $row['other_prenom'],
        );
    }
}
