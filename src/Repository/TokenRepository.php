<?php

declare(strict_types=1);

namespace App\Repository;

use App\Db\Query;

/**
 * Jetons à usage unique : vérification d'e-mail et réinitialisation
 * de mot de passe (64 caractères hexadécimaux, expiration 24 h).
 */
final class TokenRepository
{
    public function __construct(private Query $db)
    {
    }

    public function create(int $userId, string $type, string $token, string $expiresAt): void
    {
        $this->db->insert('tokens', [
            'user_id' => $userId,
            'type' => $type,
            'token' => $token,
            'expires_at' => $expiresAt,
        ]);
    }

    /** Jeton de vérification d'e-mail (avec l'utilisateur), ou null si invalide. */
    public function findValidVerify(string $token): ?array
    {
        if (preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
            return null;
        }
        return $this->db->fetch(
            'SELECT t.id AS token_id, t.used, t.expires_at, u.id AS user_id
             FROM tokens t JOIN users u ON u.id = t.user_id
             WHERE t.token = ? AND t.type = ?',
            [$token, 'verify_email']
        );
    }

    /** Jeton de réinitialisation non utilisé et non expiré, ou null. */
    public function findValidReset(string $token): ?array
    {
        if (preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
            return null;
        }
        $row = $this->db->fetch(
            'SELECT id, user_id, used, expires_at FROM tokens WHERE token = ? AND type = ?',
            [$token, 'reset_password']
        );
        if ($row === null || (int) $row['used'] === 1 || strtotime((string) $row['expires_at']) < time()) {
            return null;
        }
        return $row;
    }

    public function markUsed(int $id): void
    {
        $this->db->update('tokens', ['used' => 1], 'id = ?', [$id]);
    }
}
