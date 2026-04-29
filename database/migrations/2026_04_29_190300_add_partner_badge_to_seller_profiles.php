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
 * AG48 — Marketplace partner badge timestamp on seller profiles.
 * Granted on first approved listing after onboarding completion.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('marketplace_seller_profiles')) {
            return;
        }

        Schema::table('marketplace_seller_profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('marketplace_seller_profiles', 'marketplace_partner_badge_at')) {
                $table->timestamp('marketplace_partner_badge_at')
                    ->nullable()
                    ->after('onboarding_completed_at');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('marketplace_seller_profiles')) {
            return;
        }

        Schema::table('marketplace_seller_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('marketplace_seller_profiles', 'marketplace_partner_badge_at')) {
                $table->dropColumn('marketplace_partner_badge_at');
            }
        });
    }
};
