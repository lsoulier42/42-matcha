<?php

declare(strict_types=1);

namespace App\ViewModel;

/**
 * Universal user card: suggestions, advanced search,
 * "who viewed me" and "who liked me" (date field populated when applicable).
 */
final readonly class ProfileCard
{
    public function __construct(
        public int $id,
        public string $prenom,
        public ?int $age,
        public ?string $ville,
        public ?float $distanceKm,
        public string $popularityDisplay,
        public int $sharedTags,
        public ?string $bio,
        public ?string $avatar,
        public ?string $date,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            prenom: (string) $row['prenom'],
            age: self::ageOf((string) ($row['birthdate'] ?? '')),
            ville: $row['ville'] ?? null,
            distanceKm: isset($row['distance_km']) ? (float) $row['distance_km'] : null,
            popularityDisplay: number_format((float) ($row['note_popularite'] ?? 0), 1, ',', ' '),
            sharedTags: (int) ($row['shared_tags'] ?? 0),
            bio: $row['bio'] ?? null,
            avatar: $row['photo'] ?? ($row['avatar'] ?? null),
            date: $row['viewed_at'] ?? ($row['created_at'] ?? null),
        );
    }

    private static function ageOf(string $birthdate): ?int
    {
        if ($birthdate === '') {
            return null;
        }
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $birthdate);
        return $dt === false ? null : (int) $dt->diff(new \DateTimeImmutable('now'))->y;
    }
}
