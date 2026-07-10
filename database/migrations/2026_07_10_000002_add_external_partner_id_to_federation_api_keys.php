<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bind federation API keys to an external partner row (2026-07-10 audit M3).
 *
 * The native-ingest endpoints authenticate a federation_api_keys row, but the
 * external-partner identity — and with it the per-partner allow_* permission
 * flags — was chosen from the client-supplied X-Federation-Partner-ID header,
 * constrained only to the same tenant. A key issued for partner A could
 * therefore impersonate partner B: write, overwrite, or mass-retract B's
 * federated entities and inherit B's permissions. This column is the
 * server-side binding: the ingest controller resolves the partner from the
 * authenticated key row and ignores the header. NULL = key not linked to any
 * partner (permission-gated ingest then fails closed).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('federation_api_keys')) {
            return;
        }

        Schema::table('federation_api_keys', function (Blueprint $table) {
            if (!Schema::hasColumn('federation_api_keys', 'external_partner_id')) {
                $table->unsignedInteger('external_partner_id')->nullable()->after('platform_id')
                    ->comment('federation_external_partners.id this key acts as; NULL = unlinked');
                $table->index('external_partner_id', 'idx_fak_external_partner');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('federation_api_keys')) {
            return;
        }

        Schema::table('federation_api_keys', function (Blueprint $table) {
            if (Schema::hasColumn('federation_api_keys', 'external_partner_id')) {
                $table->dropIndex('idx_fak_external_partner');
                $table->dropColumn('external_partner_id');
            }
        });
    }
};
