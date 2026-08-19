<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * Normalised profile update data from the form (POST /profile).
 * Empty optional fields (`orientation`, `bio`, `birthdate`) are null.
 */
final readonly class ProfileUpdateData
{
    public function __construct(
        public string $email,
        public string $genre,
        public ?string $orientation,
        public ?string $bio,
        public ?string $birthdate,
    ) {
    }

    public static function fromRequest(array $body): self
    {
        $orientation = (string) ($body['orientation'] ?? '');
        $bio = trim((string) ($body['bio'] ?? ''));
        $birthdate = (string) ($body['birthdate'] ?? '');

        return new self(
            email: mb_strtolower(trim((string) ($body['email'] ?? ''))),
            genre: (string) ($body['genre'] ?? ''),
            orientation: $orientation === '' ? null : $orientation,
            bio: $bio === '' ? null : $bio,
            birthdate: $birthdate === '' ? null : $birthdate,
        );
    }

    /** Columns to update in the users table. */
    public function toRecord(): array
    {
        return [
            'email' => $this->email,
            'genre' => $this->genre,
            'orientation' => $this->orientation,
            'bio' => $this->bio,
            'birthdate' => $this->birthdate,
        ];
    }
}
