<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Profile photo (up to 5, one is the profile photo).
 */
final readonly class Photo
{
    public function __construct(
        public int $id,
        public int $userId,
        public string $path,
        public bool $isProfile,
        public int $position,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            userId: (int) $row['user_id'],
            path: (string) $row['path'],
            isProfile: (bool) ($row['is_profile'] ?? 0),
            position: (int) ($row['position'] ?? 0),
        );
    }
}
