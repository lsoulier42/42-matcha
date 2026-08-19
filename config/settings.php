<?php

declare(strict_types=1);

/*
 * Central application configuration, read from .env.
 * Loaded by the php-di container (definition 'settings').
 */

$rootDir = dirname(__DIR__);
$isDev = ($_ENV['APP_ENV'] ?? 'dev') === 'dev';

return [
    'app' => [
        'name' => 'Matcha',
        'url' => rtrim((string) ($_ENV['APP_URL'] ?? 'http://localhost:8080'), '/'),
        'env' => $isDev ? 'dev' : 'prod',
        'debug' => $isDev,
    ],

    'db' => [
        'host' => (string) ($_ENV['DB_HOST'] ?? 'db'),
        'port' => (string) ($_ENV['DB_PORT'] ?? '3306'),
        'name' => (string) ($_ENV['DB_NAME'] ?? 'matcha'),
        'user' => (string) ($_ENV['DB_USER'] ?? 'matcha'),
        'pass' => (string) ($_ENV['DB_PASS'] ?? ''),
        'charset' => 'utf8mb4',
    ],

    'twig' => [
        'templates' => $rootDir . '/templates',
        'options' => [
            'cache' => $isDev ? false : $rootDir . '/var/cache/twig',
            'debug' => $isDev,
            'auto_reload' => true,
            // In dev, any missing Twig variable is an error (catches notices).
            'strict_variables' => true,
        ],
    ],

    'session' => [
        'name' => 'matcha_session',
        'lifetime' => 7 * 86400,
        'secure' => false, // set to true behind HTTPS
    ],

    'uploads' => [
        'dir' => $rootDir . '/public/assets/uploads',
        'max_size' => 5 * 1024 * 1024, // 5 MB
        'max_photos' => 5,
        'allowed' => [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ],
    ],

    'mail' => [
        'host' => (string) ($_ENV['SMTP_HOST'] ?? 'mailhog'),
        'port' => (int) ($_ENV['SMTP_PORT'] ?? 1025),
        'user' => (string) ($_ENV['SMTP_USER'] ?? ''),
        'pass' => (string) ($_ENV['SMTP_PASS'] ?? ''),
        'from' => (string) ($_ENV['MAIL_FROM'] ?? 'no-reply@matcha.local'),
        'from_name' => (string) ($_ENV['MAIL_FROM_NAME'] ?? 'Matcha'),
    ],

    'realtime' => [
        'poll_interval' => 5, // seconds (constraint: delivery <= 10 s)
    ],

    'matching' => [
        'zone_radius_km' => 10, // "same geographic zone"
        'max_results' => 60,
    ],
];
