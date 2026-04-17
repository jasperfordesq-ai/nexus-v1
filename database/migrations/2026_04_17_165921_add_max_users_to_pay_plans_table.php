<?php

// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pay_plans', function (Blueprint $table) {
            if (! Schema::hasColumn('pay_plans', 'max_users')) {
                $table->unsignedInteger('max_users')->nullable()->after('max_menu_items')
                      ->comment('Maximum active members per tenant; null = unlimited');
            }
        });

        // Zero out the enterprise plan price until pricing is decided
        DB::table('pay_plans')
            ->where('slug', 'enterprise')
            ->update([
                'price_monthly' => 0.00,
                'price_yearly'  => 0.00,
            ]);
    }

    public function down(): void
    {
        Schema::table('pay_plans', function (Blueprint $table) {
            if (Schema::hasColumn('pay_plans', 'max_users')) {
                $table->dropColumn('max_users');
            }
        });
    }
};
