<?php

declare(strict_types=1);

/*
 * Applies database/schema.sql (idempotent: CREATE TABLE IF NOT EXISTS).
 * Usage: docker compose exec web php scripts/migrate.php
 */

require __DIR__ . '/../vendor/autoload.php';

Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

$settings = require __DIR__ . '/../config/settings.php';
$pdo = App\Db\ConnectionFactory::create($settings['db']);
$sql = file_get_contents(__DIR__ . '/../database/schema.sql');

if ($sql === false) {
    fwrite(STDERR, "Impossible de lire database/schema.sql\n");
    exit(1);
}

$pdo->exec($sql);
echo "Schéma appliqué avec succès.\n";
