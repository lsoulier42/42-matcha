<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Jeton à usage unique (vérification d'e-mail / réinitialisation).
 */
final readonly class Token
{
    public function __construct(
        public int $id,
        public int $userId,
        public string $type,
        public string $token,
        public string $expiresAt,
        public bool $used,
        public ?string $createdAt,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) ($row['id'] ?? $row['token_id']),
            userId: (int) ($row['user_id'] ?? $row['token_id']),
            type: (string) ($row['type'] ?? ''),
            token: (string) ($row['token'] ?? ''),
            expiresAt: (string) $row['expires_at'],
            used: (bool) ($row['used'] ?? 0),
            createdAt: $row['created_at'] ?? null,
        );
    }
}
