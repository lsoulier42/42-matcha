<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Messages flash en session (affichés une seule fois, dans le layout).
 */
final class Flash
{
    public static function set(string $type, string $message): void
    {
        $_SESSION['flash'][$type][] = $message;
    }

    /** Récupère et efface les messages flash. */
    public static function pull(): array
    {
        $flash = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $flash;
    }
}
