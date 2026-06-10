<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant-configurable data retention policies (IT-Data-03).
 *
 * tenant_retention_policies — one row per (tenant, data type): how many
 * days to retain, what to do when the period lapses (delete only in v1;
 * the action column leaves room for anonymize/archive), and whether the
 * policy is enabled. Policies are opt-in: no row / disabled row means
 * data is retained indefinitely, matching previous behaviour.
 *
 * tenant_retention_runs — audit trail of every enforcement pass so
 * admins can evidence disposal (who/what/when/how many rows).
 *
 * Guarded with Schema::hasTable for idempotency.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tenant_retention_policies')) {
            Schema::create('tenant_retention_policies', function (Blueprint $table) {
                $table->id();
                // tenants.id is signed int(11) — match the type
                $table->integer('tenant_id');
                $table->string('data_type', 50);
                $table->unsignedInteger('retention_days');
                $table->string('action', 20)->default('delete');
                $table->boolean('is_enabled')->default(false);
                $table->integer('updated_by')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'data_type'], 'retention_tenant_type_unique');
            });
        }

        if (!Schema::hasTable('tenant_retention_runs')) {
            Schema::create('tenant_retention_runs', function (Blueprint $table) {
                $table->id();
                $table->integer('tenant_id');
                $table->string('data_type', 50);
                $table->string('action', 20);
                $table->unsignedInteger('retention_days');
                $table->unsignedInteger('affected_rows')->default(0);
                $table->string('status', 20)->default('completed');
                $table->string('error', 500)->nullable();
                $table->timestamp('ran_at')->useCurrent();

                $table->index(['tenant_id', 'data_type', 'ran_at'], 'retention_runs_tenant_type_ran');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_retention_runs');
        Schema::dropIfExists('tenant_retention_policies');
    }
};
