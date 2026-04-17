<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a `canonical_pair` column to `federation_partnerships`.
 *
 * The canonical pair stores a normalised "{min_id}-{max_id}" string for the
 * two tenant IDs involved. A unique index on this column prevents concurrent
 * race conditions where tenants A and B both submit requests simultaneously,
 * producing two rows — A→B and B→A — that each pass the existing one-directional
 * UNIQUE KEY on (tenant_id, partner_tenant_id).
 *
 * Back-fills existing rows so the migration is safe to run on a live database.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('federation_partnerships', 'canonical_pair')) {
            Schema::table('federation_partnerships', function (Blueprint $table) {
                $table->string('canonical_pair', 64)->nullable()->after('partner_tenant_id');
            });
        }

        // Back-fill all existing rows.
        DB::statement("
            UPDATE federation_partnerships
            SET canonical_pair = CONCAT(
                LEAST(tenant_id, partner_tenant_id),
                '-',
                GREATEST(tenant_id, partner_tenant_id)
            )
            WHERE canonical_pair IS NULL
        ");

        // Add unique index if it does not already exist.
        $indexExists = collect(DB::select("SHOW INDEX FROM federation_partnerships WHERE Key_name = 'idx_federation_canonical_pair'"))->isNotEmpty();
        if (! $indexExists) {
            Schema::table('federation_partnerships', function (Blueprint $table) {
                $table->unique('canonical_pair', 'idx_federation_canonical_pair');
            });
        }
    }

    public function down(): void
    {
        Schema::table('federation_partnerships', function (Blueprint $table) {
            if (Schema::hasColumn('federation_partnerships', 'canonical_pair')) {
                $table->dropUnique('idx_federation_canonical_pair');
                $table->dropColumn('canonical_pair');
            }
        });
    }
};
