<?php

declare(strict_types=1);

namespace App\Db;

use PDO;
use PDOStatement;

/**
 * Minimal custom database access layer (allowed by the spec:
 * "free to create your own mini query library").
 *
 * All queries use prepared statements (anti-SQLi).
 * Column/table names are caller-supplied literals, never user input.
 */
final class Query
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** Executes a prepared statement and returns the PDOStatement. */
    public function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /** Returns a single row or null. */
    public function fetch(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** Returns all rows. */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    /** Returns the first column of the first row, or null if no rows. */
    public function value(string $sql, array $params = []): mixed
    {
        $v = $this->run($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    /** Generic INSERT: returns the created id. */
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

    /** Generic UPDATE: $where is a SQL literal (e.g. "id = ?"). */
    public function update(string $table, array $data, string $where, array $whereParams = []): void
    {
        $sets = implode(', ', array_map(static fn (string $c): string => "$c = ?", array_keys($data)));
        $this->run(
            sprintf('UPDATE %s SET %s WHERE %s', $table, $sets, $where),
            [...array_values($data), ...$whereParams]
        );
    }

    /** Generic DELETE. */
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
