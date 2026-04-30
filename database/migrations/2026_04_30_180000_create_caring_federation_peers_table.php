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
 * AG23 follow-up — Cross-Platform Federation Peers
 *
 * Each row represents a remote NEXUS install that this tenant has agreed to
 * federate hour-transfers with. The `shared_secret` is the per-pair HMAC
 * secret used to sign and verify outbound/inbound transfer payloads. Peers
 * are tenant-scoped: each cooperative chooses its own peers independently.
 *
 * Also adds an idempotency column to `caring_hour_transfers` so an inbound
 * transfer received twice (e.g. retry) is recognised and only credited once.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('caring_federation_peers')) {
            Schema::create('caring_federation_peers', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('tenant_id')->index();
                // Stable slug for the remote install + cooperative pair, e.g. "kiss-zug"
                $table->string('peer_slug', 100);
                // Public-facing display name shown to admins
                $table->string('display_name', 255);
                // Base URL of the remote NEXUS API, e.g. "https://api.kiss-bern.ch"
                // The inbound endpoint is appended automatically.
                $table->string('base_url', 500);
                // HMAC-SHA256 shared secret negotiated at partnership creation.
                // Stored as a hex string so the column never holds binary.
                $table->string('shared_secret', 128);
                // pending — awaiting remote ack
                // active  — verified and able to send/receive
                // suspended — paused by either side
                $table->enum('status', ['pending', 'active', 'suspended'])->default('pending');
                $table->text('notes')->nullable();
                $table->timestamp('last_handshake_at')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'peer_slug'], 'uq_caring_fed_peer_slug');
            });
        }

        if (Schema::hasTable('caring_hour_transfers')
            && ! Schema::hasColumn('caring_hour_transfers', 'remote_idempotency_key')) {
            Schema::table('caring_hour_transfers', function (Blueprint $table) {
                // Composite key built from `source_tenant_slug + ':' + source_transfer_id`
                // so an inbound row can be deduped even on retries.
                $table->string('remote_idempotency_key', 160)->nullable()->after('linked_transfer_id');
                $table->boolean('is_remote')->default(false)->after('remote_idempotency_key');

                $table->index('remote_idempotency_key', 'idx_caring_hour_xfer_idempotency');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('caring_hour_transfers')
            && Schema::hasColumn('caring_hour_transfers', 'remote_idempotency_key')) {
            Schema::table('caring_hour_transfers', function (Blueprint $table) {
                $table->dropIndex('idx_caring_hour_xfer_idempotency');
                $table->dropColumn(['remote_idempotency_key', 'is_remote']);
            });
        }

        Schema::dropIfExists('caring_federation_peers');
    }
};
