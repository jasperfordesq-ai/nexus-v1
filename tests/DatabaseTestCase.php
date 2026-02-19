<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests;

use PDO;

/**
 * Database Test Case
 *
 * Base class for tests that require database access.
 * Provides transaction rollback for test isolation.
 */
abstract class DatabaseTestCase extends TestCase
{
    protected static ?PDO $pdo = null;
    protected static bool $migrated = false;

    /**
     * Set up database connection before any tests run.
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        if (self::$pdo === null) {
            self::$pdo = self::createConnection();
        }

        if (!self::$migrated) {
            self::migrate();
            self::$migrated = true;
        }
    }

    /**
     * Create database connection.
     */
    protected static function createConnection(): PDO
    {
        $host = getenv('DB_HOST') ?: 'localhost';
        $database = getenv('DB_DATABASE') ?: getenv('DB_NAME') ?: 'nexus';
        $username = getenv('DB_USERNAME') ?: getenv('DB_USER') ?: 'root';
        $password = getenv('DB_PASSWORD') ?: getenv('DB_PASS') ?: '';

        $dsn = "mysql:host={$host};dbname={$database};charset=utf8mb4";

        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return $pdo;
    }

    /**
     * Run migrations for test database.
     */
    protected static function migrate(): void
    {
        // Skip migrations - test database schema is copied from main database
        // Migrations with DELIMITER commands can't be executed via PDO
        // If schema is missing, it will be copied during test database setup
        return;
    }

    /**
     * Begin transaction before each test.
     */
    protected function setUp(): void
    {
        parent::setUp();
        self::$pdo->beginTransaction();
    }

    /**
     * Rollback transaction after each test.
     */
    protected function tearDown(): void
    {
        if (self::$pdo->inTransaction()) {
            self::$pdo->rollBack();
        }
        parent::tearDown();
    }

    /**
     * Get PDO connection.
     */
    protected function getConnection(): PDO
    {
        return self::$pdo;
    }

    /**
     * Insert test data into a table.
     */
    protected function insertTestData(string $table, array $data): int
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        $stmt = self::$pdo->prepare($sql);
        $stmt->execute(array_values($data));

        return (int) self::$pdo->lastInsertId();
    }

    /**
     * Get test data from a table.
     */
    protected function getTestData(string $table, array $conditions = []): array
    {
        $sql = "SELECT * FROM {$table}";

        if (!empty($conditions)) {
            $where = implode(' AND ', array_map(fn($k) => "{$k} = ?", array_keys($conditions)));
            $sql .= " WHERE {$where}";
        }

        $stmt = self::$pdo->prepare($sql);
        $stmt->execute(array_values($conditions));

        return $stmt->fetchAll();
    }

    /**
     * Assert that a table has a row matching conditions.
     */
    protected function assertDatabaseHas(string $table, array $conditions): void
    {
        $data = $this->getTestData($table, $conditions);
        $this->assertNotEmpty($data, "Failed asserting that table [{$table}] has matching row.");
    }

    /**
     * Assert that a table does not have a row matching conditions.
     */
    protected function assertDatabaseMissing(string $table, array $conditions): void
    {
        $data = $this->getTestData($table, $conditions);
        $this->assertEmpty($data, "Failed asserting that table [{$table}] does not have matching row.");
    }

    /**
     * Assert table row count.
     */
    protected function assertDatabaseCount(string $table, int $count, array $conditions = []): void
    {
        $sql = "SELECT COUNT(*) as count FROM {$table}";

        if (!empty($conditions)) {
            $where = implode(' AND ', array_map(fn($k) => "{$k} = ?", array_keys($conditions)));
            $sql .= " WHERE {$where}";
        }

        $stmt = self::$pdo->prepare($sql);
        $stmt->execute(array_values($conditions));
        $result = $stmt->fetch();

        $this->assertEquals($count, $result['count'], "Table [{$table}] does not have expected row count.");
    }

    /**
     * Truncate a table.
     */
    protected function truncateTable(string $table): void
    {
        self::$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        self::$pdo->exec("TRUNCATE TABLE {$table}");
        self::$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    }
}
