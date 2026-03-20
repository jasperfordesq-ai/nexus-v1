<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Core;

use PDO;

/**
 * @deprecated Use \App\Core\Database instead. This is a backward-compatibility delegate.
 */
class Database
{
    public static function setLaravelConnection(PDO $pdo): void
    {
        \App\Core\Database::setLaravelConnection($pdo);
    }

    public static function getInstance()
    {
        return \App\Core\Database::getInstance();
    }

    public static function getConnection()
    {
        return \App\Core\Database::getConnection();
    }

    public static function query($sql, $params = [])
    {
        return \App\Core\Database::query($sql, $params);
    }

    public static function lastInsertId()
    {
        return \App\Core\Database::lastInsertId();
    }

    public static function beginTransaction()
    {
        return \App\Core\Database::beginTransaction();
    }

    public static function commit()
    {
        return \App\Core\Database::commit();
    }

    public static function rollback()
    {
        return \App\Core\Database::rollback();
    }

    public static function getQueryStats()
    {
        return \App\Core\Database::getQueryStats();
    }

    public static function resetQueryLog(): void
    {
        \App\Core\Database::resetQueryLog();
    }

    public static function setSlowQueryThreshold($seconds): void
    {
        \App\Core\Database::setSlowQueryThreshold($seconds);
    }

    public static function setProfilingEnabled($enabled): void
    {
        \App\Core\Database::setProfilingEnabled($enabled);
    }
}
