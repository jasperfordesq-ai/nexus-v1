<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SSO engine (IT-Sec-05) — per-tenant OpenID Connect provider registry.
 *
 * Each row is one external identity provider a tenant accepts logins
 * from (Microsoft Entra ID, Google Workspace, Hivebrite, any
 * spec-compliant OIDC issuer). Adding a provider is configuration,
 * not code: the engine discovers endpoints from the issuer's
 * /.well-known/openid-configuration document.
 *
 * client_secret is Crypt-encrypted at rest. allowed_email_domains is
 * the auto-provisioning guard: with auto_provision on, only identities
 * whose email matches a listed domain may self-create an account.
 *
 * Guarded with Schema::hasTable for idempotency.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tenant_sso_providers')) {
            Schema::create('tenant_sso_providers', function (Blueprint $table) {
                $table->id();
                // tenants.id is signed int(11) — match the type
                $table->integer('tenant_id');
                // Max 20 chars: identities are stored in oauth_identities
                // as "sso:{tenant_id}:{provider_key}" within varchar(32).
                $table->string('provider_key', 20);
                $table->string('display_name', 100);
                $table->string('preset', 32)->default('generic');
                $table->string('issuer_url', 500);
                $table->string('client_id', 255);
                $table->text('client_secret_encrypted')->nullable();
                $table->string('scopes', 255)->default('openid profile email');
                $table->json('allowed_email_domains')->nullable();
                $table->boolean('auto_provision')->default(true);
                $table->boolean('is_enabled')->default(false);
                $table->integer('updated_by')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'provider_key'], 'sso_tenant_provider_unique');
                $table->index('tenant_id', 'sso_tenant_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_sso_providers');
    }
};
