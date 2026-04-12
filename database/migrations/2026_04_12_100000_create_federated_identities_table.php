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
 * federated_identities — maps local NEXUS users to external identities on
 * federation partners. Used to resolve inbound webhooks (e.g. reviews from a
 * remote tenant) back to the correct local User, and to look up the remote
 * identity when pushing data outbound.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('federated_identities')) {
            return;
        }

        Schema::create('federated_identities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('local_user_id');
            $table->unsignedBigInteger('partner_id');
            $table->string('external_user_id');
            $table->string('external_handle')->nullable();
            $table->text('attestation_signature')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->unique(['partner_id', 'external_user_id'], 'uniq_fed_identity_partner_ext');
            $table->index('local_user_id', 'idx_fed_identity_local_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('federated_identities');
    }
};
