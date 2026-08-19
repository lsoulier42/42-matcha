<?php

declare(strict_types=1);

namespace App\Repository;

use App\Db\Query;
use App\Entity\Photo;

/**
 * Profile photos (maximum 5, one profile photo).
 */
final class PhotoRepository
{
    public function __construct(private Query $db)
    {
    }

    /** @return Photo[] */
    public function listByUser(int $userId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM photos WHERE user_id = ? ORDER BY position ASC',
            [$userId]
        );
        return array_map(static fn (array $row): Photo => Photo::fromRow($row), $rows);
    }

    public function countForUser(int $userId): int
    {
        return (int) $this->db->value('SELECT COUNT(*) FROM photos WHERE user_id = ?', [$userId]);
    }

    public function findOwned(int $photoId, int $userId): ?Photo
    {
        $row = $this->db->fetch('SELECT * FROM photos WHERE id = ? AND user_id = ?', [$photoId, $userId]);
        return $row === null ? null : Photo::fromRow($row);
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

    /** Sets $photoId as the sole profile photo for $userId. */
    public function setProfile(int $userId, int $photoId): void
    {
        $this->db->run('UPDATE photos SET is_profile = 0 WHERE user_id = ?', [$userId]);
        $this->db->update('photos', ['is_profile' => 1], 'id = ?', [$photoId]);
    }

    public function delete(int $photoId): void
    {
        $this->db->delete('photos', 'id = ?', [$photoId]);
    }

    /** First remaining photo (for promotion after deletion). */
    public function next(int $userId): ?Photo
    {
        $row = $this->db->fetch(
            'SELECT * FROM photos WHERE user_id = ? ORDER BY position ASC LIMIT 1',
            [$userId]
        );
        return $row === null ? null : Photo::fromRow($row);
    }

    public function profilePhoto(int $userId): ?Photo
    {
        $row = $this->db->fetch(
            'SELECT * FROM photos WHERE user_id = ? AND is_profile = 1 ORDER BY position ASC LIMIT 1',
            [$userId]
        );
        return $row === null ? null : Photo::fromRow($row);
    }

    /** Does the user have a profile photo? (required to like) */
    public function hasProfilePhoto(int $userId): bool
    {
        return $this->db->value(
            'SELECT id FROM photos WHERE user_id = ? AND is_profile = 1 LIMIT 1',
            [$userId]
        ) !== null;
    }
}
