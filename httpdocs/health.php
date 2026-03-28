<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Simple health check endpoint for Docker
 * Does NOT load the full application
 */

header('Content-Type: application/json');

// Basic checks — do NOT expose php_version or internal details to the public internet
$health = [
    'status' => 'healthy',
    'timestamp' => date('c'),
];

// Check database connection (optional)
$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbPort = getenv('DB_PORT') ?: '3306';
$dbName = getenv('DB_NAME') ?: 'nexus';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') ?: '';

try {
    $pdo = new PDO(
        "mysql:host={$dbHost};port={$dbPort};dbname={$dbName}",
        $dbUser,
        $dbPass,
        [PDO::ATTR_TIMEOUT => 5]
    );
    $health['database'] = 'connected';
} catch (PDOException $e) {
    error_log('Health check DB error: ' . $e->getMessage());
    $health['database'] = 'disconnected';
    $health['status'] = 'degraded';
}

// Check Redis connection (optional)
$redisHost = getenv('REDIS_HOST') ?: '127.0.0.1';
$redisPort = getenv('REDIS_PORT') ?: '6379';

try {
    $redis = new Redis();
    $redis->connect($redisHost, (int) $redisPort, 3);
    $redis->ping();
    $health['redis'] = 'connected';
    $redis->close();
} catch (Throwable $e) {
    $health['redis'] = 'disconnected';
    if ($health['status'] === 'healthy') {
        $health['status'] = 'degraded';
    }
}

http_response_code($health['status'] === 'healthy' ? 200 : 503);
echo json_encode($health, JSON_PRETTY_PRINT);
