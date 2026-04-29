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
 * AG60 — Banking / payment / admin API integration framework.
 *
 * Creates the tables that back the Partner API: external integrators
 * (Postfinance, ZKB, Raiffeisen, eUmzug, Gever, etc.) authenticate with
 * client credentials, exchange them for short-lived OAuth bearer tokens,
 * and call a curated subset of the platform API.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('api_partners')) {
            Schema::create('api_partners', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('name', 191);
                $table->string('slug', 100)->unique();
                $table->text('description')->nullable();
                $table->string('contact_email', 191)->nullable();
                $table->enum('status', ['pending', 'active', 'suspended'])->default('pending');
                $table->boolean('is_sandbox')->default(true);
                $table->json('allowed_scopes')->nullable();
                $table->json('allowed_ip_cidrs')->nullable();
                $table->unsignedInteger('rate_limit_per_minute')->default(60);
                $table->timestamps();

                $table->index(['tenant_id', 'status']);
            });
        }

        if (! Schema::hasTable('api_partner_credentials')) {
            Schema::create('api_partner_credentials', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('partner_id')->index();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('client_id', 64)->unique();
                $table->string('client_secret_hash', 255);
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        if (! Schema::hasTable('api_oauth_tokens')) {
            Schema::create('api_oauth_tokens', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('partner_id')->index();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('access_token_hash', 64)->index();
                $table->string('refresh_token_hash', 64)->nullable();
                $table->json('scopes')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        if (! Schema::hasTable('api_webhook_subscriptions')) {
            Schema::create('api_webhook_subscriptions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('partner_id')->index();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->json('event_types');
                $table->string('target_url', 500);
                $table->string('secret', 128);
                $table->enum('status', ['active', 'paused', 'failed'])->default('active');
                $table->timestamp('last_delivery_at')->nullable();
                $table->unsignedInteger('failure_count')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('api_call_log')) {
            Schema::create('api_call_log', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('partner_id')->nullable()->index();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->string('method', 10);
                $table->string('path', 255);
                $table->unsignedSmallInteger('status_code');
                $table->unsignedInteger('response_time_ms')->default(0);
                $table->string('ip', 64)->nullable();
                $table->string('user_agent', 255)->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['partner_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('api_call_log');
        Schema::dropIfExists('api_webhook_subscriptions');
        Schema::dropIfExists('api_oauth_tokens');
        Schema::dropIfExists('api_partner_credentials');
        Schema::dropIfExists('api_partners');
    }
};
