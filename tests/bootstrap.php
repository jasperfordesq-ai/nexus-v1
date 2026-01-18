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
$envFile = __DIR__ . '/../.env.testing';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        if (strpos($line, '=') !== false) {
            putenv(trim($line));
            [$key, $value] = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
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
