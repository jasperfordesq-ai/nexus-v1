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
 * AG48 — Add onboarding_completed_at and opening_hours columns to marketplace_seller_profiles.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('marketplace_seller_profiles')) {
            return;
        }

        Schema::table('marketplace_seller_profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('marketplace_seller_profiles', 'opening_hours')) {
                // Format: {"mon":{"open":"09:00","close":"18:00"},"tue":...,"sun":null}
                $table->json('opening_hours')->nullable()->after('business_address');
            }

            if (!Schema::hasColumn('marketplace_seller_profiles', 'onboarding_completed_at')) {
                // NULL = wizard not yet finished; set on completion
                $table->datetime('onboarding_completed_at')->nullable()->after('joined_marketplace_at');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('marketplace_seller_profiles')) {
            return;
        }

        Schema::table('marketplace_seller_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('marketplace_seller_profiles', 'onboarding_completed_at')) {
                $table->dropColumn('onboarding_completed_at');
            }

            if (Schema::hasColumn('marketplace_seller_profiles', 'opening_hours')) {
                $table->dropColumn('opening_hours');
            }
        });
    }
};
