<?php

declare(strict_types=1);

namespace App\Repository;

use App\Db\Query;
use App\Entity\Tag;

/**
 * Tags réutilisables (intérêts partagés) et liaison user_tags.
 */
final class TagRepository
{
    public function __construct(private Query $db)
    {
    }

    public function findIdByName(string $name): ?int
    {
        $id = $this->db->value('SELECT id FROM tags WHERE name = ?', [$name]);
        return $id === null ? null : (int) $id;
    }

    public function create(string $name): int
    {
        $this->db->insert('tags', ['name' => $name]);
        return $this->db->lastInsertId();
    }

    /** Tous les noms de tags (ordre alphabétique). */
    public function all(): array
    {
        $rows = $this->db->fetchAll('SELECT name FROM tags ORDER BY name ASC');
        return array_column($rows, 'name');
    }

    /** Autocomplétion : 10 premiers tags commençant par $q. */
    public function search(string $q): array
    {
        if ($q === '') {
            $rows = $this->db->fetchAll('SELECT name FROM tags ORDER BY name ASC LIMIT 10');
        } else {
            $rows = $this->db->fetchAll('SELECT name FROM tags WHERE name LIKE ? ORDER BY name ASC LIMIT 10', [$q . '%']);
        }
        return array_column($rows, 'name');
    }

    /** @return Tag[] */
    public function listByUser(int $userId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT t.id, t.name FROM tags t
             JOIN user_tags ut ON ut.tag_id = t.id
             WHERE ut.user_id = ? ORDER BY t.name ASC',
            [$userId]
        );
        return array_map(static fn (array $row): Tag => Tag::fromRow($row), $rows);
    }

    /** Ids des tags d'un utilisateur. */
    public function idsForUser(int $userId): array
    {
        $rows = $this->db->fetchAll('SELECT tag_id FROM user_tags WHERE user_id = ?', [$userId]);
        return array_map('intval', array_column($rows, 'tag_id'));
    }

    public function countForUser(int $userId): int
    {
        return (int) $this->db->value('SELECT COUNT(*) FROM user_tags WHERE user_id = ?', [$userId]);
    }

    public function attach(int $userId, int $tagId): void
    {
        $this->db->run('INSERT IGNORE INTO user_tags (user_id, tag_id) VALUES (?, ?)', [$userId, $tagId]);
    }

    public function detach(int $userId, int $tagId): void
    {
        $this->db->delete('user_tags', 'user_id = ? AND tag_id = ?', [$userId, $tagId]);
    }
}
