<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

/**
 * API Endpoint Test Runner
 *
 * This script runs comprehensive tests for all API endpoints
 * and generates a detailed report.
 *
 * Usage:
 *   php tests/run-api-tests.php [options]
 *
 * Options:
 *   --suite=<name>    Run specific test suite (auth, core, social, gamification, ai, etc.)
 *   --verbose         Show detailed output
 *   --coverage        Generate code coverage report
 *   --filter=<pattern> Run tests matching pattern
 *   --stop-on-failure Stop on first failure
 */

// Load bootstrap
require_once __DIR__ . '/bootstrap.php';

// Parse command line arguments
$options = getopt('', [
    'suite::',
    'verbose',
    'coverage',
    'filter::',
    'stop-on-failure',
    'help'
]);

if (isset($options['help'])) {
    showHelp();
    exit(0);
}

// Determine which test suite to run
$suite = $options['suite'] ?? 'all';
$verbose = isset($options['verbose']);
$coverage = isset($options['coverage']);
$filter = $options['filter'] ?? '';
$stopOnFailure = isset($options['stop-on-failure']);

// Build PHPUnit command
// Detect OS and use appropriate phpunit executable
$isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
if ($isWindows && file_exists(__DIR__ . '/../vendor/bin/phpunit.bat')) {
    $command = 'vendor\bin\phpunit.bat';
} elseif (file_exists(__DIR__ . '/../vendor/bin/phpunit')) {
    $command = 'vendor/bin/phpunit';
} else {
    echo "❌ PHPUnit not found. Please run: composer install\n";
    exit(1);
}

// Add configuration
$command .= ' --configuration phpunit.xml';

// Add test suite filter
if ($suite !== 'all') {
    $testMap = [
        'auth' => 'tests/Controllers/Api/AuthControllerTest.php',
        'core' => 'tests/Controllers/Api/CoreApiControllerTest.php',
        'social' => 'tests/Controllers/Api/SocialApiControllerTest.php',
        'wallet' => 'tests/Controllers/Api/WalletApiControllerTest.php',
        'gamification' => 'tests/Controllers/Api/GamificationApiControllerTest.php',
        'ai' => 'tests/Controllers/Api/AiApiControllerTest.php',
        'push' => 'tests/Controllers/Api/PushApiControllerTest.php',
        'webauthn' => 'tests/Controllers/Api/WebAuthnApiControllerTest.php',
        'super-admin' => 'tests/Controllers/Api/AdminSuperApiControllerTest.php',
        'api-all' => 'tests/Controllers/Api/',
    ];

    if (isset($testMap[$suite])) {
        $command .= ' ' . $testMap[$suite];
    } else {
        echo "❌ Unknown test suite: {$suite}\n";
        echo "Available suites: " . implode(', ', array_keys($testMap)) . "\n";
        exit(1);
    }
} else {
    // Run all API tests
    $command .= ' tests/Controllers/Api/';
}

// Add filter
if ($filter) {
    $command .= ' --filter ' . escapeshellarg($filter);
}

// Add verbose flag
if ($verbose) {
    $command .= ' --verbose';
}

// Add coverage
if ($coverage) {
    $command .= ' --coverage-html coverage/html';
    $command .= ' --coverage-text';
}

// Add stop on failure
if ($stopOnFailure) {
    $command .= ' --stop-on-failure';
}

// Add colors
$command .= ' --colors=always';

// Display header
echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║           API ENDPOINT TEST RUNNER                           ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n";
echo "\n";
echo "Suite:          " . ($suite === 'all' ? 'All API Tests' : ucfirst($suite)) . "\n";
echo "Environment:    " . ($_ENV['APP_ENV'] ?? 'testing') . "\n";
echo "Database:       " . ($_ENV['DB_DATABASE'] ?? 'nexus_test') . "\n";
echo "Verbose:        " . ($verbose ? 'Yes' : 'No') . "\n";
echo "Coverage:       " . ($coverage ? 'Yes' : 'No') . "\n";
if ($filter) {
    echo "Filter:         {$filter}\n";
}
echo "\n";
echo "────────────────────────────────────────────────────────────────\n\n";

// Run tests
$startTime = microtime(true);
passthru($command, $exitCode);
$endTime = microtime(true);

// Display summary
$duration = round($endTime - $startTime, 2);

echo "\n────────────────────────────────────────────────────────────────\n";
echo "\n";
echo "Duration:       {$duration} seconds\n";
echo "Exit Code:      {$exitCode}\n";

if ($exitCode === 0) {
    echo "Status:         ✅ All tests passed!\n";
} else {
    echo "Status:         ❌ Some tests failed\n";
}

if ($coverage) {
    echo "\n";
    echo "Coverage report: coverage/html/index.html\n";
}

echo "\n";

exit($exitCode);

/**
 * Show help information
 */
function showHelp(): void
{
    echo <<<HELP

╔══════════════════════════════════════════════════════════════╗
║           API ENDPOINT TEST RUNNER - HELP                    ║
╚══════════════════════════════════════════════════════════════╝

USAGE:
    php tests/run-api-tests.php [options]

OPTIONS:
    --suite=<name>       Run specific test suite
    --verbose            Show detailed test output
    --coverage           Generate code coverage report
    --filter=<pattern>   Run tests matching pattern
    --stop-on-failure    Stop on first test failure
    --help               Show this help message

AVAILABLE TEST SUITES:
    all                  Run all API tests (default)
    auth                 Authentication endpoints
    core                 Core API endpoints (members, listings, groups)
    social               Social features (likes, comments, feed)
    wallet               Wallet/timebanking endpoints
    gamification         Gamification features (badges, rewards, challenges)
    ai                   AI-powered features (chat, generation)
    push                 Push notifications
    webauthn             WebAuthn passwordless authentication
    super-admin          Super Admin Panel endpoints
    api-all              All API controller tests

EXAMPLES:
    # Run all API tests
    php tests/run-api-tests.php

    # Run authentication tests only
    php tests/run-api-tests.php --suite=auth

    # Run tests with verbose output
    php tests/run-api-tests.php --suite=social --verbose

    # Run tests matching a pattern
    php tests/run-api-tests.php --filter=testLogin

    # Generate coverage report
    php tests/run-api-tests.php --coverage

    # Stop on first failure
    php tests/run-api-tests.php --stop-on-failure

    # Combine options
    php tests/run-api-tests.php --suite=wallet --verbose --stop-on-failure


HELP;
}
