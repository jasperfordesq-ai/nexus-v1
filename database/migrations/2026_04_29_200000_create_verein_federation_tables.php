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
 * AG55 — Verein-to-Verein federation within a Gemeinde.
 *
 * Three tables for cross-Verein event sharing and member discovery
 * within a single municipality:
 *  - verein_federation_consents — opt-in marker per Verein
 *  - verein_event_shares        — events shared by source to target
 *  - verein_cross_invitations   — member-to-member cross invitations
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('verein_federation_consents')) {
            Schema::create('verein_federation_consents', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedInteger('organization_id');
                $table->unsignedInteger('tenant_id');
                $table->string('sharing_scope', 20)->default('none');
                $table->string('municipality_code', 64)->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('opted_in_by_admin_id')->nullable();
                $table->timestamp('opted_in_at')->nullable();
                $table->timestamps();

                $table->unique('organization_id', 'verein_fed_consent_org_unique');
                $table->index(['tenant_id', 'municipality_code', 'is_active'], 'verein_fed_consent_lookup_idx');
            });
        }

        if (!Schema::hasTable('verein_event_shares')) {
            Schema::create('verein_event_shares', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedInteger('source_organization_id');
                $table->unsignedInteger('target_organization_id');
                $table->unsignedInteger('event_id');
                $table->unsignedInteger('tenant_id');
                $table->timestamp('shared_at')->useCurrent();
                $table->string('status', 16)->default('active');
                $table->timestamps();

                $table->index(['target_organization_id', 'status'], 'verein_event_shares_target_idx');
                $table->index('event_id', 'verein_event_shares_event_idx');
                $table->index(['tenant_id', 'source_organization_id'], 'verein_event_shares_source_idx');
            });
        }

        if (!Schema::hasTable('verein_cross_invitations')) {
            Schema::create('verein_cross_invitations', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedInteger('source_organization_id');
                $table->unsignedInteger('target_organization_id');
                $table->unsignedInteger('tenant_id');
                $table->unsignedInteger('inviter_user_id');
                $table->unsignedInteger('invitee_user_id');
                $table->text('message')->nullable();
                $table->string('status', 16)->default('sent');
                $table->timestamp('sent_at')->useCurrent();
                $table->timestamp('responded_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();

                $table->index(['target_organization_id', 'status'], 'verein_cross_inv_target_idx');
                $table->index(['invitee_user_id', 'status'], 'verein_cross_inv_invitee_idx');
                $table->index(['tenant_id', 'status', 'expires_at'], 'verein_cross_inv_expiry_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('verein_cross_invitations');
        Schema::dropIfExists('verein_event_shares');
        Schema::dropIfExists('verein_federation_consents');
    }
};
