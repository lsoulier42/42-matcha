<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * Normalised registration data from the form (GET /auth/register).
 * The password hash is computed at persistence time via toRecord().
 */
final readonly class RegisterData
{
    public function __construct(
        public string $email,
        public string $username,
        public string $nom,
        public string $prenom,
        public string $password,
        public string $passwordConfirm,
    ) {
    }

    public static function fromRequest(array $body): self
    {
        return new self(
            email: mb_strtolower(trim((string) ($body['email'] ?? ''))),
            username: trim((string) ($body['username'] ?? '')),
            nom: trim((string) ($body['nom'] ?? '')),
            prenom: trim((string) ($body['prenom'] ?? '')),
            password: (string) ($body['password'] ?? ''),
            passwordConfirm: (string) ($body['password_confirm'] ?? ''),
        );
    }

    /** Row to insert into the users table. */
    public function toRecord(): array
    {
        return [
            'email' => $this->email,
            'username' => $this->username,
            'nom' => $this->nom,
            'prenom' => $this->prenom,
            'password_hash' => password_hash($this->password, PASSWORD_DEFAULT),
        ];
    }
}
