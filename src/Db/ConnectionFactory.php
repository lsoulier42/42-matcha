<?php

declare(strict_types=1);

namespace App\Db;

use PDO;

/**
 * Creates the application's single PDO connection.
 * Native prepared statements (anti-SQLi), errors as exceptions.
 */
final class ConnectionFactory
{
    public static function create(array $config): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['name'],
            $config['charset'] ?? 'utf8mb4'
        );

        return new PDO($dsn, $config['user'], $config['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]);
    }
}
