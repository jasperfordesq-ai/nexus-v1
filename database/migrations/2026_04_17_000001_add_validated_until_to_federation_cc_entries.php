<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add validated_until to federation_cc_entries.
 *
 * CC protocol: validated (V) entries expire after validated_window seconds.
 * This column stores the absolute timestamp at which a validated entry expires.
 * Clients use secs_valid_left (computed as validated_until − now) to determine
 * whether a validated entry is still actionable.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('federation_cc_entries') && !Schema::hasColumn('federation_cc_entries', 'validated_until')) {
            Schema::table('federation_cc_entries', function (Blueprint $table) {
                $table->timestamp('validated_until')->nullable()
                    ->after('written_at')
                    ->comment('Expiry timestamp for validated (V) entries; null = not validated or no expiry');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('federation_cc_entries') && Schema::hasColumn('federation_cc_entries', 'validated_until')) {
            Schema::table('federation_cc_entries', function (Blueprint $table) {
                $table->dropColumn('validated_until');
            });
        }
    }
};
