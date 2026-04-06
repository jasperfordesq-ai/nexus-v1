<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fix tenant_id column type in marketplace_delivery_offers.
 *
 * Was unsignedInteger (INT), should be unsignedBigInteger (BIGINT UNSIGNED)
 * to match all other marketplace tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('marketplace_delivery_offers')) {
            return;
        }

        // Change tenant_id from INT UNSIGNED to BIGINT UNSIGNED
        DB::statement('ALTER TABLE `marketplace_delivery_offers` MODIFY COLUMN `tenant_id` BIGINT UNSIGNED NOT NULL DEFAULT 1');
    }

    public function down(): void
    {
        if (!Schema::hasTable('marketplace_delivery_offers')) {
            return;
        }

        DB::statement('ALTER TABLE `marketplace_delivery_offers` MODIFY COLUMN `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1');
    }
};
