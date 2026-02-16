<?php
/**
 * Simple health check endpoint for Docker
 * Does NOT load the full application
 */

header('Content-Type: application/json');

// Basic checks
$health = [
    'status' => 'healthy',
    'timestamp' => date('c'),
    'php_version' => PHP_VERSION,
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

http_response_code($health['status'] === 'healthy' ? 200 : 503);
echo json_encode($health, JSON_PRETTY_PRINT);
