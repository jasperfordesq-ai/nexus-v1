<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * SUPERSEDED — this migration is kept for historical record only.
 *
 * The full schema is now managed via Laravel's native schema dump at
 * database/schema/mysql-schema.sql. On a fresh database, `php artisan migrate`
 * loads that dump first (which includes laravel_migrations data), so this
 * migration is never re-executed.
 *
 * The old nexus-baseline.sql file has been removed.
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

        // Disable FK checks so tables can be created in any order
        DB::unprepared('SET FOREIGN_KEY_CHECKS=0');

        foreach ($statements as $statement) {
            // Skip MySQL directives and empty statements
            if (str_starts_with($statement, '/*') || str_starts_with($statement, '--')) {
                continue;
            }
            DB::unprepared($statement);
        }

        DB::unprepared('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        // Intentionally empty — dropping 386 tables is destructive
        // and this baseline should never be rolled back.
    }
};
