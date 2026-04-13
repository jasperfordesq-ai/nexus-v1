<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * federation_webhook_nonces — replay-protection nonce store for federation
 * external webhooks.
 *
 * Paired with the 5-min TIMESTAMP_TOLERANCE window in
 * FederationExternalWebhookController::TIMESTAMP_TOLERANCE, a nonce lets us
 * block replay of a signed+within-window payload. Rows older than the
 * tolerance window are safe to purge (a GC cron can trim anything older
 * than ~10 min).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('federation_webhook_nonces')) {
            return;
        }

        Schema::create('federation_webhook_nonces', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('partner_id');
            $table->string('nonce', 128);
            $table->timestamp('seen_at')->useCurrent();

            // Same nonce may legitimately be reused across different partners
            // (they don't share nonce namespaces), so scope uniqueness by partner.
            $table->unique(['partner_id', 'nonce'], 'uk_fwn_partner_nonce');
            $table->index('seen_at', 'idx_fwn_seen_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('federation_webhook_nonces');
    }
};
