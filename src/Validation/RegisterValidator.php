<?php

declare(strict_types=1);

namespace App\Validation;

use App\Dto\RegisterData;
use App\Repository\UserRepository;
use App\Security\PasswordPolicy;

/**
 * Règles d'inscription : champs obligatoires, e-mail valide et unique,
 * username au bon format et unique, mot de passe sécurisé (blacklist
 * des mots anglais courants + complexité) et confirmation.
 *
 * @return array<string, string> erreurs (champ => message), vide si valide
 */
final class RegisterValidator
{
    public function validate(UserRepository $users, RegisterData $data): array
    {
        $email = $data->email;
        $username = $data->username;
        $password = $data->password;

        $v = new Validator();
        $v->required('email', $email, 'adresse e-mail')
            ->email('email', $email, 'Adresse e-mail')
            ->required('username', $username, 'nom d\'utilisateur')
            ->username('username', $username)
            ->required('nom', $data->nom, 'nom de famille')
            ->name('nom', $data->nom, 'Nom de famille')
            ->required('prenom', $data->prenom, 'prénom')
            ->name('prenom', $data->prenom, 'Prénom')
            ->required('password', $password, 'mot de passe')
            ->equals('password_confirm', $data->passwordConfirm, $password, 'Les deux mots de passe ne correspondent pas.');

        foreach (PasswordPolicy::check($password) as $error) {
            $v->add('password', $error);
        }

        if ($email !== '' && $users->emailExists($email)) {
            $v->add('email', 'Cette adresse e-mail est déjà utilisée.');
        }
        if ($username !== '' && $users->usernameExists($username)) {
            $v->add('username', 'Ce nom d\'utilisateur est déjà pris.');
        }

        return $v->errors();
    }
}
