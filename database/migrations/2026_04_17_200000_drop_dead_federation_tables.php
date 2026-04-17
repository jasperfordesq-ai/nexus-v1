<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Drop dead federation tables that have zero code references.
 *
 * Tables dropped:
 *  - federation_notifications      (superseded by core notifications system)
 *  - federation_realtime_queue     (never implemented, no code references)
 *  - federation_tenant_settings    (settings now stored in tenants.configuration JSON)
 *
 * The down() method is intentionally empty — these tables are dead and
 * should not be recreated.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('federation_notifications')) {
            Schema::dropIfExists('federation_notifications');
        }
        if (Schema::hasTable('federation_realtime_queue')) {
            Schema::dropIfExists('federation_realtime_queue');
        }
        if (Schema::hasTable('federation_tenant_settings')) {
            Schema::dropIfExists('federation_tenant_settings');
        }
    }

    public function down(): void
    {
        // Intentionally empty — these tables are dead and should not be recreated.
    }
};
