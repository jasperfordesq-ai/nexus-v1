<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Add payment tracking columns to identity_verification_sessions
 * and seed default verification fee for all tenants.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Add payment columns to identity_verification_sessions
        if (Schema::hasTable('identity_verification_sessions')) {
            if (!Schema::hasColumn('identity_verification_sessions', 'stripe_payment_intent_id')) {
                Schema::table('identity_verification_sessions', function (Blueprint $table) {
                    $table->string('stripe_payment_intent_id', 255)->nullable()->after('metadata');
                    $table->integer('verification_fee_amount')->nullable()->after('stripe_payment_intent_id');
                    $table->enum('payment_status', ['none', 'pending', 'completed', 'failed'])->default('none')->after('verification_fee_amount');
                });
            }
        }

        // 2. Seed default verification fee (€5.00 = 500 cents) for all existing tenants
        if (Schema::hasTable('tenant_settings') && Schema::hasTable('tenants')) {
            DB::statement("
                INSERT INTO tenant_settings (tenant_id, setting_key, setting_value, setting_type)
                SELECT id, 'identity_verification_fee_cents', '500', 'integer'
                FROM tenants t
                WHERE NOT EXISTS (
                    SELECT 1 FROM tenant_settings ts
                    WHERE ts.tenant_id = t.id AND ts.setting_key = 'identity_verification_fee_cents'
                )
            ");
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('identity_verification_sessions')) {
            Schema::table('identity_verification_sessions', function (Blueprint $table) {
                $table->dropColumn(['stripe_payment_intent_id', 'verification_fee_amount', 'payment_status']);
            });
        }

        DB::table('tenant_settings')->where('setting_key', 'identity_verification_fee_cents')->delete();
    }
};
