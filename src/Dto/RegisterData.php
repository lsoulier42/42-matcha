<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * Données d'inscription normalisées, issues du formulaire (GET /auth/register).
 * Le hash du mot de passe est calculé à la persistance via toRecord().
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

    /** Ligne à insérer dans la table users. */
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
