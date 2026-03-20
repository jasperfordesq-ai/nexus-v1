<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Core;

use PDO;

/**
 * Facade / wrapper for \Nexus\Core\Database.
 *
 * Every public static method on the legacy class is forwarded here so that code
 * outside `src/` can depend on `App\Core\Database` while the underlying
 * implementation lives in `Nexus\Core\Database`.  When the Laravel migration
 * replaces the legacy class, only this thin delegate needs updating.
 */
class Database
{
    /**
     * Get the singleton PDO instance (or Laravel's PDO when bridged).
     *
     * @return \PDO
     */
    public static function getInstance(): PDO
    {
        return \Nexus\Core\Database::getInstance();
    }

    /**
     * Alias of getInstance().
     *
     * @return \PDO
     */
    public static function getConnection(): PDO
    {
        return \Nexus\Core\Database::getConnection();
    }

    /**
     * Prepare, execute and return a PDOStatement.
     *
     * @param  string $sql    SQL with positional (?) or named (:param) placeholders
     * @param  array  $params Bind values
     * @return \PDOStatement
     */
    public static function query(string $sql, array $params = []): \PDOStatement
    {
        return \Nexus\Core\Database::query($sql, $params);
    }

    /**
     * Return the last auto-increment ID.
     *
     * @return string
     */
    public static function lastInsertId(): string
    {
        return \Nexus\Core\Database::lastInsertId();
    }

    /**
     * Begin a database transaction.
     *
     * @return bool
     */
    public static function beginTransaction(): bool
    {
        return \Nexus\Core\Database::beginTransaction();
    }

    /**
     * Commit the current transaction.
     *
     * @return bool
     */
    public static function commit(): bool
    {
        return \Nexus\Core\Database::commit();
    }

    /**
     * Rollback the current transaction.
     *
     * @return bool
     */
    public static function rollback(): bool
    {
        return \Nexus\Core\Database::rollback();
    }

    /**
     * Set Laravel's PDO connection for the DB bridge.
     *
     * @param \PDO $pdo
     */
    public static function setLaravelConnection(PDO $pdo): void
    {
        \Nexus\Core\Database::setLaravelConnection($pdo);
    }

    /**
     * Get query performance statistics.
     *
     * @return array
     */
    public static function getQueryStats(): array
    {
        return \Nexus\Core\Database::getQueryStats();
    }

    /**
     * Reset the query log.
     */
    public static function resetQueryLog(): void
    {
        \Nexus\Core\Database::resetQueryLog();
    }

    /**
     * Set the slow-query threshold.
     *
     * @param float $seconds Threshold in seconds
     */
    public static function setSlowQueryThreshold(float $seconds): void
    {
        \Nexus\Core\Database::setSlowQueryThreshold($seconds);
    }

    /**
     * Enable or disable query profiling.
     *
     * @param bool $enabled
     */
    public static function setProfilingEnabled(bool $enabled): void
    {
        \Nexus\Core\Database::setProfilingEnabled($enabled);
    }
}
