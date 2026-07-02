<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop stray `distance_km varchar(255)` columns from content/reference tables.
 *
 * These columns were added in error (a per-viewer proximity value has no
 * meaning as a stored column on users/categories/attributes). Because every
 * proximity query computes `(haversine) AS distance_km` and JOINs one of these
 * tables (typically `users` for the member location), the physical column
 * collided with the computed alias and made `ORDER BY distance_km` /
 * `HAVING distance_km` ambiguous — SQLSTATE[23000] 1052 — which broke the
 * Smart Matching cache warm-up, group recommendations, Explore, Events,
 * Listings, Marketplace, Caring, and Volunteer proximity searches.
 *
 * Verified on production before writing this migration: all four columns are
 * 100% NULL (0 non-null rows across users/categories/attributes/
 * listing_attributes), and no code writes to them — so the drop is
 * non-destructive to real data. The legitimate matching tables
 * (match_cache/match_approvals/match_history) use `distance_km decimal(8,2)`
 * and are intentionally left untouched.
 */
return new class extends Migration
{
    /** @var list<string> tables carrying the accidental varchar(255) column */
    private const STRAY_TABLES = ['users', 'categories', 'attributes', 'listing_attributes'];

    public function up(): void
    {
        foreach (self::STRAY_TABLES as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'distance_km')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropColumn('distance_km');
            });
        }
    }

    public function down(): void
    {
        // Reversible: recreate the (nullable, unused) columns as they were.
        foreach (self::STRAY_TABLES as $table) {
            if (!Schema::hasTable($table) || Schema::hasColumn($table, 'distance_km')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->string('distance_km', 255)->nullable();
            });
        }
    }
};
