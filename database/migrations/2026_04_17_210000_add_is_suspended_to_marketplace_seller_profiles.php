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
    public function up(): void
    {
        if (Schema::hasTable('marketplace_seller_profiles') && ! Schema::hasColumn('marketplace_seller_profiles', 'is_suspended')) {
            Schema::table('marketplace_seller_profiles', function (Blueprint $table) {
                $table->boolean('is_suspended')->default(false)->after('is_community_endorsed');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('marketplace_seller_profiles', 'is_suspended')) {
            Schema::table('marketplace_seller_profiles', function (Blueprint $table) {
                $table->dropColumn('is_suspended');
            });
        }
    }
};
