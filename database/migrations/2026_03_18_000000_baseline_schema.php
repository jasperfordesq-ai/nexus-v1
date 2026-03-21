<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Baseline schema migration — loads the full 386-table production schema.
 *
 * On existing databases (production), every table already exists, so the
 * IF NOT EXISTS guards in the SQL will skip creation. Safe to run anywhere.
 *
 * On fresh databases, this creates the entire schema from scratch so that
 * `php artisan migrate` produces a working database.
 *
 * The SQL dump is stored at database/schema/nexus-baseline.sql and was
 * generated from the local dev database on 2026-03-21.
 *
 * The down() method is intentionally empty — this baseline is irreversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        $sqlPath = database_path('schema/nexus-baseline.sql');

        if (! file_exists($sqlPath)) {
            throw new RuntimeException(
                "Baseline schema SQL not found at {$sqlPath}. " .
                "Run: mysqldump --no-data nexus > database/schema/nexus-baseline.sql"
            );
        }

        $sql = file_get_contents($sqlPath);

        // Add IF NOT EXISTS to all CREATE TABLE statements so this is
        // safe to run on databases that already have these tables.
        $sql = preg_replace(
            '/CREATE TABLE `/i',
            'CREATE TABLE IF NOT EXISTS `',
            $sql
        );

        // Split on semicolons and execute each statement individually
        // (PDO doesn't support multiple statements in one exec by default)
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            fn(string $s) => strlen($s) > 5
        );

        foreach ($statements as $statement) {
            // Skip MySQL directives and empty statements
            if (str_starts_with($statement, '/*') || str_starts_with($statement, '--')) {
                continue;
            }
            DB::unprepared($statement);
        }
    }

    public function down(): void
    {
        // Intentionally empty — dropping 386 tables is destructive
        // and this baseline should never be rolled back.
    }
};
