<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Session flash messages (displayed once, in the layout).
 */
final class Flash
{
    public static function set(string $type, string $message): void
    {
        $_SESSION['flash'][$type][] = $message;
    }

    /** Retrieves and clears flash messages. */
    public static function pull(): array
    {
        $flash = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $flash;
    }
}
