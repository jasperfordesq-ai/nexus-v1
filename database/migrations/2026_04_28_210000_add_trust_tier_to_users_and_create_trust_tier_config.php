<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Add trust_tier column to users (0=newcomer, 1=member, 2=trusted, 3=verified, 4=coordinator)
        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'trust_tier')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedTinyInteger('trust_tier')->default(0)->after('id');
            });
        }

        // Per-tenant tier configuration
        if (!Schema::hasTable('caring_trust_tier_config')) {
            Schema::create('caring_trust_tier_config', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('tenant_id')->unique();
                $table->json('criteria'); // thresholds per tier
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'trust_tier')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('trust_tier');
            });
        }

        Schema::dropIfExists('caring_trust_tier_config');
    }
};
