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
 * Drop the legacy `allowed_layouts` column from `pay_plans`.
 *
 * The column was added to restrict tenants on a given plan to a subset of
 * page layouts (e.g. 'modern', 'civicone'). Both legacy layouts have been
 * removed; the React frontend is the only layout now and this column has
 * no functional consumer. The only would-be enforcement methods
 * (`PayPlan::getAllowedLayouts()` / `getCurrentPlanForTenant()`) never
 * existed.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('pay_plans')) {
            return;
        }
        if (Schema::hasColumn('pay_plans', 'allowed_layouts')) {
            Schema::table('pay_plans', function (Blueprint $table) {
                $table->dropColumn('allowed_layouts');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('pay_plans')) {
            return;
        }
        if (!Schema::hasColumn('pay_plans', 'allowed_layouts')) {
            Schema::table('pay_plans', function (Blueprint $table) {
                $table->json('allowed_layouts')->nullable()->after('features');
            });
        }
    }
};
