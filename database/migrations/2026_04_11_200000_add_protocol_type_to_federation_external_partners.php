<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add protocol_type column to federation_external_partners.
 *
 * Supports multiple federation protocols: nexus (default), timeoverflow,
 * komunitin (JSON:API), and credit_commons. Existing partners default to
 * 'nexus' — no behavior change for current production partners.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('federation_external_partners', 'protocol_type')) {
            return;
        }

        Schema::table('federation_external_partners', function (Blueprint $table) {
            $table->string('protocol_type', 30)->default('nexus')->after('auth_method')
                ->comment('Federation protocol: nexus, timeoverflow, komunitin, credit_commons');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('federation_external_partners', 'protocol_type')) {
            return;
        }

        Schema::table('federation_external_partners', function (Blueprint $table) {
            $table->dropColumn('protocol_type');
        });
    }
};
