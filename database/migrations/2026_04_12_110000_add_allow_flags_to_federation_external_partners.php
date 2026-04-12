<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add per-partner federation push flags for the new outbound domains
 * (connections, volunteering, member sync).  The existing flags
 * allow_member_search / allow_listing_search / allow_messaging /
 * allow_transactions / allow_events / allow_groups cover the older domains.
 *
 * Defaults to 0 (off) — admins must opt-in per partner, matching the
 * conservative default used for allow_events / allow_groups.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('federation_external_partners')) {
            return;
        }

        Schema::table('federation_external_partners', function (Blueprint $table) {
            if (!Schema::hasColumn('federation_external_partners', 'allow_connections')) {
                $table->boolean('allow_connections')->default(false)->after('allow_groups');
            }
            if (!Schema::hasColumn('federation_external_partners', 'allow_volunteering')) {
                $table->boolean('allow_volunteering')->default(false)->after('allow_connections');
            }
            if (!Schema::hasColumn('federation_external_partners', 'allow_member_sync')) {
                $table->boolean('allow_member_sync')->default(false)->after('allow_volunteering');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('federation_external_partners')) {
            return;
        }

        Schema::table('federation_external_partners', function (Blueprint $table) {
            foreach (['allow_connections', 'allow_volunteering', 'allow_member_sync'] as $col) {
                if (Schema::hasColumn('federation_external_partners', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
