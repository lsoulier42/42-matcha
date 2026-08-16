<?php

declare(strict_types=1);

namespace App\Repository;

use App\Db\Query;

/**
 * Accès aux données des utilisateurs. Toutes les requêtes passent par
 * la mini-lib Query (prepared statements) — pas d'ORM, conformément au sujet.
 */
final class UserRepository
{
    public function __construct(private Query $db)
    {
    }

    public function findById(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM users WHERE id = ?', [$id]);
    }

    public function findActiveById(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM users WHERE id = ? AND actif = 1', [$id]);
    }

    public function findByUsername(string $username, bool $activeOnly = true): ?array
    {
        $sql = 'SELECT * FROM users WHERE username = ?';
        if ($activeOnly) {
            $sql .= ' AND actif = 1';
        }
        return $this->db->fetch($sql, [$username]);
    }

    public function findByEmail(string $email, bool $activeOnly = true): ?array
    {
        $sql = 'SELECT * FROM users WHERE email = ?';
        if ($activeOnly) {
            $sql .= ' AND actif = 1';
        }
        return $this->db->fetch($sql, [$email]);
    }

    /** Existe-t-il un utilisateur avec cet e-mail (hors $excludeId éventuel) ? */
    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        $sql = 'SELECT id FROM users WHERE email = ?';
        $params = [$email];
        if ($excludeId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeId;
        }
        return $this->db->value($sql, $params) !== null;
    }

    public function usernameExists(string $username): bool
    {
        return $this->db->value('SELECT id FROM users WHERE username = ?', [$username]) !== null;
    }

    public function create(array $data): int
    {
        return $this->db->insert('users', $data);
    }

    public function update(int $id, array $data): void
    {
        $this->db->update('users', $data, 'id = ?', [$id]);
    }

    public function touchLastLogin(int $id): void
    {
        $this->db->run('UPDATE users SET derniere_connexion = NOW() WHERE id = ?', [$id]);
    }

    public function setEmailVerified(int $id): void
    {
        $this->db->update('users', ['email_verifie' => 1], 'id = ?', [$id]);
    }

    /** Position GPS (carte interactive). */
    public function findWithPosition(int $id): ?array
    {
        return $this->db->fetch('SELECT id, prenom, lat, lng FROM users WHERE id = ?', [$id]);
    }

    /** Positions GPS des ids donnés (carte interactive). */
    public function findPositionsByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        return $this->db->fetchAll(
            "SELECT id, prenom, lat, lng, note_popularite FROM users
             WHERE id IN ($placeholders) AND lat IS NOT NULL AND lng IS NOT NULL",
            $ids
        );
    }

    public function count(): int
    {
        return (int) $this->db->value('SELECT COUNT(*) FROM users');
    }

    /**
     * Candidats aux suggestions : conditions (construites par le service),
     * photo de profil et nombre de tags partagés avec l'utilisateur courant.
     *
     * @param string[] $where conditions SQL (fusionnées par AND)
     */
    public function suggestCandidates(array $where, array $params, array $myTagIds): array
    {
        $sharedSelect = $myTagIds !== []
            ? '(SELECT COUNT(*) FROM user_tags ut2 WHERE ut2.user_id = u.id AND ut2.tag_id IN ('
                . implode(',', array_fill(0, count($myTagIds), '?')) . '))'
            : '0';

        return $this->db->fetchAll(
            "SELECT u.id, u.username, u.prenom, u.genre, u.orientation, u.bio, u.birthdate,
                    u.note_popularite, u.ville, u.lat, u.lng, u.derniere_connexion,
                    p.path AS avatar, $sharedSelect AS shared_tags
             FROM users u
             LEFT JOIN photos p ON p.user_id = u.id AND p.is_profile = 1
             WHERE " . implode(' AND ', $where) . "
             ORDER BY u.note_popularite DESC",
            [...$myTagIds, ...$params]
        );
    }
}
