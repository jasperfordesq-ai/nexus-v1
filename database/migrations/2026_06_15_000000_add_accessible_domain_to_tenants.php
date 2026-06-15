<?php

// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenants can run TWO member-facing frontends on their own domains:
 *   - the React SPA  → tenants.domain        (existing; e.g. pairc-goodman.com)
 *   - the accessible (GOV.UK) frontend → tenants.accessible_domain (new)
 *
 * Both resolve to the same tenant by Host (TenantContext::resolve). The
 * column is nullable + unique so each accessible hostname maps to one tenant;
 * MySQL permits multiple NULLs in a unique index, so tenants without an
 * accessible custom domain are unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tenants')) {
            return;
        }

        if (!Schema::hasColumn('tenants', 'accessible_domain')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->string('accessible_domain', 255)->nullable()->after('domain');
            });
        }

        if (!Schema::hasIndex('tenants', 'uq_tenants_accessible_domain')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->unique('accessible_domain', 'uq_tenants_accessible_domain');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('tenants')) {
            return;
        }

        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasIndex('tenants', 'uq_tenants_accessible_domain')) {
                $table->dropUnique('uq_tenants_accessible_domain');
            }
            if (Schema::hasColumn('tenants', 'accessible_domain')) {
                $table->dropColumn('accessible_domain');
            }
        });
    }
};
