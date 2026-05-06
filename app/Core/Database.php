<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Core;

use Illuminate\Support\Facades\DB;
use PDO;
use PDOStatement;

/**
 * Compatibility wrapper for legacy regression tests and migrated services that
 * still expect the pre-Laravel Database facade contract.
 */
final class Database
{
    public static function getInstance(): PDO
    {
        return DB::connection()->getPdo();
    }

    public static function query(string $sql, array $params = []): PDOStatement
    {
        $statement = self::getInstance()->prepare($sql);
        $statement->execute($params);

        return $statement;
    }
}
