<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tier 2a governance: tenant-level safeguarding declaration.
 *
 * When a tenant declares it works with children or vulnerable adults, a Child
 * Safeguarding Statement (Children First Act 2015 / Tusla compliance) must be
 * uploaded before the tenant can be activated. The statement is stored under
 * uploads/tenants/{id}/safeguarding/ and served via a signed URL to tenant
 * members only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            if (!Schema::hasColumn('tenants', 'works_with_children')) {
                $table->boolean('works_with_children')->default(false)->after('description');
            }
            if (!Schema::hasColumn('tenants', 'works_with_vulnerable_adults')) {
                $table->boolean('works_with_vulnerable_adults')->default(false)->after('works_with_children');
            }
            if (!Schema::hasColumn('tenants', 'safeguarding_statement_path')) {
                $table->string('safeguarding_statement_path', 500)->nullable()->after('works_with_vulnerable_adults');
            }
            if (!Schema::hasColumn('tenants', 'safeguarding_statement_original_name')) {
                $table->string('safeguarding_statement_original_name', 255)->nullable()->after('safeguarding_statement_path');
            }
            if (!Schema::hasColumn('tenants', 'safeguarding_statement_uploaded_at')) {
                $table->timestamp('safeguarding_statement_uploaded_at')->nullable()->after('safeguarding_statement_original_name');
            }
            if (!Schema::hasColumn('tenants', 'safeguarding_statement_uploaded_by')) {
                $table->unsignedInteger('safeguarding_statement_uploaded_by')->nullable()->after('safeguarding_statement_uploaded_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            foreach ([
                'safeguarding_statement_uploaded_by',
                'safeguarding_statement_uploaded_at',
                'safeguarding_statement_original_name',
                'safeguarding_statement_path',
                'works_with_vulnerable_adults',
                'works_with_children',
            ] as $col) {
                if (Schema::hasColumn('tenants', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
