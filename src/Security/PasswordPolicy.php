<?php

declare(strict_types=1);

namespace App\Security;

/**
 * Password policy (explicit spec requirement):
 *  - commonly used English words REJECTED (blacklist),
 *  - complexity requirement: min 8 characters, lowercase, uppercase,
 *    digit and symbol.
 */
final class PasswordPolicy
{
    private const BLACKLIST = [
        'password', 'password1', 'password123', 'passw0rd', 'pass123', 'pass',
        '123456', '12345678', '123456789', '1234567890', '12345', '1234', '123',
        '000000', '111111', '121212', '112233', '666666', '88888888',
        'qwerty', 'qwerty123', 'qwertyuiop', 'qazwsx', '1q2w3e4r', '1q2w3e4r5t',
        '1qaz2wsx', 'zaq12wsx', 'zaq1xsw2', 'qwe123', '1q2w3e', 'zxcvbnm',
        'azerty', 'azertyuiop', 'abc123', 'abc1234', 'abc12345', '123abc',
        'letmein', 'letmein1', 'letmein123', 'admin', 'admin123', 'admin1234',
        'root', 'root123', 'toor', 'guest', 'test', 'test123', 'test1234',
        'demo', 'default', 'changeme', 'welcome', 'welcome1', 'love', 'lovely',
        'iloveyou', 'iloveyou1', 'secret', 'monkey', 'dragon', 'master',
        'master123', 'login', 'shadow', 'sunshine', 'sunshine1', 'princess',
        'princess1', 'trustno1', 'superman', 'batman', 'donald', 'mickey',
        'starwars', 'hunter', 'freedom', 'whatever', 'whatever1', 'hello',
        'hello123', 'hallo', 'computer', 'internet', 'google', 'yahoo',
        'microsoft', 'apple', 'football', 'soccer', 'baseball', 'basketball',
        'mustang', 'harley', 'chelsea', 'arsenal', 'liverpool', 'manchester',
        'jordan', 'pepper', 'pokemon', 'ninja', 'cookie', 'buster', 'silver',
        'golden', 'purple', 'yellow', 'orange', 'banana', 'a123456', 'aaa111',
        'mypassword', 'mypass', 'babygirl', 'snoopy', 'buster', 'diamond',
        'tigger', 'charlie', 'thomas', 'hannah', 'jessica', 'daniel',
    ];

    /** @return string[] error messages (empty if the password is accepted) */
    public static function check(string $password): array
    {
        $errors = [];

        if (strlen($password) < 8) {
            $errors[] = 'Le mot de passe doit contenir au moins 8 caractères.';
        }
        if (preg_match('/[a-z]/', $password) !== 1) {
            $errors[] = 'Le mot de passe doit contenir au moins une minuscule.';
        }
        if (preg_match('/[A-Z]/', $password) !== 1) {
            $errors[] = 'Le mot de passe doit contenir au moins une majuscule.';
        }
        if (preg_match('/[0-9]/', $password) !== 1) {
            $errors[] = 'Le mot de passe doit contenir au moins un chiffre.';
        }
        if (preg_match('/[^a-zA-Z0-9]/', $password) !== 1) {
            $errors[] = 'Le mot de passe doit contenir au moins un symbole (ex. ! @ # $ %).';
        }
        if (in_array(mb_strtolower($password), self::BLACKLIST, true)) {
            $errors[] = 'Ce mot de passe est trop courant (mot anglais usuel interdit).';
        }

        return $errors;
    }
}
