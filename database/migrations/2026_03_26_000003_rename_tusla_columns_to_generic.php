<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rename Ireland-specific "tusla_*" columns to generic "authority_*" names.
     *
     * Tusla is the Irish Child and Family Agency — these columns should use
     * locale-neutral names since Project NEXUS is a global platform.
     */
    public function up(): void
    {
        if (!Schema::hasTable('vol_safeguarding_incidents')) {
            return;
        }

        if (Schema::hasColumn('vol_safeguarding_incidents', 'tusla_notified')
            && !Schema::hasColumn('vol_safeguarding_incidents', 'authority_notified')) {
            Schema::table('vol_safeguarding_incidents', function (Blueprint $table) {
                $table->renameColumn('tusla_notified', 'authority_notified');
            });
        }

        if (Schema::hasColumn('vol_safeguarding_incidents', 'tusla_reference')
            && !Schema::hasColumn('vol_safeguarding_incidents', 'authority_reference')) {
            Schema::table('vol_safeguarding_incidents', function (Blueprint $table) {
                $table->renameColumn('tusla_reference', 'authority_reference');
            });
        }
    }

    /**
     * Reverse the column renames back to tusla_* originals.
     */
    public function down(): void
    {
        if (!Schema::hasTable('vol_safeguarding_incidents')) {
            return;
        }

        if (Schema::hasColumn('vol_safeguarding_incidents', 'authority_notified')
            && !Schema::hasColumn('vol_safeguarding_incidents', 'tusla_notified')) {
            Schema::table('vol_safeguarding_incidents', function (Blueprint $table) {
                $table->renameColumn('authority_notified', 'tusla_notified');
            });
        }

        if (Schema::hasColumn('vol_safeguarding_incidents', 'authority_reference')
            && !Schema::hasColumn('vol_safeguarding_incidents', 'tusla_reference')) {
            Schema::table('vol_safeguarding_incidents', function (Blueprint $table) {
                $table->renameColumn('authority_reference', 'tusla_reference');
            });
        }
    }
};
