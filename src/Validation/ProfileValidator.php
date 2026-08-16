<?php

declare(strict_types=1);

namespace App\Validation;

use App\Repository\UserRepository;

/**
 * Règles de mise à jour du profil : e-mail (unique, hors soi-même),
 * genre et préférences (enums), biographie, date de naissance (16–100 ans).
 *
 * @return array<string, string> erreurs (champ => message), vide si valide
 */
final class ProfileValidator
{
    private const GENRES = ['homme', 'femme', 'autre'];
    private const ORIENTATIONS = ['hetero', 'homo', 'bi'];

    public function validate(UserRepository $users, array $data, int $userId): array
    {
        $email = mb_strtolower(trim((string) ($data['email'] ?? '')));
        $genre = (string) ($data['genre'] ?? '');
        $orientation = (string) ($data['orientation'] ?? '');
        $bio = trim((string) ($data['bio'] ?? ''));
        $birthdate = (string) ($data['birthdate'] ?? '');

        $v = new Validator();
        $v->required('email', $email, 'adresse e-mail')
            ->email('email', $email, 'Adresse e-mail')
            ->required('genre', $genre, 'genre')
            ->required('orientation', $orientation, 'préférences')
            ->length('bio', $bio, 0, 500, 'Biographie');

        if (!in_array($genre, self::GENRES, true)) {
            $v->add('genre', 'Genre invalide.');
        }
        if (!in_array($orientation, self::ORIENTATIONS, true)) {
            $v->add('orientation', 'Préférences invalides.');
        }

        if ($users->emailExists($email, $userId)) {
            $v->add('email', 'Cette adresse e-mail est déjà utilisée.');
        }

        if ($birthdate !== '') {
            $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $birthdate);
            if ($dt === false) {
                $v->add('birthdate', 'Date de naissance invalide.');
            } else {
                $age = (int) $dt->diff(new \DateTimeImmutable('now'))->y;
                if ($age < 16 || $age > 100) {
                    $v->add('birthdate', 'Âge invalide (16 à 100 ans attendu).');
                }
            }
        }

        return $v->errors();
    }
}
