<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Tag réutilisable (centre d'intérêt partagé).
 */
final readonly class Tag
{
    public function __construct(
        public int $id,
        public string $name,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            name: (string) $row['name'],
        );
    }
}
