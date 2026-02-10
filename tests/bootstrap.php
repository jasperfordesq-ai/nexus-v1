<?php

declare(strict_types=1);

/**
 * PHPUnit Bootstrap File
 *
 * Sets up the testing environment for all test suites.
 */

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Load testing environment variables
// Try multiple env files in order of preference
$envFiles = [
    __DIR__ . '/../.env.testing',   // Testing-specific env
    __DIR__ . '/../.env.docker',    // Docker environment
    __DIR__ . '/../.env',           // Default env
];

$loadEnvFile = function($filePath) {
    if (!file_exists($filePath)) {
        return false;
    }
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments and empty lines
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }
        // Remove Windows line endings
        $line = str_replace("\r", '', $line);
        if (strpos($line, '=') !== false) {
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            // Remove quotes from value
            if (preg_match('/^["\'].*["\']$/', $value)) {
                $value = substr($value, 1, -1);
            }
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
    return true;
};

// Only load env file if not running in Docker (Docker injects env vars directly)
// Check if DB_HOST is already set to a Docker service name
$loaded = false;
$dockerDbHost = getenv('DB_HOST');
$isDocker = ($dockerDbHost && $dockerDbHost !== 'localhost' && $dockerDbHost !== '127.0.0.1');

if (!$isDocker) {
    foreach ($envFiles as $envFile) {
        if ($loadEnvFile($envFile)) {
            $loaded = true;
            break;
        }
    }
} else {
    // In Docker, ensure DB_DATABASE is set from DB_NAME for compatibility
    if (!getenv('DB_DATABASE') && getenv('DB_NAME')) {
        putenv('DB_DATABASE=' . getenv('DB_NAME'));
        $_ENV['DB_DATABASE'] = getenv('DB_NAME');
    }
    // Map DB_USER to DB_USERNAME and DB_PASS to DB_PASSWORD
    if (!getenv('DB_USERNAME') && getenv('DB_USER')) {
        putenv('DB_USERNAME=' . getenv('DB_USER'));
        $_ENV['DB_USERNAME'] = getenv('DB_USER');
    }
    if (!getenv('DB_PASSWORD') && getenv('DB_PASS')) {
        putenv('DB_PASSWORD=' . getenv('DB_PASS'));
        $_ENV['DB_PASSWORD'] = getenv('DB_PASS');
    }
}

// Set default testing environment variables
$_ENV['APP_ENV'] = 'testing';
$_ENV['APP_DEBUG'] = 'true';

// Set timezone
date_default_timezone_set('UTC');

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Define testing constants
define('TESTING', true);
define('BASE_PATH', dirname(__DIR__));
define('TESTS_PATH', __DIR__);

// Initialize test database if needed
if (getenv('DB_DATABASE') === 'nexus_test') {
    // Database will be set up by DatabaseTestCase
}
