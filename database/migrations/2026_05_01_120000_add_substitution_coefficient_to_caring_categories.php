<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds `substitution_coefficient` (DECIMAL(3,2), default 1.00) to the canonical
 * category table used by BOTH the volunteering module (vol_opportunities.category_id)
 * AND the caring community module (caring_support_relationships.category_id).
 *
 * The coefficient represents the fraction of an hour of formal/professional care
 * (e.g. Spitex) that an hour of community/peer support in this category genuinely
 * substitutes for, in line with KISS / Age-Stiftung methodology used by Swiss
 * cantonal social departments to evaluate the Pflege-CHF KPI.
 *
 * Examples:
 *   - personal hygiene / medication reminders: ~1.00 (direct substitution)
 *   - companionship / social visits: ~0.40 (lower formal-care equivalence)
 *   - transport to appointments: ~0.70
 *
 * The municipalRoi() admin endpoint multiplies approved hours by this coefficient
 * to produce the substitution-weighted hours figure used in formal-care offset CHF.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('categories')) {
            Schema::table('categories', function (Blueprint $table) {
                if (!Schema::hasColumn('categories', 'substitution_coefficient')) {
                    $table->decimal('substitution_coefficient', 3, 2)
                        ->default(1.00)
                        ->after('color')
                        ->comment('KISS/Age-Stiftung Pflege-CHF substitution coefficient (0.00–1.00). 1.00 = full formal-care equivalence.');
                }
            });
        }

        // caring_support_categories is a separate optional table some pilots have created
        // for caring-specific taxonomies. If it exists, mirror the column there for
        // consistency. Guarded by hasTable so this is a no-op on platforms without it.
        if (Schema::hasTable('caring_support_categories')) {
            Schema::table('caring_support_categories', function (Blueprint $table) {
                if (!Schema::hasColumn('caring_support_categories', 'substitution_coefficient')) {
                    $table->decimal('substitution_coefficient', 3, 2)
                        ->default(1.00)
                        ->comment('KISS/Age-Stiftung Pflege-CHF substitution coefficient (0.00–1.00).');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('categories') && Schema::hasColumn('categories', 'substitution_coefficient')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->dropColumn('substitution_coefficient');
            });
        }

        if (Schema::hasTable('caring_support_categories') && Schema::hasColumn('caring_support_categories', 'substitution_coefficient')) {
            Schema::table('caring_support_categories', function (Blueprint $table) {
                $table->dropColumn('substitution_coefficient');
            });
        }
    }
};
