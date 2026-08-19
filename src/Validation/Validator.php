<?php

declare(strict_types=1);

namespace App\Validation;

/**
 * Custom validator (no built-in validator allowed by the spec).
 * Accumulates error messages field by field.
 */
final class Validator
{
    /** @var array<string, string> */
    private array $errors = [];

    public function required(string $field, mixed $value, string $label): self
    {
        if ($value === null || trim((string) $value) === '') {
            $this->errors[$field] = "Le champ « $label » est obligatoire.";
        }
        return $this;
    }

    public function email(string $field, mixed $value, string $label): self
    {
        if ($value === null || $value === '') {
            return $this;
        }
        if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            $this->errors[$field] = "« $label » n'est pas une adresse e-mail valide.";
        } elseif (mb_strlen((string) $value) > 190) {
            $this->errors[$field] = "« $label » est trop longue.";
        }
        return $this;
    }

    /** Username: 3–30 alphanumeric or underscore characters. */
    public function username(string $field, mixed $value): self
    {
        if ($value === null || $value === '') {
            return $this;
        }
        if (preg_match('/^[a-zA-Z0-9_]{3,30}$/', (string) $value) !== 1) {
            $this->errors[$field] = 'Le nom d\'utilisateur doit contenir 3 à 30 caractères (lettres, chiffres, _).';
        }
        return $this;
    }

    /** Name / first name: letters, spaces, apostrophes, hyphens. */
    public function name(string $field, mixed $value, string $label): self
    {
        if ($value === null || $value === '') {
            return $this;
        }
        $v = trim((string) $value);
        if (preg_match('/^[a-zA-ZÀ-ÿ\' -]{1,100}$/u', $v) !== 1) {
            $this->errors[$field] = "« $label » contient des caractères invalides.";
        }
        return $this;
    }

    public function length(string $field, mixed $value, int $min, int $max, string $label): self
    {
        if ($value === null || $value === '') {
            return $this;
        }
        $len = mb_strlen((string) $value);
        if ($len < $min || $len > $max) {
            $this->errors[$field] = "« $label » doit contenir entre $min et $max caractères.";
        }
        return $this;
    }

    public function equals(string $field, mixed $value, mixed $expected, string $label): self
    {
        if ($value !== $expected) {
            $this->errors[$field] = $label;
        }
        return $this;
    }

    /** Adds an error directly (e.g. business rules). */
    public function add(string $field, string $message): self
    {
        $this->errors[$field] = $message;
        return $this;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        return $this->errors;
    }
}
