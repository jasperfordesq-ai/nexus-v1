<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

// Create nexus_test database. Reads credentials from environment, no
// hardcoded secrets. Run as:
//   docker exec -e DB_ROOT_PASSWORD=... -e DB_PASS=... <container> php tests/create_test_db.php

$rootPass = getenv('DB_ROOT_PASSWORD') ?: '';
$userPass = getenv('DB_PASS') ?: '';

try {
    if ($rootPass === '') {
        throw new RuntimeException('DB_ROOT_PASSWORD env var not set');
    }
    $pdo = new PDO(
        'mysql:host=db;port=3306',
        'root',
        $rootPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $pdo->exec('CREATE DATABASE IF NOT EXISTS nexus_test');
    $pdo->exec("GRANT ALL PRIVILEGES ON nexus_test.* TO 'nexus'@'%'");
    $pdo->exec('FLUSH PRIVILEGES');
    echo "nexus_test database created and permissions granted.\n";
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";

    // Fall back to the nexus user
    try {
        if ($userPass === '') {
            throw new RuntimeException('DB_PASS env var not set');
        }
        $pdo = new PDO(
            'mysql:host=db;port=3306',
            'nexus',
            $userPass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $pdo->exec('CREATE DATABASE IF NOT EXISTS nexus_test');
        echo "nexus_test database created with nexus user.\n";
    } catch (Throwable $e2) {
        echo "Error with nexus user: " . $e2->getMessage() . "\n";
    }
}
