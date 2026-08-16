<?php

declare(strict_types=1);

namespace App\ViewModel;

use App\Entity\User;

/**
 * Profil public (consultation d'un autre utilisateur ou mon propre profil) :
 * toutes les infos SAUF l'e-mail et le mot de passe, plus le statut en ligne.
 */
final readonly class UserProfile
{
    public function __construct(
        public int $id,
        public string $username,
        public string $prenom,
        public ?string $genre,
        public ?string $orientation,
        public ?string $bio,
        public ?int $age,
        public ?string $ville,
        public string $popularityDisplay,
        public bool $isOnline,
        public string $lastSeen,
        public ?string $avatar,
    ) {
    }

    public static function fromRow(array $row): self
    {
        $lastSeen = (string) ($row['derniere_connexion'] ?? '');
        return new self(
            id: (int) $row['id'],
            username: (string) $row['username'],
            prenom: (string) $row['prenom'],
            genre: $row['genre'] ?? null,
            orientation: $row['orientation'] ?? null,
            bio: $row['bio'] ?? null,
            age: self::ageOf((string) ($row['birthdate'] ?? '')),
            ville: $row['ville'] ?? null,
            popularityDisplay: number_format((float) ($row['note_popularite'] ?? 0), 1, ',', ' '),
            isOnline: self::isOnline($lastSeen),
            lastSeen: self::lastSeen($lastSeen),
            avatar: $row['photo'] ?? ($row['avatar'] ?? null),
        );
    }

    /** Conversion depuis l'entité User (profil courant, session). */
    public static function fromUser(User $user): self
    {
        $lastSeen = (string) ($user->derniereConnexion ?? '');
        return new self(
            id: $user->id,
            username: $user->username,
            prenom: $user->prenom,
            genre: $user->genre,
            orientation: $user->orientation,
            bio: $user->bio,
            age: self::ageOf((string) ($user->birthdate ?? '')),
            ville: $user->ville,
            popularityDisplay: number_format($user->notePopularite, 1, ',', ' '),
            isOnline: self::isOnline($lastSeen),
            lastSeen: self::lastSeen($lastSeen),
            avatar: null,
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

    private static function isOnline(string $lastSeen): bool
    {
        if ($lastSeen === '') {
            return false;
        }
        $ts = strtotime($lastSeen);
        return $ts !== false && (time() - $ts) < 300; // en ligne depuis moins de 5 minutes
    }

    private static function lastSeen(string $lastSeen): string
    {
        $ts = strtotime($lastSeen);
        if ($ts === false || $ts <= 0) {
            return 'jamais';
        }
        $diff = time() - $ts;
        if ($diff < 60) {
            return 'à l\'instant';
        }
        if ($diff < 3600) {
            return 'il y a ' . (int) floor($diff / 60) . ' min';
        }
        if ($diff < 86400) {
            return 'il y a ' . (int) floor($diff / 3600) . ' h';
        }
        return 'le ' . date('d/m/Y à H:i', $ts);
    }
}
