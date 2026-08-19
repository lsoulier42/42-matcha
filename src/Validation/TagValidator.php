<?php

declare(strict_types=1);

namespace App\Validation;

use App\Repository\TagRepository;

/**
 * Reusable tag rules: name format and 20-tag limit per user
 * (rules extracted from ProfileController::addTag).
 *
 * @return array<string, string> errors (field => message), empty if valid
 */
final class TagValidator
{
    public function validate(TagRepository $tags, string $name, int $userId): array
    {
        $v = new Validator();

        if (preg_match('/^[a-z0-9_-]{1,30}$/', $name) !== 1) {
            $v->add('tag', 'Tag invalide (1 à 30 caractères : lettres, chiffres, _ et -).');
        }

        if ($tags->countForUser($userId) >= 20) {
            $v->add('tag', 'Nombre maximal de tags atteint (20).');
        }

        return $v->errors();
    }
}
