<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fixes caring_kiss_treffen.event_id FK behaviour:
 *   1. Makes event_id NULLABLE — AGM minutes are an audit artefact and must
 *      survive even if the originating event row is purged.
 *   2. Switches the FK ON DELETE rule from CASCADE to SET NULL so deleting
 *      an event no longer wipes the historical Treffen record.
 *
 * The column type stays signed INT to match events.id (int(11) signed). The
 * original create migration's $table->integer('event_id') already matches; no
 * sign change is needed — only the nullability + cascade rule.
 *
 * Refs: caring-community audit C6.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('caring_kiss_treffen')) {
            return;
        }

        // Drop existing FK (Laravel default name: caring_kiss_treffen_event_id_foreign)
        try {
            DB::statement('ALTER TABLE `caring_kiss_treffen` DROP FOREIGN KEY `caring_kiss_treffen_event_id_foreign`');
        } catch (\Throwable $e) {
            // FK may have a different name or already be gone — ignore.
        }

        // Make column nullable (still signed int to match events.id)
        DB::statement('ALTER TABLE `caring_kiss_treffen` MODIFY `event_id` INT(11) NULL');

        // Re-add FK with ON DELETE SET NULL to preserve AGM minutes audit history
        DB::statement(
            'ALTER TABLE `caring_kiss_treffen` '
            . 'ADD CONSTRAINT `caring_kiss_treffen_event_id_foreign` '
            . 'FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE SET NULL'
        );
    }

    public function down(): void
    {
        if (!Schema::hasTable('caring_kiss_treffen')) {
            return;
        }

        try {
            DB::statement('ALTER TABLE `caring_kiss_treffen` DROP FOREIGN KEY `caring_kiss_treffen_event_id_foreign`');
        } catch (\Throwable $e) {
            // ignore
        }

        // Restoring NOT NULL is unsafe if any rows have NULL event_id.
        // Only restore the cascade FK; leave nullability as-is to avoid data loss on rollback.
        DB::statement(
            'ALTER TABLE `caring_kiss_treffen` '
            . 'ADD CONSTRAINT `caring_kiss_treffen_event_id_foreign` '
            . 'FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE'
        );
    }
};
