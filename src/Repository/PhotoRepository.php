<?php

declare(strict_types=1);

namespace App\Repository;

use App\Db\Query;

/**
 * Photos des profils (maximum 5, une photo de profil).
 */
final class PhotoRepository
{
    public function __construct(private Query $db)
    {
    }

    public function listByUser(int $userId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM photos WHERE user_id = ? ORDER BY position ASC',
            [$userId]
        );
    }

    public function countForUser(int $userId): int
    {
        return (int) $this->db->value('SELECT COUNT(*) FROM photos WHERE user_id = ?', [$userId]);
    }

    public function findOwned(int $photoId, int $userId): ?array
    {
        return $this->db->fetch('SELECT * FROM photos WHERE id = ? AND user_id = ?', [$photoId, $userId]);
    }

    public function create(int $userId, string $path, int $isProfile, int $position): int
    {
        return $this->db->insert('photos', [
            'user_id' => $userId,
            'path' => $path,
            'is_profile' => $isProfile,
            'position' => $position,
        ]);
    }

    public function nextPosition(int $userId): int
    {
        return (int) $this->db->value(
            'SELECT COALESCE(MAX(position), -1) + 1 FROM photos WHERE user_id = ?',
            [$userId]
        );
    }

    /** Désigne $photoId comme unique photo de profil de $userId. */
    public function setProfile(int $userId, int $photoId): void
    {
        $this->db->run('UPDATE photos SET is_profile = 0 WHERE user_id = ?', [$userId]);
        $this->db->update('photos', ['is_profile' => 1], 'id = ?', [$photoId]);
    }

    public function delete(int $photoId): void
    {
        $this->db->delete('photos', 'id = ?', [$photoId]);
    }

    /** Première photo restante (pour promouvoir après suppression). */
    public function next(int $userId): ?array
    {
        return $this->db->fetch(
            'SELECT id FROM photos WHERE user_id = ? ORDER BY position ASC LIMIT 1',
            [$userId]
        );
    }

    public function profilePhoto(int $userId): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM photos WHERE user_id = ? AND is_profile = 1 ORDER BY position ASC LIMIT 1',
            [$userId]
        );
    }

    /** L'utilisateur a-t-il une photo de profil ? (exigence pour liker) */
    public function hasProfilePhoto(int $userId): bool
    {
        return $this->db->value(
            'SELECT id FROM photos WHERE user_id = ? AND is_profile = 1 LIMIT 1',
            [$userId]
        ) !== null;
    }
}
