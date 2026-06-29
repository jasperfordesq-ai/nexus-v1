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
        if (Schema::hasTable('vol_donations')) {
            Schema::table('vol_donations', function (Blueprint $table): void {
                if (!Schema::hasColumn('vol_donations', 'fund_code')) {
                    $table->string('fund_code', 80)->default('general')->after('giving_day_id')->index('idx_vd_fund_code');
                }
                if (!Schema::hasColumn('vol_donations', 'gift_aid_claim_status')) {
                    $table->string('gift_aid_claim_status', 32)->default('not_eligible')->after('status')->index('idx_vd_gift_aid_status');
                }
                if (!Schema::hasColumn('vol_donations', 'gift_aid_declaration_name')) {
                    $table->string('gift_aid_declaration_name', 160)->nullable()->after('gift_aid_claim_status');
                }
                if (!Schema::hasColumn('vol_donations', 'gift_aid_address_line1')) {
                    $table->string('gift_aid_address_line1', 190)->nullable()->after('gift_aid_declaration_name');
                }
                if (!Schema::hasColumn('vol_donations', 'gift_aid_address_line2')) {
                    $table->string('gift_aid_address_line2', 190)->nullable()->after('gift_aid_address_line1');
                }
                if (!Schema::hasColumn('vol_donations', 'gift_aid_town')) {
                    $table->string('gift_aid_town', 120)->nullable()->after('gift_aid_address_line2');
                }
                if (!Schema::hasColumn('vol_donations', 'gift_aid_postcode')) {
                    $table->string('gift_aid_postcode', 40)->nullable()->after('gift_aid_town')->index('idx_vd_gift_aid_postcode');
                }
                if (!Schema::hasColumn('vol_donations', 'gift_aid_country')) {
                    $table->string('gift_aid_country', 2)->nullable()->after('gift_aid_postcode');
                }
                if (!Schema::hasColumn('vol_donations', 'gift_aid_consented_at')) {
                    $table->timestamp('gift_aid_consented_at')->nullable()->after('gift_aid_country');
                }
                if (!Schema::hasColumn('vol_donations', 'gift_aid_claimed_at')) {
                    $table->timestamp('gift_aid_claimed_at')->nullable()->after('gift_aid_consented_at');
                }
            });
        }

        if (!Schema::hasTable('donation_disputes')) {
            Schema::create('donation_disputes', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedInteger('tenant_id');
                $table->unsignedBigInteger('vol_donation_id')->nullable();
                $table->string('stripe_dispute_id', 120);
                $table->string('payment_intent_id', 120)->nullable();
                $table->string('charge_id', 120)->nullable();
                $table->unsignedInteger('amount')->default(0);
                $table->string('currency', 3)->default('gbp');
                $table->string('status', 64)->default('needs_response');
                $table->string('reason', 120)->nullable();
                $table->timestamp('evidence_due_at')->nullable();
                $table->string('payment_route', 50)->default('platform_default');
                $table->string('stripe_account_id', 100)->nullable();
                $table->json('payload')->nullable();
                $table->timestamps();

                $table->unique('stripe_dispute_id');
                $table->index(['tenant_id', 'status']);
                $table->index(['tenant_id', 'created_at']);
                $table->index('payment_intent_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('donation_disputes');

        if (!Schema::hasTable('vol_donations')) {
            return;
        }

        Schema::table('vol_donations', function (Blueprint $table): void {
            foreach ([
                'gift_aid_claimed_at',
                'gift_aid_consented_at',
                'gift_aid_country',
                'gift_aid_postcode',
                'gift_aid_town',
                'gift_aid_address_line2',
                'gift_aid_address_line1',
                'gift_aid_declaration_name',
                'gift_aid_claim_status',
                'fund_code',
            ] as $column) {
                if (Schema::hasColumn('vol_donations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
