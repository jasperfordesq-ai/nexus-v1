<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Federated inbound reviews originate on a remote node, so their reviewer is not
 * a local user. The reviews.reviewer_id column was NOT NULL with a FK to users,
 * which made inbound federated reviews fail with a FK/constraint error (HTTP 500
 * on /v2/federation/ingest/reviews). Allow NULL so a federated review can be
 * stored with reviewer_id = NULL (its origin is captured by reviewer_tenant_id +
 * external_partner_id). Local reviews still set reviewer_id and the FK still
 * applies to non-null values, preserving ON DELETE CASCADE integrity.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('reviews') || !Schema::hasColumn('reviews', 'reviewer_id')) {
            return;
        }
        $col = collect(DB::select("SHOW COLUMNS FROM `reviews` WHERE Field = 'reviewer_id'"))->first();
        if ($col && strtoupper((string) ($col->Null ?? '')) === 'NO') {
            DB::statement('ALTER TABLE `reviews` MODIFY `reviewer_id` INT NULL');
        }
    }

    public function down(): void
    {
        // Intentionally not reverting: federated reviews may legitimately hold a
        // NULL reviewer_id, so restoring NOT NULL could fail on existing data.
    }
};
