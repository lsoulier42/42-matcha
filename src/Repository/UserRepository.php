<?php

declare(strict_types=1);

namespace App\Repository;

use App\Db\Query;
use App\Entity\User;
use App\ViewModel\MapMarker;

/**
 * User data access. All queries go through the mini-lib Query
 * (prepared statements) — no ORM, per the spec.
 * SQL rows are mapped to entities (User) or ViewModels (MapMarker).
 */
final class UserRepository
{
    public function __construct(private Query $db)
    {
    }

    public function findById(int $id): ?User
    {
        $row = $this->db->fetch('SELECT * FROM users WHERE id = ?', [$id]);
        return $row === null ? null : User::fromRow($row);
    }

    public function findActiveById(int $id): ?User
    {
        $row = $this->db->fetch('SELECT * FROM users WHERE id = ? AND actif = 1', [$id]);
        return $row === null ? null : User::fromRow($row);
    }

    public function findByUsername(string $username, bool $activeOnly = true): ?User
    {
        $sql = 'SELECT * FROM users WHERE username = ?';
        if ($activeOnly) {
            $sql .= ' AND actif = 1';
        }
        $row = $this->db->fetch($sql, [$username]);
        return $row === null ? null : User::fromRow($row);
    }

    public function findByEmail(string $email, bool $activeOnly = true): ?User
    {
        $sql = 'SELECT * FROM users WHERE email = ?';
        if ($activeOnly) {
            $sql .= ' AND actif = 1';
        }
        $row = $this->db->fetch($sql, [$email]);
        return $row === null ? null : User::fromRow($row);
    }

    /** Does a user with this email exist (excluding $excludeId if given)? */
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

    /** GPS position for a user (the "you" marker on the map). */
    public function findWithPosition(int $id): ?MapMarker
    {
        $row = $this->db->fetch('SELECT id, prenom, lat, lng FROM users WHERE id = ?', [$id]);
        if ($row === null || $row['lat'] === null || $row['lng'] === null) {
            return null;
        }
        return MapMarker::fromRow($row);
    }

    /** GPS positions for the given IDs (map markers). */
    public function findPositionsByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = $this->db->fetchAll(
            "SELECT id, prenom, lat, lng, note_popularite FROM users
             WHERE id IN ($placeholders) AND lat IS NOT NULL AND lng IS NOT NULL",
            $ids
        );
        return array_map(static fn (array $row): MapMarker => MapMarker::fromRow($row), $rows);
    }

    public function count(): int
    {
        return (int) $this->db->value('SELECT COUNT(*) FROM users');
    }

    /**
     * Suggestion candidates: conditions (built by the service),
     * profile photo and count of tags shared with the current user.
     *
     * @param string[] $where SQL conditions (joined by AND)
     * @return array<array<string, mixed>> raw rows (the service applies
     *         orientation, scoring and sorting before mapping to ProfileCard)
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
