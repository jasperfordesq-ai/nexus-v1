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
 * SOC13 — Social login (OAuth).
 *
 * Stores the link between a NEXUS user and an external OAuth provider
 * identity (Google, Apple, Facebook). One user can have multiple
 * provider identities; one provider identity can only be linked to a
 * single NEXUS user (globally — same Google account in two tenants
 * still maps to the same user-provider record).
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('oauth_identities')) {
            Schema::create('oauth_identities', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id')->index();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('provider', 32);
                $table->string('provider_user_id', 191);
                $table->string('provider_email', 191)->nullable();
                $table->string('avatar_url', 500)->nullable();
                $table->json('raw_payload')->nullable();
                $table->timestamp('linked_at')->useCurrent();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();

                $table->unique(['provider', 'provider_user_id'], 'oauth_identities_provider_uid_unique');
                $table->unique(['user_id', 'provider'], 'oauth_identities_user_provider_unique');
                $table->index('provider_user_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('oauth_identities');
    }
};
