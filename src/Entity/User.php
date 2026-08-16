<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Entité utilisateur (ligne de la table users).
 * Le mot de passe n'est jamais exposé aux vues : utiliser withoutPassword()
 * pour la session, ou UserProfile pour l'affichage.
 */
final readonly class User
{
    public function __construct(
        public int $id,
        public string $email,
        public string $username,
        public string $nom,
        public string $prenom,
        public ?string $passwordHash,
        public ?string $genre,
        public ?string $orientation,
        public ?string $bio,
        public ?string $birthdate,
        public float $notePopularite,
        public ?string $ville,
        public ?float $lat,
        public ?float $lng,
        public bool $gpsConsent,
        public bool $emailVerifie,
        public bool $actif,
        public ?string $bloqueJusqua,
        public ?string $derniereConnexion,
        public ?string $createdAt,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            email: (string) $row['email'],
            username: (string) $row['username'],
            nom: (string) $row['nom'],
            prenom: (string) $row['prenom'],
            passwordHash: isset($row['password_hash']) ? (string) $row['password_hash'] : null,
            genre: $row['genre'] ?? null,
            orientation: $row['orientation'] ?? null,
            bio: $row['bio'] ?? null,
            birthdate: $row['birthdate'] ?? null,
            notePopularite: (float) ($row['note_popularite'] ?? 0),
            ville: $row['ville'] ?? null,
            lat: isset($row['lat']) && $row['lat'] !== null ? (float) $row['lat'] : null,
            lng: isset($row['lng']) && $row['lng'] !== null ? (float) $row['lng'] : null,
            gpsConsent: (bool) ($row['gps_consent'] ?? 0),
            emailVerifie: (bool) ($row['email_verifie'] ?? 0),
            actif: (bool) ($row['actif'] ?? 1),
            bloqueJusqua: $row['bloque_jusqua'] ?? null,
            derniereConnexion: $row['derniere_connexion'] ?? null,
            createdAt: $row['created_at'] ?? null,
        );
    }

    /** Version sûre pour la session : le hash du mot de passe est retiré. */
    public function withoutPassword(): self
    {
        return new self(
            id: $this->id,
            email: $this->email,
            username: $this->username,
            nom: $this->nom,
            prenom: $this->prenom,
            passwordHash: null,
            genre: $this->genre,
            orientation: $this->orientation,
            bio: $this->bio,
            birthdate: $this->birthdate,
            notePopularite: $this->notePopularite,
            ville: $this->ville,
            lat: $this->lat,
            lng: $this->lng,
            gpsConsent: $this->gpsConsent,
            emailVerifie: $this->emailVerifie,
            actif: $this->actif,
            bloqueJusqua: $this->bloqueJusqua,
            derniereConnexion: $this->derniereConnexion,
            createdAt: $this->createdAt,
        );
    }
}
