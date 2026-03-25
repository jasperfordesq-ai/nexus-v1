<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add tenant_id to story_reactions, story_views, and story_poll_votes tables.
 *
 * These tables previously relied on indirect tenant scoping via FK to the stories
 * table. Adding explicit tenant_id follows the project pattern of direct tenant
 * scoping on every table and enables safe direct queries without a JOIN.
 */
return new class extends Migration
{
    private const TABLES = ['story_reactions', 'story_views', 'story_poll_votes'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            // Skip if tenant_id already exists (idempotent)
            if (Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }

            // Add the column (nullable initially so existing rows don't fail)
            DB::statement("ALTER TABLE `{$table}` ADD COLUMN `tenant_id` INT NULL AFTER `id`");

            // Backfill tenant_id from the parent stories table
            DB::statement("
                UPDATE `{$table}` t
                INNER JOIN stories s ON t.story_id = s.id
                SET t.tenant_id = s.tenant_id
            ");

            // Now make it NOT NULL and add the FK + index
            DB::statement("ALTER TABLE `{$table}` MODIFY COLUMN `tenant_id` INT NOT NULL");
            DB::statement("ALTER TABLE `{$table}` ADD KEY `idx_{$table}_tenant` (`tenant_id`)");

            // Add FK only if it doesn't already exist
            try {
                DB::statement("ALTER TABLE `{$table}` ADD CONSTRAINT `fk_{$table}_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE");
            } catch (\Throwable $e) {
                // FK may already exist or name collision — safe to ignore
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }

            try {
                DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `fk_{$table}_tenant`");
            } catch (\Throwable $e) {
                // Ignore if FK doesn't exist
            }

            try {
                DB::statement("ALTER TABLE `{$table}` DROP KEY `idx_{$table}_tenant`");
            } catch (\Throwable $e) {
                // Ignore if index doesn't exist
            }

            DB::statement("ALTER TABLE `{$table}` DROP COLUMN `tenant_id`");
        }
    }
};
