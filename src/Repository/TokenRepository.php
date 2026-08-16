<?php

declare(strict_types=1);

namespace App\Repository;

use App\Db\Query;
use App\Entity\Token;

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
    public function findValidVerify(string $token): ?Token
    {
        if (preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
            return null;
        }
        $row = $this->db->fetch(
            'SELECT t.id AS token_id, t.used, t.expires_at, t.type, t.token, t.created_at,
                    u.id AS user_id
             FROM tokens t JOIN users u ON u.id = t.user_id
             WHERE t.token = ? AND t.type = ?',
            [$token, 'verify_email']
        );
        return $row === null ? null : Token::fromRow($row);
    }

    /** Jeton de réinitialisation non utilisé et non expiré, ou null. */
    public function findValidReset(string $token): ?Token
    {
        if (preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
            return null;
        }
        $row = $this->db->fetch(
            'SELECT id, user_id, type, token, used, expires_at, created_at
             FROM tokens WHERE token = ? AND type = ?',
            [$token, 'reset_password']
        );
        if ($row === null) {
            return null;
        }
        $tokenEntity = Token::fromRow($row);
        if ($tokenEntity->used || strtotime($tokenEntity->expiresAt) < time()) {
            return null;
        }
        return $tokenEntity;
    }

    public function markUsed(int $id): void
    {
        $this->db->update('tokens', ['used' => 1], 'id = ?', [$id]);
    }
}
