<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a composite index on listings(tenant_id, user_id) to support the very
 * common "fetch all listings owned by user X in tenant Y" query pattern. The
 * existing single-column tenant_id and user_id indexes force the optimizer to
 * pick a less-selective path; the new index covers both predicates at once.
 *
 * Used by the onboarding firstOrCreate idempotency check, profile pages, and
 * any per-user listing dashboard.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('listings')) {
            return;
        }

        $exists = collect(DB::select('SHOW INDEX FROM listings'))
            ->contains(fn ($row) => $row->Key_name === 'idx_listings_tenant_user');

        if (!$exists) {
            DB::statement('ALTER TABLE listings ADD INDEX idx_listings_tenant_user (tenant_id, user_id)');
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('listings')) {
            return;
        }

        $exists = collect(DB::select('SHOW INDEX FROM listings'))
            ->contains(fn ($row) => $row->Key_name === 'idx_listings_tenant_user');

        if ($exists) {
            DB::statement('ALTER TABLE listings DROP INDEX idx_listings_tenant_user');
        }
    }
};
