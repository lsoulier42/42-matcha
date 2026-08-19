<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * Normalised tag name from the form (POST /profile/tags).
 * Centralised normalisation: lowercase, no '#', spaces → hyphens.
 */
final readonly class TagData
{
    public function __construct(
        public string $name,
    ) {
    }

    public static function fromRequest(array $body): self
    {
        $name = mb_strtolower(trim((string) ($body['tag'] ?? '')));
        $name = str_replace('#', '', $name);
        $name = preg_replace('/\s+/', '-', $name) ?? '';

        return new self(name: $name);
    }
}
