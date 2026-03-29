<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('peer_endorsements')) {
            Schema::create('peer_endorsements', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('tenant_id');
                $table->unsignedInteger('endorser_id');
                $table->unsignedInteger('endorsed_id');
                $table->timestamps();

                $table->unique(['tenant_id', 'endorser_id', 'endorsed_id'], 'uniq_peer_endorsement');
                $table->index('tenant_id', 'idx_pe_tenant');
                $table->index('endorsed_id', 'idx_pe_endorsed');
            });
        }

        // Add nullable organization_id to member_verification_badges
        if (Schema::hasTable('member_verification_badges') && ! Schema::hasColumn('member_verification_badges', 'organization_id')) {
            Schema::table('member_verification_badges', function (Blueprint $table) {
                $table->unsignedInteger('organization_id')->nullable()->after('verified_by');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('peer_endorsements');

        if (Schema::hasTable('member_verification_badges') && Schema::hasColumn('member_verification_badges', 'organization_id')) {
            Schema::table('member_verification_badges', function (Blueprint $table) {
                $table->dropColumn('organization_id');
            });
        }
    }
};
