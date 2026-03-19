<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

// Create nexus_test database
try {
    $pdo = new PDO(
        'mysql:host=db;port=3306',
        'root',
        'nexus_root_secret',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $pdo->exec('CREATE DATABASE IF NOT EXISTS nexus_test');
    $pdo->exec("GRANT ALL PRIVILEGES ON nexus_test.* TO 'nexus'@'%'");
    $pdo->exec('FLUSH PRIVILEGES');
    echo "nexus_test database created and permissions granted.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";

    // Try with the nexus user instead
    try {
        $pdo = new PDO(
            'mysql:host=db;port=3306',
            'nexus',
            'HpW4H99dd2BNXjtl5FhHlIEitzAkjmm',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $pdo->exec('CREATE DATABASE IF NOT EXISTS nexus_test');
        echo "nexus_test database created with nexus user.\n";
    } catch (PDOException $e2) {
        echo "Error with nexus user: " . $e2->getMessage() . "\n";
    }
}
