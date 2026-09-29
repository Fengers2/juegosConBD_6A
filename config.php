<?php
declare(strict_types=1);

/**
 * Database connection settings. Configure these with environment variables:
 * DB_HOST, DB_PORT, DB_NAME, DB_USER and DB_PASSWORD.
 */
$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbPort = getenv('DB_PORT') ?: '3306';
$dbName = getenv('DB_NAME') ?: 'juevos6A';
$dbUser = getenv('DB_USER') ?: 'crud_app';
$dbPassword = getenv('DB_PASSWORD') ?: '';

$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
    $dbHost,
    $dbPort,
    $dbName
);

$pdo = new PDO($dsn, $dbUser, $dbPassword, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);