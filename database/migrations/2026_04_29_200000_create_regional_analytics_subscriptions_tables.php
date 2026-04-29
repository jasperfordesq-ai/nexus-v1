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
 * AG59 — Paid Regional Analytics Product.
 *
 * Distinct from ADM1 internal admin analytics.
 * SaaS-style subscriptions sellable to municipalities and SME partners,
 * with bucketed/anonymised aggregates rendered into PDF reports.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('regional_analytics_subscriptions')) {
            Schema::create('regional_analytics_subscriptions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('partner_name', 191);
                $table->enum('partner_type', ['municipality', 'sme_partner'])->default('municipality');
                $table->string('contact_email', 191);
                $table->string('billing_email', 191)->nullable();
                $table->enum('plan_tier', ['basic', 'pro', 'enterprise'])->default('basic');
                $table->enum('status', ['trialing', 'active', 'past_due', 'cancelled'])->default('trialing');
                $table->string('stripe_subscription_id', 100)->nullable();
                $table->string('subscription_token', 80)->unique();
                $table->timestamp('trial_ends_at')->nullable();
                $table->timestamp('current_period_start')->nullable();
                $table->timestamp('current_period_end')->nullable();
                $table->unsignedInteger('monthly_price_cents')->default(0);
                $table->string('currency', 3)->default('CHF');
                $table->json('enabled_modules')->nullable();
                $table->unsignedBigInteger('created_by_admin_id')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'status']);
            });
        }

        if (! Schema::hasTable('regional_analytics_reports')) {
            Schema::create('regional_analytics_reports', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('subscription_id')->index();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->enum('report_type', ['monthly_summary', 'quarterly', 'on_demand'])->default('monthly_summary');
                $table->date('period_start');
                $table->date('period_end');
                $table->timestamp('generated_at')->nullable();
                $table->string('file_url', 500)->nullable();
                $table->json('payload_json')->nullable();
                $table->json('recipient_emails')->nullable();
                $table->enum('status', ['queued', 'generated', 'sent', 'failed'])->default('queued');
                $table->text('error_message')->nullable();
                $table->timestamps();

                $table->index(['subscription_id', 'period_start']);
            });
        }

        if (! Schema::hasTable('regional_analytics_access_log')) {
            Schema::create('regional_analytics_access_log', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('subscription_id')->index();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('accessed_endpoint', 255);
                $table->timestamp('accessed_at')->useCurrent();
                $table->string('ip_hash', 64)->nullable();
                $table->string('user_agent', 255)->nullable();

                $table->index(['subscription_id', 'accessed_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('regional_analytics_access_log');
        Schema::dropIfExists('regional_analytics_reports');
        Schema::dropIfExists('regional_analytics_subscriptions');
    }
};
