<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * federation_neighborhoods exists but its tenant junction table was never
 * created, so adding/removing tenants to a neighborhood — and deleting a
 * neighborhood — always failed with a 500 (AdminFederationNeighborhoods
 * Controller + FederationNeighborhoodService both read/write this table).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('federation_neighborhood_tenants')) {
            return;
        }

        Schema::create('federation_neighborhood_tenants', function (Blueprint $table) {
            $table->id();
            // Match federation_neighborhoods.id (signed int) and tenants.id.
            $table->integer('neighborhood_id');
            $table->integer('tenant_id');
            $table->dateTime('created_at')->useCurrent();

            $table->unique(['neighborhood_id', 'tenant_id'], 'uniq_neighborhood_tenant');
            $table->index('tenant_id', 'idx_fnt_tenant');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('federation_neighborhood_tenants');
    }
};
