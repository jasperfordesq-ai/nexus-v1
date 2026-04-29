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
 * AG54 — Verein membership fee collection.
 *
 * Three tenant-scoped tables:
 *   - verein_membership_fees     (one fee config per Verein/club)
 *   - verein_member_dues         (per-member, per-year dues record)
 *   - verein_dues_payments       (Stripe payment audit ledger)
 *
 * organization_id references vol_organizations.id (org_type='club' = Verein),
 * matching the existing AG30 Verein bulk-import + scoped admin pattern.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('verein_membership_fees')) {
            Schema::create('verein_membership_fees', function (Blueprint $table): void {
                $table->id();
                $table->unsignedInteger('organization_id');
                $table->unsignedInteger('tenant_id');
                $table->unsignedInteger('fee_amount_cents');
                $table->string('currency', 3)->default('CHF');
                $table->enum('billing_cycle', ['annual', 'biennial', 'monthly'])->default('annual');
                $table->unsignedSmallInteger('grace_period_days')->default(30);
                $table->unsignedInteger('late_fee_cents')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique('organization_id', 'verein_fees_org_unique');
                $table->index(['tenant_id', 'is_active'], 'verein_fees_tenant_active_idx');
            });
        }

        if (!Schema::hasTable('verein_member_dues')) {
            Schema::create('verein_member_dues', function (Blueprint $table): void {
                $table->id();
                $table->unsignedInteger('organization_id');
                $table->unsignedInteger('tenant_id');
                $table->unsignedInteger('user_id');
                $table->unsignedSmallInteger('membership_year');
                $table->unsignedInteger('amount_cents');
                $table->string('currency', 3)->default('CHF');
                $table->enum('status', ['pending', 'paid', 'overdue', 'waived', 'refunded'])
                    ->default('pending');
                $table->date('due_date');
                $table->timestamp('paid_at')->nullable();
                $table->string('stripe_payment_intent_id', 191)->nullable();
                $table->unsignedInteger('reminder_count')->default(0);
                $table->timestamp('last_reminder_at')->nullable();
                $table->unsignedInteger('waived_by_admin_id')->nullable();
                $table->string('waived_reason', 500)->nullable();
                $table->timestamp('refunded_at')->nullable();
                $table->timestamps();

                $table->unique(
                    ['organization_id', 'user_id', 'membership_year'],
                    'verein_dues_org_user_year_unique'
                );
                $table->index(['organization_id', 'membership_year'], 'verein_dues_org_year_idx');
                $table->index(['user_id', 'status'], 'verein_dues_user_status_idx');
                $table->index(['tenant_id', 'status'], 'verein_dues_tenant_status_idx');
                $table->index('stripe_payment_intent_id', 'verein_dues_pi_idx');
            });
        }

        if (!Schema::hasTable('verein_dues_payments')) {
            Schema::create('verein_dues_payments', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('dues_id');
                $table->unsignedInteger('tenant_id');
                $table->string('stripe_payment_intent_id', 191);
                $table->unsignedInteger('amount_cents');
                $table->string('currency', 3)->default('CHF');
                $table->timestamp('paid_at');
                $table->string('payment_method', 50)->nullable();
                $table->string('receipt_url', 500)->nullable();
                $table->timestamps();

                $table->unique('stripe_payment_intent_id', 'verein_dues_pmts_pi_unique');
                $table->index(['tenant_id', 'paid_at'], 'verein_dues_pmts_tenant_paid_idx');
                $table->index('dues_id', 'verein_dues_pmts_dues_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('verein_dues_payments');
        Schema::dropIfExists('verein_member_dues');
        Schema::dropIfExists('verein_membership_fees');
    }
};
