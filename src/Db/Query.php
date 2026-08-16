<?php

declare(strict_types=1);

namespace App\Db;

use PDO;
use PDOStatement;

/**
 * Mini-bibliothèque maison d'accès à la base (autorisée par le sujet :
 * « libre de créer sa propre mini-bibliothèque de requêtes »).
 *
 * Toutes les requêtes passent par des prepared statements (anti-SQLi).
 * Les noms de colonnes/table sont des littéraux écrits par les appelants,
 * jamais des entrées utilisateur.
 */
final class Query
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** Exécute une requête préparée et retourne le statement (SELECT/INSERT/UPDATE/DELETE). */
    public function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /** Retourne une ligne ou null. */
    public function fetch(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** Retourne toutes les lignes. */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    /** Retourne la première colonne de la première ligne, ou null si aucune ligne. */
    public function value(string $sql, array $params = []): mixed
    {
        $v = $this->run($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    /** INSERT générique : retourne l'id créé. */
    public function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $cols),
            implode(', ', array_fill(0, count($cols), '?'))
        );
        $this->run($sql, array_values($data));
        return (int) $this->pdo->lastInsertId();
    }

    /** UPDATE générique : $where est un littéral SQL (ex. "id = ?"). */
    public function update(string $table, array $data, string $where, array $whereParams = []): void
    {
        $sets = implode(', ', array_map(static fn (string $c): string => "$c = ?", array_keys($data)));
        $this->run(
            sprintf('UPDATE %s SET %s WHERE %s', $table, $sets, $where),
            [...array_values($data), ...$whereParams]
        );
    }

    /** DELETE générique. */
    public function delete(string $table, string $where, array $params = []): void
    {
        $this->run(sprintf('DELETE FROM %s WHERE %s', $table, $where), $params);
    }

    public function lastInsertId(): int
    {
        return (int) $this->pdo->lastInsertId();
    }

    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollBack(): void
    {
        $this->pdo->rollBack();
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }
}
