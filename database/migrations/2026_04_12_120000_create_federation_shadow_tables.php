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
 * Create shadow tables for inbound federation push events.
 *
 * These tables mirror remote-partner entities (events, groups, connection
 * requests, volunteering opportunities, listings, member-profile updates)
 * so downstream subsystems (feed, search, notifications) can surface
 * federated content without touching the canonical local tables.
 *
 * Naming note: `federation_connections` already exists with a different
 * schema (Nexus↔Nexus user-to-user connections). To avoid collision the
 * inbound partner-originated shadow uses `federation_inbound_connections`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('federation_events')) {
            Schema::create('federation_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('external_partner_id')->index();
                $table->string('external_id', 128);
                $table->string('title', 500);
                $table->text('description')->nullable();
                $table->dateTime('starts_at')->nullable();
                $table->dateTime('ends_at')->nullable();
                $table->string('location', 500)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(['external_partner_id', 'external_id'], 'uk_fed_events_partner_ext');
            });
        }

        if (!Schema::hasTable('federation_groups')) {
            Schema::create('federation_groups', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('external_partner_id')->index();
                $table->string('external_id', 128);
                $table->string('name', 500);
                $table->text('description')->nullable();
                $table->string('privacy', 32)->default('public');
                $table->unsignedInteger('member_count')->default(0);
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(['external_partner_id', 'external_id'], 'uk_fed_groups_partner_ext');
            });
        }

        if (!Schema::hasTable('federation_inbound_connections')) {
            Schema::create('federation_inbound_connections', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('external_partner_id')->index();
                $table->unsignedBigInteger('local_user_id');
                $table->string('external_user_id', 128);
                $table->string('status', 32)->default('pending');
                $table->string('message', 1000)->nullable();
                $table->timestamps();
                $table->unique(
                    ['external_partner_id', 'local_user_id', 'external_user_id'],
                    'uk_fed_inbound_conn'
                );
                $table->index('local_user_id', 'idx_fed_inbound_conn_local_user');
            });
        }

        if (!Schema::hasTable('federation_volunteering')) {
            Schema::create('federation_volunteering', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('external_partner_id')->index();
                $table->string('external_id', 128);
                $table->string('title', 500);
                $table->text('description')->nullable();
                $table->decimal('hours_requested', 8, 2)->nullable();
                $table->string('location', 500)->nullable();
                $table->dateTime('starts_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(['external_partner_id', 'external_id'], 'uk_fed_vol_partner_ext');
            });
        }

        if (!Schema::hasTable('federation_listings')) {
            Schema::create('federation_listings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('external_partner_id')->index();
                $table->string('external_id', 128);
                $table->string('title', 500);
                $table->text('description')->nullable();
                $table->string('type', 32)->nullable();
                $table->string('category', 128)->nullable();
                $table->string('external_user_id', 128)->nullable();
                $table->string('external_user_name', 255)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(['external_partner_id', 'external_id'], 'uk_fed_listings_partner_ext');
            });
        }

        if (!Schema::hasTable('federation_members')) {
            Schema::create('federation_members', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('external_partner_id')->index();
                $table->string('external_id', 128);
                $table->string('username', 255)->nullable();
                $table->string('display_name', 255)->nullable();
                $table->text('bio')->nullable();
                $table->string('location', 255)->nullable();
                $table->string('avatar_url', 1000)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('profile_updated_at')->nullable();
                $table->timestamps();
                $table->unique(['external_partner_id', 'external_id'], 'uk_fed_members_partner_ext');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('federation_members');
        Schema::dropIfExists('federation_listings');
        Schema::dropIfExists('federation_volunteering');
        Schema::dropIfExists('federation_inbound_connections');
        Schema::dropIfExists('federation_groups');
        Schema::dropIfExists('federation_events');
    }
};
