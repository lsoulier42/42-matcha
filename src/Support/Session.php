<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Minimal wrapper around the PHP session superglobal.
 * Use $request->getAttribute('user_id') in controllers that receive a request;
 * fall back to this helper only when no request is available (e.g. HomeController).
 */
final class Session
{
    public static function userId(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }
}
